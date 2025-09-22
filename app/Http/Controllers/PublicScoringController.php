<?php

// app/Http/Controllers/PublicScoringController.php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueWeek;
use App\Models\LeagueWeekScore;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicScoringController extends Controller
{
    protected function leagueOr404(string $uuid): League
    {
        return League::query()->where('public_uuid', $uuid)->firstOrFail();
    }

    public function start(Request $request, string $uuid, int $checkinId)
    {
        $league = $this->leagueOr404($uuid);

        // guard: must be personal_device
        $mode = $league->scoring_mode->value ?? $league->scoring_mode;
        abort_unless($mode === 'personal_device', 404);

        $checkin = LeagueCheckin::with('participant')->findOrFail($checkinId);
        abort_unless($checkin->league_id === $league->id, 404);

        $participant = $checkin->participant;

        // pick the "current" week (>= today, or nearest active)
        $today = Carbon::today();
        $week = LeagueWeek::query()
            ->where('league_id', $league->id)
            ->orderByRaw('CASE WHEN date >= ? THEN 0 ELSE 1 END, ABS(DATEDIFF(date, ?))', [$today, $today])
            ->firstOrFail();

        // find or create the week score
        $score = LeagueWeekScore::firstOrCreate(
            [
                'league_id' => $league->id,
                'league_week_id' => $week->id,
                'league_participant_id' => $participant->id,
            ],
            [
                'arrows_per_end' => (int) ($league->arrows_per_end ?? 3),
                'ends_planned' => (int) ($league->ends_per_day ?? 10),
                'max_score' => 10,
                'x_value' => (int) ($league->x_ring_value ?? 10),
            ]
        );

        // seed ends if not present
        $existing = $score->ends()->count();
        if ($existing < $score->ends_planned) {
            for ($i = $existing + 1; $i <= $score->ends_planned; $i++) {
                $score->ends()->create([
                    'end_number' => $i,
                    'scores' => array_fill(0, $score->arrows_per_end, null),
                    'end_score' => 0,
                    'x_count' => 0,
                ]);
            }
        }

        return redirect()->route('public.scoring.record', [$league->public_uuid, $score->id]);
    }

    public function record(Request $request, string $uuid, int $scoreId)
    {
        $league = $this->leagueOr404($uuid);
        $mode = $league->scoring_mode->value ?? $league->scoring_mode;
        abort_unless($mode === 'personal_device', 404);

        $score = LeagueWeekScore::with(['ends' => fn ($q) => $q->orderBy('end_number')])->findOrFail($scoreId);
        abort_unless($score->league_id === $league->id, 404);

        // Wrapper view mounts the Livewire component
        return view('public.scoring.record', [
            'uuid' => $uuid,
            'score' => $score,
            'league' => $league,
        ]);
    }

    /**
     * NEW: End-scoring summary page
     */
    public function summary(Request $request, string $uuid, int $scoreId)
    {
        $league = $this->leagueOr404($uuid);
        $mode = $league->scoring_mode->value ?? $league->scoring_mode;
        abort_unless($mode === 'personal_device', 404);

        $score = LeagueWeekScore::with(['ends' => fn ($q) => $q->orderBy('end_number')])->findOrFail($scoreId);
        abort_unless($score->league_id === $league->id, 404);

        // Derive summary stats from ends/scores
        $stats = $this->computeSummaryStats($score);

        return view('public.scoring.summary', array_merge([
            'league' => $league,
            'score' => $score,
        ], $stats));
    }

    /**
     * Helper: compute totals/averages/completion/X stats.
     */
    protected function computeSummaryStats(LeagueWeekScore $score): array
    {
        $plannedEnds = (int) ($score->ends_planned ?? $score->ends->count());
        $arrowsPerEnd = (int) $score->arrows_per_end;
        $maxPerArrow = (int) $score->max_score;
        $xValue = (int) ($score->x_value ?? 10);

        $endsCompleted = 0;
        $arrowsEntered = 0;
        $xCount = 0;

        // If your model already tracks these, keep using them:
        $totalScore = (int) ($score->total_score ?? 0);

        foreach ($score->ends as $e) {
            $hasAny = false;
            if (is_array($e->scores)) {
                foreach ($e->scores as $sv) {
                    if (! is_null($sv)) {
                        $arrowsEntered++;
                        $hasAny = true;

                        // X-count: treat value == xValue as X when xValue is a special (>= max) value
                        if ((int) $sv === $xValue && $xValue >= $maxPerArrow) {
                            $xCount++;
                        }
                    }
                }
            }
            if ($hasAny) {
                $endsCompleted++;
            }
        }

        $maxPossibleEntered = $arrowsEntered * $maxPerArrow;
        $avgPerArrow = $arrowsEntered ? round($totalScore / $arrowsEntered, 2) : 0.0;
        $completionPct = $plannedEnds ? round(($endsCompleted / $plannedEnds) * 100) : 0;
        $xRate = $arrowsEntered ? round(($xCount / $arrowsEntered) * 100) : 0;

        return [
            'plannedEnds' => $plannedEnds,
            'arrowsPerEnd' => $arrowsPerEnd,
            'endsCompleted' => $endsCompleted,
            'arrowsEntered' => $arrowsEntered,
            'maxPerArrow' => $maxPerArrow,
            'totalScore' => $totalScore,
            'maxPossibleEntered' => $maxPossibleEntered,
            'avgPerArrow' => $avgPerArrow,
            'completionPct' => $completionPct,
            'xCount' => $xCount,
            'xRate' => $xRate,
        ];
    }
}
