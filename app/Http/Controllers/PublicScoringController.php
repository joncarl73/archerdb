<?php

// app/Http/Controllers/PublicScoringController.php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventLineTime;
use App\Models\EventScore;
// EVENT models (new)
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
    // =========================================================================
    // --------------------------- LEAGUE FLOW (UNCHANGED) ----------------------
    // =========================================================================

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
        $week = LeagueWeek::query()
            ->where('league_id', $league->id)
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

    // =========================================================================
    // ---------------------------- EVENT FLOW (NEW) ----------------------------
    // =========================================================================
    //
    // Routes you outlined (examples):
    //   Route::get('e/{uuid}/start-scoring/{checkin}', [PublicScoringController::class, 'startEvent'])->name('public.event.scoring.start');
    //   Route::get('e/{uuid}/score/{score}', [PublicScoringController::class, 'recordEvent'])->name('public.event.scoring.record');
    //   Route::get('e/{uuid}/scoring/{score}/summary', [PublicScoringController::class, 'summaryEvent'])->name('public.event.scoring.summary');

    protected function eventOr404(string $uuid): Event
    {
        return Event::query()->where('public_uuid', $uuid)->firstOrFail();
    }

    /**
     * Start scoring for an EVENT check-in:
     * - Uses line time selected at check-in.
     * - Seeds EventScore + ends based on ruleset (ends_per_session, arrows_per_end, x_value).
     * - Allows PD if event->scoring_mode is 'personal_device' OR kiosk session present.
     */
    public function startEvent(Request $request, string $uuid, int $checkinId)
    {
        $event = $this->eventOr404($uuid);

        // PD override (same semantics as league)
        $forcePD = $request->boolean('pd');
        $mode = $event->scoring_mode->value ?? $event->scoring_mode;

        $kioskMode = (bool) $request->session()->get('kiosk_mode', false);
        $allowed = $kioskMode || $forcePD || (string) $mode === 'personal_device';
        abort_unless($allowed, 404);

        $checkin = EventCheckin::with('participant')->findOrFail($checkinId);
        abort_unless($checkin->event_id === $event->id, 404);

        $lineTime = EventLineTime::where('id', $checkin->event_line_time_id)
            ->where('event_id', $event->id)
            ->firstOrFail();

        // read ruleset-derived scoring defaults
        $rs = $this->resolveEventScoringDefaults($event);

        // find or create EventScore (scoped by event + line time + participant)
        $score = EventScore::firstOrCreate(
            [
                'event_id' => $event->id,
                'event_line_time_id' => $lineTime->id,
                'event_checkin_id' => $checkin->id,
                'participant_id' => $checkin->participant_id,
            ],
            [
                'arrows_per_end' => $rs['arrows_per_end'],
                'ends_planned' => $rs['ends_per_session'],
                'max_score' => $rs['max_per_arrow'],
                'x_value' => $rs['x_value'],
                'total_score' => 0,
            ]
        );

        // seed ends if not present
        $existing = $score->ends()->count();
        if ($existing < $score->ends_planned) {
            for ($n = $existing + 1; $n <= $score->ends_planned; $n++) {
                $score->ends()->create([
                    'end_number' => $n,
                    'scores' => array_fill(0, $score->arrows_per_end, null),
                    'end_score' => 0,
                    'x_count' => 0,
                ]);
            }
        }

        return redirect()->route('public.event.scoring.record', [$event->public_uuid, $score->id]);
    }

    /**
     * Render an EVENT score for recording (kiosk or PD).
     */
    public function recordEvent(Request $request, string $uuid, int $scoreId)
    {
        $rid = uniqid('erec_', false);
        $ctx = [
            'rid' => $rid,
            'uuid' => $uuid,
            'scoreId' => $scoreId,
            'route' => Route::currentRouteName(),
            'session_id' => $request->session()->getId(),
        ];

        try {
            $event = $this->eventOr404($uuid);
            $mode = $event->scoring_mode->value ?? $event->scoring_mode;
            $kioskMode = (bool) $request->session()->get('kiosk_mode', false);

            $allowed = $kioskMode || in_array((string) $mode, ['personal_device', 'kiosk', 'tablet'], true);
            if (! $allowed) {
                abort(404, 'E-REC-G1');
            }

            $score = EventScore::with(['ends' => fn ($q) => $q->orderBy('end_number')])
                ->where('event_id', $event->id)
                ->findOrFail($scoreId);

        } catch (ModelNotFoundException $e) {
            Log::warning('event.record.not_found', $ctx + ['ex' => $e->getMessage()]);
            abort(404, 'E-REC-SNF');
        } catch (Throwable $e) {
            Log::error('event.record.error', $ctx + ['ex' => $e->getMessage()]);
            abort(404, 'E-REC-SE');
        }

        return view('public.scoring.record', [
            'uuid' => $uuid,
            'score' => $score,
            'event' => $event,
            'kioskMode' => (bool) $request->session()->get('kiosk_mode', false),
            'kioskReturnTo' => $request->session()->get('kiosk_return_to'),
        ]);
    }

    /**
     * Summary page for EVENT scoring.
     */
    public function summaryEvent(Request $request, string $uuid, int $scoreId)
    {
        $event = $this->eventOr404($uuid);

        $kioskMode = (bool) $request->session()->get('kiosk_mode', false);
        $mode = $event->scoring_mode->value ?? $event->scoring_mode;
        $allowed = $kioskMode || in_array((string) $mode, ['personal_device', 'kiosk', 'tablet'], true);
        abort_unless($allowed, 404);

        $score = EventScore::with(['ends' => fn ($q) => $q->orderBy('end_number')])
            ->where('event_id', $event->id)
            ->findOrFail($scoreId);

        // Reuse the same computation; EventScore shares the same shape
        $stats = $this->computeSummaryStatsForEvent($score);

        // You can reuse public.scoring.summary if it’s generic, otherwise make a dedicated one.
        return view('public.scoring.summary', array_merge([
            'event' => $event,
            'score' => $score,
        ], $stats));
    }

    // =========================================================================
    // ----------------------------- HELPERS (EVENT) ----------------------------
    // =========================================================================

    /**
     * Read defaults from ruleset/effective rules:
     *  - ends_per_session (default 10)
     *  - arrows_per_end   (default 3)
     *  - x_value          (default 10)
     *  - max_per_arrow    (default 10)  // inferred from scoring_values if present
     */
    protected function resolveEventScoringDefaults(Event $event): array
    {
        $schema = [];
        if (method_exists($event, 'effectiveRules')) {
            $schema = $event->effectiveRules();
        } else {
            $schema = $event->ruleset?->schema ?? [];
        }

        // If ruleset table stores scoring_values/x_value columns, prefer them
        $ruleset = $event->ruleset ?? null;
        $scoringValues = is_array($ruleset?->scoring_values ?? null) ? $ruleset->scoring_values : (array) ($schema['scoring_values'] ?? []);
        $maxPerArrow = ! empty($scoringValues) ? max($scoringValues) : 10;

        return [
            'ends_per_session' => (int) ($schema['ends_per_session'] ?? $ruleset?->schema['ends_per_session'] ?? 10),
            'arrows_per_end' => (int) ($schema['arrows_per_end'] ?? $ruleset?->schema['arrows_per_end'] ?? 3),
            'x_value' => (int) ($ruleset?->x_value ?? $schema['x_value'] ?? 10),
            'max_per_arrow' => (int) $maxPerArrow,
        ];
    }

    /**
     * Same as computeSummaryStats but typed for EventScore.
     */
    protected function computeSummaryStatsForEvent(EventScore $score): array
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
