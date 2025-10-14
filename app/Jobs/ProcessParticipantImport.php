<?php

namespace App\Jobs;

use App\Models\LeagueParticipant;
use App\Models\ParticipantImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessParticipantImport implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $participantImportId) {}

    public function handle(): void
    {
        /** @var ParticipantImport $import */
        $import = ParticipantImport::query()->findOrFail($this->participantImportId);

        if (! $import->isProcessable()) {
            Log::warning('participant_import.process: not processable', [
                'id' => $import->id,
                'status' => $import->status,
            ]);

            return;
        }

        $import->status = 'processing';
        $import->save();

        try {
            $stream = Storage::readStream($import->file_path);
            if (! $stream) {
                throw new \RuntimeException('Could not open staged CSV: '.$import->file_path);
            }

            // ---- Read header + rows; normalize headers; de-dup within CSV
            $rawHeader = fgetcsv($stream) ?: [];
            $map = $this->normalizeHeaders($rawHeader);

            $csvKeys = [];   // set of canonical keys seen
            $rows = [];      // normalized rows: ['first' => ..., 'last' => ..., 'email' => ..., 'key' => ...]
            $rawLines = 0;

            while (($line = fgetcsv($stream)) !== false) {
                // Skip blank line
                $joined = implode('', array_map(fn ($v) => trim((string) $v), $line));
                if ($joined === '') {
                    continue;
                }
                $rawLines++;

                // Map to assoc using normalized header map
                $assoc = [];
                foreach ($map as $i => $key) {
                    $assoc[$key] = trim((string) ($line[$i] ?? ''));
                }
                $first = (string) ($assoc['first_name'] ?? '');
                $last = (string) ($assoc['last_name'] ?? '');
                $email = (string) ($assoc['email'] ?? '');

                // Ignore rows with nothing in the 3 key fields
                if ($first === '' && $last === '' && $email === '') {
                    continue;
                }

                $key = $this->canonicalKey($first, $last, $email);

                // De-dup within this CSV
                if (isset($csvKeys[$key])) {
                    continue;
                }
                $csvKeys[$key] = true;

                $rows[] = [
                    'first' => $first,
                    'last' => $last,
                    'email' => $email,
                    'key' => $key,
                ];
            }
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (empty($rows)) {
                // Nothing to do; mark completed so UI doesn’t keep retrying or appear stuck
                $import->status = 'completed';
                $import->processed_at = Carbon::now();
                $import->save();

                Log::info('participant_import.process: no rows after normalization', [
                    'id' => $import->id,
                    'raw_lines' => $rawLines,
                ]);

                return;
            }

            // ---- Build “existing” sets from DB for fast membership checks
            $emails = array_values(array_unique(
                array_filter(array_map(fn ($r) => $r['email'] !== '' ? mb_strtolower(trim($r['email'])) : null, $rows))
            ));

            // For name-only rows (no email), we’ll compare against league participants where email IS NULL
            $needsNameOnlyCheck = (bool) count(array_filter($rows, fn ($r) => $r['email'] === ''));

            $existingEmailSet = [];
            if (! empty($emails)) {
                $existingEmails = LeagueParticipant::query()
                    ->where('league_id', $import->league_id)
                    ->whereIn(DB::raw('LOWER(email)'), $emails)
                    ->pluck('email')
                    ->all();

                foreach ($existingEmails as $e) {
                    if ($e !== null && $e !== '') {
                        $existingEmailSet[mb_strtolower(trim($e))] = true;
                    }
                }
            }

            $existingNameOnlySet = [];
            if ($needsNameOnlyCheck) {
                $nullEmailParticipants = LeagueParticipant::query()
                    ->where('league_id', $import->league_id)
                    ->whereNull('email')
                    ->get(['first_name', 'last_name']);

                foreach ($nullEmailParticipants as $p) {
                    $k = mb_strtolower(trim((string) $p->first_name)).'|'.mb_strtolower(trim((string) $p->last_name));
                    $existingNameOnlySet[$k] = true;
                }
            }

            // ---- Filter to only NEW rows (mirror the billing rules)
            $toInsert = [];
            $now = Carbon::now();
            $existingMatches = 0;

            foreach ($rows as $r) {
                if ($r['email'] !== '') {
                    $exists = isset($existingEmailSet[mb_strtolower(trim($r['email']))]);
                } else {
                    $k = mb_strtolower(trim($r['first'])).'|'.mb_strtolower(trim($r['last']));
                    $exists = isset($existingNameOnlySet[$k]);
                }

                if ($exists) {
                    $existingMatches++;

                    continue; // skip existing
                }

                $toInsert[] = [
                    'league_id' => $import->league_id,
                    'first_name' => $r['first'],
                    'last_name' => $r['last'],
                    'email' => $r['email'] !== '' ? $r['email'] : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // ---- Persist and finalize
            DB::transaction(function () use ($import, $toInsert) {
                if (! empty($toInsert)) {
                    // bulk insert (faster than create() in loop)
                    LeagueParticipant::query()->insert($toInsert);
                }

                $import->status = 'completed';
                $import->processed_at = Carbon::now();
                $import->save();
            });

            Log::info('participant_import.process: completed', [
                'id' => $import->id,
                'league_id' => $import->league_id,
                'raw_lines' => $rawLines,
                'unique_csv_rows' => count($rows),
                'existing_matched' => $existingMatches,
                'inserted' => count($toInsert),
            ]);
        } catch (\Throwable $e) {
            Log::error('participant_import.process: failed', [
                'id' => $import->id,
                'err' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $import->status = 'failed';
            $import->error_text = $e->getMessage();
            $import->save();
        }
    }

    /**
     * Normalize headers → positional map to expected keys: first_name, last_name, email
     * Returns an array like [0 => 'first_name', 1 => 'last_name', 2 => 'email', ...]
     */
    private function normalizeHeaders(?array $headers): array
    {
        // default positions if header missing
        if (! $headers) {
            return [0 => 'first_name', 1 => 'last_name', 2 => 'email'];
        }

        $map = [];
        $aliases = [
            'first_name' => ['first_name', 'first name', 'first', 'firstname', 'given', 'given_name', 'given name'],
            'last_name' => ['last_name', 'last name', 'last', 'lastname', 'surname', 'family', 'family_name', 'family name'],
            'email' => ['email', 'e-mail', 'mail'],
        ];

        foreach ($headers as $i => $h) {
            $k = mb_strtolower(trim((string) $h));
            $k = str_replace(['-', ' '], '_', $k);
            foreach ($aliases as $target => $list) {
                if ($k === $target || in_array($k, $list, true)) {
                    $map[$i] = $target;

                    continue 2;
                }
            }
            // unknown columns ignored
        }

        // Ensure we have all three keys (fallback by position)
        if (! in_array('first_name', $map, true)) {
            $map[0] = 'first_name';
        }
        if (! in_array('last_name', $map, true)) {
            $map[1] = 'last_name';
        }
        if (! in_array('email', $map, true)) {
            $map[2] = 'email';
        }

        ksort($map);

        return $map;
    }

    /**
     * Unique key per row: email (if present) else "first|last" for NULL-email participants.
     */
    private function canonicalKey(string $first, string $last, string $email): string
    {
        $e = mb_strtolower(trim($email));
        if ($e !== '') {
            return 'email:'.$e;
        }

        $f = mb_strtolower(trim($first));
        $l = mb_strtolower(trim($last));

        return 'name:'.$f.'|'.$l;
    }
}
