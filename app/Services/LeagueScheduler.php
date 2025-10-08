<?php

namespace App\Services;

use App\Models\League;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeagueScheduler
{
    /**
     * Compute and (re)write league weeks using start_date, day_of_week, length_weeks.
     * Also snapshots scoring defaults (ends_per_day, arrows_per_end) onto each week.
     */
    public function buildWeeks(League $league): void
    {
        DB::transaction(function () use ($league) {
            // Wipe & rebuild weeks to keep schedule consistent after edits
            $league->weeks()->delete();

            // Normalize start date
            $start = $league->start_date instanceof Carbon
                ? $league->start_date->copy()->startOfDay()
                : Carbon::parse($league->start_date)->startOfDay();

            // Align to desired weekday on or after start_date (0=Sun..6=Sat)
            $target = (int) $league->day_of_week;     // desired DOW
            $current = (int) $start->dayOfWeek;        // current DOW
            $delta = ($target - $current + 7) % 7;   // 0..6, 0 means same day
            $first = $start->copy()->addDays($delta);

            $n = max(0, (int) $league->length_weeks);

            // Snapshot scoring defaults from League
            $endsDefault = (int) ($league->ends_per_day ?? 10);
            $arrowsDefault = (int) ($league->arrows_per_end ?? 3);

            for ($i = 0; $i < $n; $i++) {
                $league->weeks()->create([
                    'week_number' => $i + 1,
                    'date' => $first->copy()->addWeeks($i)->toDateString(),
                    'ends' => $endsDefault,
                    'arrows_per_end' => $arrowsDefault,
                ]);
            }
        });
    }
}
