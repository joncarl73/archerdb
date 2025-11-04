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

            $rawHeader = fgetcsv($stream) ?: [];
            $map = $this->normalizeHeaders($rawHeader);

            $csvKeys = [];
            $rows = [];
            $rawLines = 0;

            while (($line = fgetcsv($stream)) !== false) {
                $joined = implode('', array_map(fn ($v) => trim((string) $v), $line));
                if ($joined === '') {
                    continue;
                }

                $rawLines++;

                $assoc = [];
                foreach ($map as $i => $key) {
                    $assoc[$key] = trim((string) ($line[$i] ?? ''));
                }

                $first = (string) ($assoc['first_name'] ?? '');
                $last = (string) ($assoc['last_name'] ?? '');
                $email = (string) ($assoc['email'] ?? '');

                if ($first === '' && $last === '' && $email === '') {
                    continue;
                }

                $key = $this->canonicalKey($first, $last, $email);
                if (isset($csvKeys[$key])) {
                    continue;
                }
                $csvKeys[$key] = true;

                $rows[] = [
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $email,
                    'division_name' => (string) ($assoc['division_name'] ?? ''),
                    'bow_type' => (string) ($assoc['bow_type'] ?? ''),
                    'is_para' => $this->toBool((string) ($assoc['is_para'] ?? '')),
                    'uses_wheelchair' => $this->toBool((string) ($assoc['uses_wheelchair'] ?? '')),
                    'notes' => (string) ($assoc['notes'] ?? ''),
                    'key' => $key,
                ];
            }
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (empty($rows)) {
                $import->status = 'completed';
                $import->processed_at = Carbon::now();
                $import->save();
                Log::info('participant_import.process: no rows after normalization', ['id' => $import->id, 'raw_lines' => $rawLines]);

                return;
            }

            // Branch by owner: league vs event
            if ($import->league_id) {
                $this->processForLeague($import, $rows, $rawLines);
            } else {
                $this->processForEvent($import, $rows, $rawLines);
            }
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

    /** ===== LEAGUE path: unchanged from your current behavior ===== */
    private function processForLeague(ParticipantImport $import, array $rows, int $rawLines): void
    {
        $emails = array_values(array_unique(
            array_filter(array_map(fn ($r) => $r['email'] !== '' ? mb_strtolower(trim($r['email'])) : null, $rows))
        ));
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

        $toInsert = [];
        $now = Carbon::now();
        $existingMatches = 0;

        foreach ($rows as $r) {
            if ($r['email'] !== '') {
                $exists = isset($existingEmailSet[mb_strtolower(trim($r['email']))]);
            } else {
                $k = mb_strtolower(trim($r['first_name'])).'|'.mb_strtolower(trim($r['last_name']));
                $exists = isset($existingNameOnlySet[$k]);
            }

            if ($exists) {
                $existingMatches++;

                continue;
            }

            $toInsert[] = [
                'league_id' => $import->league_id,
                'first_name' => $r['first_name'],
                'last_name' => $r['last_name'],
                'email' => $r['email'] !== '' ? $r['email'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($import, $toInsert) {
            if (! empty($toInsert)) {
                LeagueParticipant::query()->insert($toInsert);
            }
            $import->status = 'completed';
            $import->processed_at = Carbon::now();
            $import->save();
        });

        Log::info('participant_import.process: league completed', [
            'id' => $import->id,
            'league_id' => $import->league_id,
            'raw_lines' => $rawLines,
            'inserted' => count($toInsert),
        ]);
    }

    /** ===== EVENT path: writes to trimmed event_participants schema ===== */
    private function processForEvent(ParticipantImport $import, array $rows, int $rawLines): void
    {
        $EventParticipant = \App\Models\EventParticipant::class;

        // -----------------------------------------------------------------
        // A) Dedup against existing event participants (email or name-only)
        // -----------------------------------------------------------------
        $emails = array_values(array_unique(
            array_filter(array_map(fn ($r) => $r['email'] !== '' ? mb_strtolower(trim($r['email'])) : null, $rows))
        ));

        $existingEmailSet = [];
        if (! empty($emails)) {
            $existing = $EventParticipant::query()
                ->where('event_id', $import->event_id)
                ->whereIn(DB::raw('LOWER(email)'), $emails)
                ->pluck('email')
                ->all();
            foreach ($existing as $e) {
                if ($e !== null && $e !== '') {
                    $existingEmailSet[mb_strtolower(trim($e))] = true;
                }
            }
        }

        $needsNameOnly = (bool) count(array_filter($rows, fn ($r) => $r['email'] === ''));
        $existingNameOnlySet = [];
        if ($needsNameOnly) {
            $nullEmailParticipants = $EventParticipant::query()
                ->where('event_id', $import->event_id)
                ->whereNull('email')
                ->get(['first_name', 'last_name']);
            foreach ($nullEmailParticipants as $p) {
                $k = mb_strtolower(trim((string) $p->first_name)).'|'.mb_strtolower(trim((string) $p->last_name));
                $existingNameOnlySet[$k] = true;
            }
        }

        // -----------------------------------------------------------------
        // B) Resolve apply_line_time_id from meta (array or JSON string)
        //    + validate it belongs to this event_line_times set
        // -----------------------------------------------------------------
        $meta = $import->meta;
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($meta)) {
            $meta = [];
        }

        $applyLineTimeId = null;
        if (array_key_exists('apply_line_time_id', $meta)) {
            $cand = $meta['apply_line_time_id'];
            $cand = is_numeric($cand) ? (int) $cand : 0;
            if ($cand > 0) {
                // Ensure the selected line time is for THIS event
                $exists = \App\Models\EventLineTime::query()
                    ->where('event_id', $import->event_id)
                    ->whereKey($cand)
                    ->exists();
                $applyLineTimeId = $exists ? $cand : null;
            }
        }

        // Log BEFORE we insert so we can see the resolved value clearly
        Log::debug('participant_import.process: apply_line_time_id resolved', [
            'import_id' => $import->id,
            'meta_raw' => $import->getRawOriginal('meta'), // raw from DB (useful if casts hide issues)
            'meta_decoded' => $meta,
            'apply_line_time' => $applyLineTimeId,
            'event_id' => $import->event_id,
        ]);

        // -----------------------------------------------------------------
        // C) Build inserts
        // -----------------------------------------------------------------
        $now = Carbon::now();
        $toInsert = [];

        foreach ($rows as $r) {
            $exists = false;
            if ($r['email'] !== '') {
                $exists = isset($existingEmailSet[mb_strtolower(trim($r['email']))]);
            } else {
                $k = mb_strtolower(trim($r['first_name'])).'|'.mb_strtolower(trim($r['last_name']));
                $exists = isset($existingNameOnlySet[$k]);
            }
            if ($exists) {
                continue;
            }

            $toInsert[] = [
                'event_id' => $import->event_id,
                'first_name' => $r['first_name'],
                'last_name' => $r['last_name'],
                'email' => $r['email'] !== '' ? $r['email'] : null,
                'division_name' => $r['division_name'] !== '' ? $r['division_name'] : null,
                'bow_type' => $r['bow_type'] !== '' ? $r['bow_type'] : null,
                'is_para' => (int) $r['is_para'],
                'uses_wheelchair' => (int) $r['uses_wheelchair'],
                'line_time_id' => $applyLineTimeId,   // applied to all uploaded rows (may be null if not selected/valid)
                'assigned_lane' => null,               // set later by admin
                'assigned_slot' => null,               // set later by admin
                'notes' => $r['notes'] !== '' ? $r['notes'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // -----------------------------------------------------------------
        // D) Insert + finalize
        // -----------------------------------------------------------------
        DB::transaction(function () use ($import, $toInsert) {
            if (! empty($toInsert)) {
                \App\Models\EventParticipant::query()->insert($toInsert);
            }
            $import->status = 'completed';
            $import->processed_at = Carbon::now();
            $import->save();
        });

        Log::info('participant_import.process: event completed', [
            'id' => $import->id,
            'event_id' => $import->event_id,
            'raw_lines' => $rawLines,
            'inserted' => count($toInsert),
            'line_time_id' => $applyLineTimeId,
        ]);
    }

    /** ===== Helpers (shared) ===== */
    private function normalizeHeaders(?array $headers): array
    {
        if (! $headers) {
            // positional fallback for the *new* schema CSV
            return [
                0 => 'first_name', 1 => 'last_name', 2 => 'email', 3 => 'division_name', 4 => 'bow_type',
                5 => 'is_para', 6 => 'uses_wheelchair', 7 => 'notes',
            ];
        }

        $aliases = [
            'first_name' => ['first_name', 'first name', 'first', 'firstname', 'given', 'given_name', 'given name'],
            'last_name' => ['last_name', 'last name', 'last', 'lastname', 'surname', 'family', 'family_name', 'family name'],
            'email' => ['email', 'e-mail', 'mail'],
            'division_name' => ['division_name', 'division', 'division name'],
            'bow_type' => ['bow_type', 'bow type', 'bow'],
            'is_para' => ['is_para', 'para', 'is para', 'disabled'],
            'uses_wheelchair' => ['uses_wheelchair', 'wheelchair', 'uses wheelchair'],
            'notes' => ['notes', 'note', 'comments', 'comment'],
        ];

        $map = [];
        foreach ($headers as $i => $h) {
            $k = mb_strtolower(trim((string) $h));
            $k = str_replace(['-', ' '], '_', $k);
            foreach ($aliases as $target => $list) {
                if ($k === $target || in_array($k, $list, true)) {
                    $map[$i] = $target;

                    continue 2;
                }
            }
        }

        // Ensure all expected exist (fallback positional)
        $needs = ['first_name', 'last_name', 'email', 'division_name', 'bow_type', 'is_para', 'uses_wheelchair', 'notes'];
        foreach ($needs as $idx => $key) {
            if (! in_array($key, $map, true)) {
                $map[$idx] = $key;
            }
        }
        ksort($map);

        return $map;
    }

    private function canonicalKey(string $first, string $last, string $email): string
    {
        $e = mb_strtolower(trim($email));
        if ($e !== '') {
            return 'email:'.$e;
        }
        $f = mb_strtolower(trim($first));
        $l = mb_strtolower(trim($last)); // ✅ fixed typo

        return 'name:'.$f.'|'.$l;
    }

    private function toBool(string $v): bool
    {
        $v = mb_strtolower(trim($v));
        if ($v === '' || $v === '0' || $v === 'no' || $v === 'false' || $v === 'n') {
            return false;
        }

        return (bool) filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? in_array($v, ['y', 'yes', '1', 'true'], true);
    }
}
