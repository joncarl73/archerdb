<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueWeek;
use App\Models\LeagueWeekScore;

class LeagueCheckinObserver
{
    /**
     * Ensure event_id is stamped before insert.
     */
    public function creating(LeagueCheckin $c): void
    {
        $this->ensureEventId($c);
    }

    /**
     * Ensure event_id is stamped on updates too (e.g., if league_id changes).
     */
    public function updating(LeagueCheckin $c): void
    {
        $this->ensureEventId($c);
    }

    /**
     * After the check-in is saved, auto-seed a zeroed score row for that participant/week.
     * (We do NOT try to set event_id here to avoid save loops; it's done in creating/updating.)
     */
    public function saved(LeagueCheckin $lc): void
    {
        // Guard: must have a league and a week number
        if (empty($lc->league_id) || empty($lc->week_number)) {
            return;
        }

        // Resolve league once
        $league = $lc->relationLoaded('league')
            ? $lc->league
            : League::select('id', 'event_id', 'arrows_per_end', 'ends_per_day', 'x_ring_value')
                ->find($lc->league_id);

        if (! $league) {
            return;
        }

        // Resolve event (prefer stamped on check-in; else from league)
        $event = null;
        $eventId = $lc->event_id ?: $league->event_id;
        if ($eventId) {
            $event = Event::select('id')->find($eventId);
        }

        // Find the target week in the right context (supports legacy leagues and event-scoped multi-day)
        $week = LeagueWeek::query()
            ->forContext($event, $league)
            ->where('week_number', (int) $lc->week_number)
            ->first();

        if (! $week) {
            // No matching week (possibly schedule not built yet)
            return;
        }

        // Only seed a score row when check-in ties to a registered participant
        if (! $lc->participant_id) {
            return;
        }

        // Ensure a LeagueWeekScore exists for this participant & week (seed with league defaults)
        LeagueWeekScore::firstOrCreate(
            [
                'league_id' => (int) $league->id,
                'league_week_id' => (int) $week->id,
                'league_participant_id' => (int) $lc->participant_id,
            ] + ($event ? ['event_id' => (int) $event->id] : []),
            [
                'arrows_per_end' => (int) ($league->arrows_per_end ?? 3),
                'ends_planned' => (int) ($league->ends_per_day ?? 10),
                'max_score' => 10,
                'x_value' => (int) ($league->x_ring_value ?? 10),
            ]
        );

        // If you broadcast to a live scoreboard, trigger your event here.
        // (E.g., WeekRowUpdated::dispatchAfterCommit($week->id);)
    }

    /**
     * Helper: stamp event_id from the league if missing.
     */
    private function ensureEventId(LeagueCheckin $c): void
    {
        if (! empty($c->event_id)) {
            return;
        }

        $league = $c->relationLoaded('league')
            ? $c->league
            : ($c->league_id ? League::select('id', 'event_id')->find($c->league_id) : null);

        if ($league && $league->event_id) {
            // Set the attribute directly to avoid mass-assignment restrictions
            $c->event_id = (int) $league->event_id;
        }
    }
}
