<?php

// app/Observers/LeagueWeekEndObserver.php

namespace App\Observers;

use App\Models\LeagueWeekEnd;

class LeagueWeekEndObserver
{
    public function saved(LeagueWeekEnd $end): void
    {
        $score = $end->score()->with('ends')->first(); // relation name 'score'
        if (! $score) {
            return;
        }

        // Recompute parent totals deterministically
        $total = 0;
        $x = 0;
        foreach ($score->ends as $e) {
            $total += (int) ($e->end_score ?? 0);
            $x += (int) ($e->x_count ?? 0);
        }

        // Save parent (this will trigger LeagueWeekScoreObserver::saved)
        $score->forceFill([
            'total_score' => $total,
            'x_count' => $x,
        ])->saveQuietly(); // avoid recursive 'saved' loop on end
    }
}
