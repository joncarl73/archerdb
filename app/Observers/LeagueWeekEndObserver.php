<?php

// app/Observers/LeagueWeekEndObserver.php

namespace App\Observers;

use App\Models\League;
use App\Models\LeagueWeekEnd;
use App\Models\LeagueWeekScore;

class LeagueWeekEndObserver
{
    /**
     * Before insert: ensure event_id is stamped and (optionally) derive end stats.
     */
    public function creating(LeagueWeekEnd $e): void
    {
        $this->ensureEventId($e);
        $this->ensureEndStats($e); // optional: keeps row internally consistent when scores provided
    }

    /**
     * Before update: re-stamp event_id if missing; recompute stats if scores changed.
     */
    public function updating(LeagueWeekEnd $e): void
    {
        $this->ensureEventId($e);

        if ($e->isDirty('scores')) {
            $this->ensureEndStats($e); // optional
        }
    }

    /**
     * After save: recompute parent score totals from all ends (deterministic, quiet save).
     */
    public function saved(LeagueWeekEnd $end): void
    {
        $score = $end->relationLoaded('score')
            ? $end->score
            : $end->score()->with('ends')->first();

        if (! $score) {
            return;
        }

        // Sum totals from child ends
        $total = 0;
        $x = 0;
        foreach ($score->ends as $e) {
            $total += (int) ($e->end_score ?? 0);
            $x += (int) ($e->x_count ?? 0);
        }

        // Quietly update the parent without triggering child re-saves
        $score->forceFill([
            'total_score' => $total,
            'x_count' => $x,
        ])->saveQuietly();
    }

    /**
     * Stamp event_id from parent score or, failing that, the score's league.
     */
    private function ensureEventId(LeagueWeekEnd $e): void
    {
        if (! empty($e->event_id)) {
            return;
        }

        $score = $e->relationLoaded('score')
            ? $e->score
            : ($e->league_week_score_id
                ? LeagueWeekScore::select('id', 'event_id', 'league_id')->find($e->league_week_score_id)
                : null);

        if ($score && $score->event_id) {
            $e->event_id = (int) $score->event_id;

            return;
        }

        if ($score && $score->league_id) {
            $league = League::select('id', 'event_id')->find($score->league_id);
            if ($league && $league->event_id) {
                $e->event_id = (int) $league->event_id;
            }
        }
    }

    /**
     * OPTIONAL: Keep end_score and x_count aligned with the 'scores' array.
     * (Uses parent score's max_score/x_value when available; defaults to 10.)
     */
    private function ensureEndStats(LeagueWeekEnd $e): void
    {
        $scores = is_array($e->scores) ? $e->scores : null;
        if ($scores === null) {
            return;
        }

        $score = $e->relationLoaded('score')
            ? $e->score
            : ($e->league_week_score_id
                ? LeagueWeekScore::select('id', 'max_score', 'x_value')->find($e->league_week_score_id)
                : null);

        $max = (int) ($score->max_score ?? 10);
        $xVal = (int) ($score->x_value ?? $max);

        $endTotal = 0;
        $xCount = 0;

        foreach ($scores as $sv) {
            if ($sv === null) {
                continue;
            }
            $v = (int) $sv;
            $endTotal += $v;
            if ($v === $xVal && $xVal >= $max) {
                $xCount++;
            }
        }

        $e->end_score = $endTotal;
        $e->x_count = $xCount;
    }
}
