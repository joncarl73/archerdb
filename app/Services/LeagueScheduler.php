<?php

namespace App\Services;

use App\Models\League;
use App\Models\LeagueWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeagueScheduler
{
    /** Compute and (re)write league weeks using start_date, day_of_week, length_weeks (Carbon 1/2 compatible). */
    public function buildWeeks(League $league): void
    {
        DB::transaction(function () use ($league) {
            // wipe/rebuild weeks
            $league->weeks()->delete();

            // Normalize start date
            $start = $league->start_date instanceof Carbon
                ? $league->start_date->copy()->startOfDay()
                : Carbon::parse($league->start_date)->startOfDay();

            // Align to desired DOW on or after start_date (0=Sun..6=Sat)
            $target = (int) $league->day_of_week;           // desired
            $current = (int) $start->dayOfWeek;             // current
            $delta = ($target - $current + 7) % 7;          // 0..6, 0 = same day

            $first = $start->copy()->addDays($delta);

            // Generate N weeks
            $n = (int) $league->length_weeks;
            for ($i = 0; $i < $n; $i++) {
                LeagueWeek::create([
                    'league_id' => $league->id,
                    'week_number' => $i + 1,
                    'date' => $first->copy()->addWeeks($i)->toDateString(),
                ]);
            }
        });
    }
}
