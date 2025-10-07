<?php

// app/Http/Controllers/PublicScoringController.php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueWeek;
use App\Models\LeagueWeekScore;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

class PublicScoringController extends Controller
{
    protected function leagueOr404(string $uuid): League
    {
        return League::query()->where('public_uuid', $uuid)->firstOrFail();
    }

    public function start(Request $request, string $uuid, int $checkinId)
    {
        $league = $this->leagueOr404($uuid);

        // NEW: allow explicit PD override via ?pd=1 (for makeups/off-night)
        $forcePD = $request->boolean('pd');

        $mode = $league->scoring_mode->value ?? $league->scoring_mode;

        // If league is personal_device OR we explicitly force PD, allow; otherwise 404
        abort_unless($forcePD || $mode === 'personal_device', 404);

        $checkin = LeagueCheckin::with('participant')->findOrFail($checkinId);
        abort_unless($checkin->league_id === $league->id, 404);

        $participant = $checkin->participant;

        // Use the week selected at check-in (authoritative for makeups)
        $event = $league->event ?? null;

        $week = LeagueWeek::query()
            ->forContext($event, $league)
            ->where('week_number', $checkin->week_number)
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

        // IMPORTANT: in PD override we do NOT set kiosk session flags
        return redirect()->route('public.scoring.record', [$league->public_uuid, $score->id]);
    }

    public function record(Request $request, string $uuid, int $scoreId)
    {
        // Unique trace id for this request
        $rid = uniqid('rec_', false);
        $ctx = [
            'rid' => $rid,
            'uuid' => $uuid,
            'scoreId' => $scoreId,
            'route' => Route::currentRouteName(),
            'fullUrl' => $request->fullUrl(),
            'referer' => $request->headers->get('referer'),
            'session_id' => $request->session()->getId(),
        ];

        Log::debug('record:start', $ctx);

        // 1) League lookup
        try {
            $league = $this->leagueOr404($uuid);
            $ctx['league_id'] = $league->id;
            $mode = $league->scoring_mode->value ?? $league->scoring_mode;
            $ctx['mode'] = (string) $mode;
            Log::debug('record:league.ok', $ctx);
        } catch (Throwable $e) {
            Log::error('record:league.fail', $ctx + ['ex' => $e->getMessage()]);
            abort(404, 'REC-E1'); // league not found
        }

        // 2) Session flags (kiosk handoff)
        $kioskMode = (bool) $request->session()->get('kiosk_mode', false);
        $kioskReturnTo = $request->session()->get('kiosk_return_to');
        $ctx['kioskMode'] = $kioskMode;
        $ctx['kioskReturnTo'] = $kioskReturnTo;

        // 3) Guard: allow personal_device OR kiosk/tablet OR kiosk flag
        $allowed = $kioskMode || in_array((string) $ctx['mode'], ['personal_device', 'kiosk', 'tablet'], true);
        Log::debug('record:guard.check', $ctx + ['allowed' => $allowed]);
        if (! $allowed) {
            Log::warning('record:guard.block', $ctx);
            abort(404, 'REC-G1'); // guard blocked
        }

        // 4) Load score scoped to league
        try {
            $score = LeagueWeekScore::with(['ends' => fn ($q) => $q->orderBy('end_number')])
                ->where('league_id', $league->id)
                ->findOrFail($scoreId);

            $ctx['score_league_id'] = $score->league_id;
            $ctx['league_week_id'] = $score->league_week_id;
            $ctx['participant_id'] = $score->league_participant_id;
            Log::debug('record:score.ok', $ctx);
        } catch (ModelNotFoundException $e) {
            Log::warning('record:score.not_found', $ctx);
            abort(404, 'REC-SNF'); // score id not found under this league
        } catch (Throwable $e) {
            Log::error('record:score.error', $ctx + ['ex' => $e->getMessage()]);
            abort(404, 'REC-SE'); // other score error
        }

        // 5) Render
        Log::debug('record:render', $ctx);

        return view('public.scoring.record', [
            'uuid' => $uuid,
            'score' => $score,
            'league' => $league,
            'kioskMode' => $kioskMode,
            'kioskReturnTo' => $kioskReturnTo,
        ]);
    }

    public function summary(Request $request, string $uuid, int $scoreId)
    {
        $league = $this->leagueOr404($uuid);

        $kioskMode = (bool) $request->session()->get('kiosk_mode', false);
        $mode = $league->scoring_mode->value ?? $league->scoring_mode;
        $allowed = $kioskMode || in_array((string) $mode, ['personal_device', 'kiosk', 'tablet'], true);
        abort_unless($allowed, 404);

        // Scope by league_id
        $score = LeagueWeekScore::with(['ends' => fn ($q) => $q->orderBy('end_number')])
            ->where('league_id', $league->id)
            ->findOrFail($scoreId);

        $stats = $this->computeSummaryStats($score);

        return view('public.scoring.summary', array_merge([
            'league' => $league,
            'score' => $score,
        ], $stats));
    }

    protected function computeSummaryStats(LeagueWeekScore $score): array
    {
        $plannedEnds = (int) ($score->ends_planned ?? $score->ends->count());
        $arrowsPerEnd = (int) $score->arrows_per_end;
        $maxPerArrow = (int) $score->max_score;
        $xValue = (int) ($score->x_value ?? 10);

        $endsCompleted = 0;
        $arrowsEntered = 0;
        $xCount = 0;
        $totalScore = (int) ($score->total_score ?? 0);

        foreach ($score->ends as $e) {
            $hasAny = false;
            if (is_array($e->scores)) {
                foreach ($e->scores as $sv) {
                    if (! is_null($sv)) {
                        $arrowsEntered++;
                        $hasAny = true;

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
