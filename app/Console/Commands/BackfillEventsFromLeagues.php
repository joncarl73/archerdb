<?php

namespace App\Console\Commands;

use App\Enums\EventKind;
use App\Models\Event;
use App\Models\League;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillEventsFromLeagues extends Command
{
    protected $signature = 'events:backfill-from-leagues {--update-existing : Also fix/update existing events}';

    protected $description = 'Create/repair Event rows for existing leagues and link league.event_id with accurate scoring_mode.';

    public function handle(): int
    {
        $updated = 0;
        $created = 0;

        // Pull related bits we might need to infer scoring mode
        League::query()
            ->when(! $this->option('update-existing'), fn ($q) => $q->whereNull('event_id'))
            ->with(['info', 'kioskSessions' => fn ($q) => $q->select('id', 'league_id')->limit(1)])
            ->chunkById(250, function ($leagues) use (&$created, &$updated) {
                foreach ($leagues as $L) {
                    $mode = $this->inferScoringMode($L);
                    $kind = ($L->type->value ?? $L->type ?? null) === 'closed'
                        ? EventKind::LeagueClosed->value
                        : EventKind::LeagueOpen->value;

                    if ($L->event_id) {
                        // Repair/update existing event
                        $E = $L->event;
                        if (! $E) {
                            continue;
                        }

                        $dirty = false;
                        if ($E->scoring_mode !== $mode) {
                            $E->scoring_mode = $mode;
                            $dirty = true;
                        }
                        if ($E->kind !== $kind) {
                            $E->kind = $kind;
                            $dirty = true;
                        }
                        // Keep titles in sync once during backfill
                        if ($E->wasRecentlyCreated || $E->title !== $L->title) {
                            $E->title = $L->title;
                            $dirty = true;
                        }
                        if ($dirty) {
                            $E->save();
                            $updated++;
                        }

                        continue;
                    }

                    // Create a brand new Event
                    $E = Event::create([
                        'public_uuid' => $L->public_uuid ?: (string) Str::uuid(),
                        'owner_id' => $L->owner_id,
                        'title' => $L->title,
                        'kind' => $kind,
                        'scoring_mode' => $mode,
                        'is_published' => (bool) ($L->is_published ?? false),
                        'starts_on' => $L->registration_start_date ?? null,
                        'ends_on' => $L->registration_end_date ?? null,
                    ]);

                    $L->forceFill(['event_id' => $E->id])->save();
                    $created++;
                }
            });

        $this->info("Events created: {$created}; Events updated: {$updated}");

        return self::SUCCESS;
    }

    /**
     * Infer scoring mode from multiple possible legacy flags + history.
     * Priority:
     *  1) Explicit mode fields if present (scoring_mode/mode)
     *  2) kiosk_only / personal_only toggles
     *  3) allow_kiosk / allow_personal booleans (League or LeagueInfo)
     *  4) Historical kiosk sessions => at least 'both'
     *  5) Fallback 'personal'
     */
    protected function inferScoringMode(League $L): string
    {
        $explicit = $L->scoring_mode ?? $L->mode ?? null;
        if (in_array($explicit, ['personal', 'kiosk', 'both'], true)) {
            return $explicit;
        }

        $info = $L->info ?? null;

        $kioskOnly = (bool) ($L->kiosk_only ?? $info?->kiosk_only ?? false);
        $personalOnly = (bool) ($L->personal_only ?? $info?->personal_only ?? false);

        if ($kioskOnly) {
            return 'kiosk';
        }
        if ($personalOnly) {
            return 'personal';
        }

        $allowKiosk = (bool) ($L->kiosk_enabled ?? $L->allow_kiosk ?? $info?->kiosk_enabled ?? $info?->allow_kiosk ?? false);
        $allowPersonal = (bool) ($L->personal_enabled ?? $L->allow_personal ?? $info?->personal_enabled ?? $info?->allow_personal ?? true);

        $hasKioskHistory = method_exists($L, 'kioskSessions') ? $L->kioskSessions()->exists() : false;

        if ($allowKiosk && ! $allowPersonal) {
            return 'kiosk';
        }
        if (($allowKiosk || $hasKioskHistory) && $allowPersonal) {
            return 'both';
        }
        if ($hasKioskHistory) {
            return 'both';
        } // conservative: don’t drop kiosk

        return 'personal';
    }
}
