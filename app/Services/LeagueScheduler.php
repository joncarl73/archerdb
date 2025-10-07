<?php

namespace App\Services;

use App\Enums\EventKind;
use App\Enums\EventScoringMode;
use App\Models\Event;
use App\Models\League;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeagueScheduler
{
    /**
     * Create or locate the backing Event for a League and link it.
     * Returns the Event id.
     */
    private function ensureEventForLeague(League $league): int
    {
        // If already linked, use it
        if ($league->event_id) {
            return (int) $league->event_id;
        }

        // Try to find an existing event that obviously belongs to this league
        $existing = Event::query()
            ->where('owner_id', $league->owner_id)
            ->where('title', $league->title)
            ->where('kind', EventKind::League) // enum cast ok; if using strings use 'league'
            ->latest('id')
            ->first();

        if (! $existing) {
            // Create a new Event on the fly (safe even if you also have the observer)
            $event = new Event;
            $event->owner_id = $league->owner_id;
            $event->public_uuid = (string) \Illuminate\Support\Str::uuid();
            $event->title = $league->title;
            $event->kind = EventKind::League;
            $event->scoring_mode = EventScoringMode::fromLeague($league->scoring_mode instanceof \UnitEnum ? $league->scoring_mode->value : $league->scoring_mode);
            $event->is_published = (bool) $league->is_published;
            $event->starts_on = $league->registration_start_date ?: $league->start_date;
            $event->ends_on = $league->registration_end_date;
            $event->price_cents = $league->price_cents;
            $event->currency = $league->currency;
            $event->save();
            $existing = $event;
        }

        // Link back quietly so we don’t trigger loops
        $league->forceFill(['event_id' => $existing->id])->saveQuietly();

        return (int) $existing->id;
    }

    /**
     * Compute and (re)write league weeks using start_date, day_of_week, length_weeks.
     * Snapshots scoring defaults and stamps event_id on every row.
     */
    public function buildWeeks(League $league): void
    {
        DB::transaction(function () use ($league) {
            // Refresh in case caller has a stale instance
            $league->refresh();

            // Ensure the league is linked to an Event before creating weeks
            $eventId = $this->ensureEventForLeague($league);

            // Wipe & rebuild for consistency after edits
            $league->weeks()->delete();

            // Normalize start date
            $start = $league->start_date instanceof Carbon
                ? $league->start_date->copy()->startOfDay()
                : Carbon::parse($league->start_date)->startOfDay();

            // Align to desired weekday on or after start_date (0=Sun..6=Sat)
            $target = (int) $league->day_of_week;
            $current = (int) $start->dayOfWeek;
            $delta = ($target - $current + 7) % 7;
            $first = $start->copy()->addDays($delta);

            $n = max(0, (int) $league->length_weeks);

            // Snapshot scoring defaults from League
            $endsDefault = (int) ($league->ends_per_day ?? 10);
            $arrowsDefault = (int) ($league->arrows_per_end ?? 3);

            for ($i = 0; $i < $n; $i++) {
                $weekNumber = $i + 1;

                $league->weeks()->create([
                    'event_id' => $eventId,                      // ← important
                    'week_number' => $weekNumber,
                    'label' => "Week {$weekNumber}",
                    'date' => $first->copy()->addWeeks($i)->toDateString(),
                    'ends' => $endsDefault,
                    'arrows_per_end' => $arrowsDefault,
                ]);
            }
        });
    }
}
