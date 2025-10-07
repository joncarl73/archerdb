<?php

namespace App\Console\Commands;

use App\Enums\EventKind;
use App\Models\Event;
use App\Models\League;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairEventScoringModes extends Command
{
    protected $signature = 'events:repair-scoring-modes
                            {--dry : Show what would change without saving}';

    protected $description = 'Repairs scoring_mode (and kind/title) on events that were backfilled from leagues.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $updated = 0;
        $scanned = 0;

        // Only leagues that are already linked to events
        League::query()
            ->whereNotNull('event_id')
            ->chunkById(250, function ($leagues) use (&$updated, &$scanned, $dry) {
                foreach ($leagues as $L) {
                    $scanned++;

                    // Robust detection: look for flags on league and info, plus kiosk history by league_id OR event_id
                    $info = $L->info ?? null;

                    $explicit = $L->scoring_mode ?? $L->mode ?? null; // if you had a legacy explicit field
                    if (! in_array($explicit, ['personal', 'kiosk', 'both'], true)) {
                        $explicit = null;
                    }

                    $kioskOnly = (bool) ($L->kiosk_only ?? $info?->kiosk_only ?? false);
                    $personalOnly = (bool) ($L->personal_only ?? $info?->personal_only ?? false);

                    $allowKiosk = (bool) ($L->kiosk_enabled ?? $L->allow_kiosk ?? $info?->kiosk_enabled ?? $info?->allow_kiosk ?? false);
                    $allowPersonal = (bool) ($L->personal_enabled ?? $L->allow_personal ?? $info?->personal_enabled ?? $info?->allow_personal ?? true);

                    // Kiosk history: accept either link (pre/post backfill)
                    $hasKioskHistory = DB::table('kiosk_sessions')
                        ->where(function ($q) use ($L) {
                            $q->where('league_id', $L->id);
                            if ($L->event_id) {
                                $q->orWhere('event_id', $L->event_id);
                            }
                        })
                        ->exists();

                    // Compute target mode
                    $mode = $explicit
                        ?? ($kioskOnly ? 'kiosk'
                            : ($personalOnly ? 'personal'
                                : ($allowKiosk && ! $allowPersonal ? 'kiosk'
                                    : (($allowKiosk || $hasKioskHistory) && $allowPersonal ? 'both'
                                        : ($hasKioskHistory ? 'both' : 'personal')))));

                    // Compute event kind conservatively
                    $typeVal = ($L->type->value ?? $L->type ?? null);
                    $kind = $typeVal === 'closed' ? EventKind::LeagueClosed->value : EventKind::LeagueOpen->value;

                    // Fetch event directly by PK (don’t rely on relation)
                    /** @var Event|null $E */
                    $E = Event::query()->find($L->event_id);
                    if (! $E) {
                        continue;
                    }

                    $changes = [];
                    if ($E->scoring_mode !== $mode) {
                        $changes['scoring_mode'] = $mode;
                    }
                    if ($E->kind !== $kind) {
                        $changes['kind'] = $kind;
                    }
                    if ($E->title !== $L->title) {
                        $changes['title'] = $L->title;
                    }
                    if ($E->starts_on != $L->registration_start_date) {
                        $changes['starts_on'] = $L->registration_start_date;
                    }
                    if ($E->ends_on != $L->registration_end_date) {
                        $changes['ends_on'] = $L->registration_end_date;
                    }

                    if ($changes) {
                        $this->line(sprintf(
                            '[%s] fixing event_id=%d: %s',
                            $dry ? 'DRY' : 'DO ',
                            $E->id,
                            json_encode($changes)
                        ));

                        if (! $dry) {
                            $E->fill($changes)->save();
                            $updated++;
                        }
                    }
                }
            });

        $this->info("Scanned leagues: {$scanned}. ".($dry ? "Would update: {$updated}" : "Updated: {$updated}"));

        return self::SUCCESS;
    }
}
