<?php

namespace App\Observers;

use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueWeek;
use App\Models\LeagueWeekScore;

class LeagueCheckinObserver
{
    public function saved(LeagueCheckin $lc): void
    {
        // Validate league/week
        $league = League::find($lc->league_id);
        if (! $league) {
            return;
        }

        $week = LeagueWeek::where('league_id', $lc->league_id)
            ->where('week_number', $lc->week_number)
            ->first();

        if (! $week) {
            return;
        }

        // We only broadcast a scoreboard row when the check-in ties to a participant.
        if (! $lc->participant_id) {
            return;
        }

        // Ensure a score row exists for this participant/week (zeros by default).
        $score = LeagueWeekScore::firstOrCreate(
            [
                'league_id' => (int) $lc->league_id,
                'league_week_id' => (int) $week->id,
                'league_participant_id' => (int) $lc->participant_id,
            ],
            [
                'arrows_per_end' => (int) ($league->arrows_per_end ?? 3),
                'ends_planned' => (int) ($league->ends_per_day ?? 10),
                'max_score' => 10,
                'x_value' => (int) ($league->x_ring_value ?? 10),
            ]
        );

        // Broadcast ONE canonical update for the live board.
        // NOTE: WeekRowUpdated should have $afterCommit = true and build the payload from fresh DB.

    }
}
