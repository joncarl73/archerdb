<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventLineTime;
use App\Models\EventScore;
use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueWeek;
use App\Models\LeagueWeekScore;
use Illuminate\Http\Request;

class PublicClsController extends Controller
{
    /**
     * Resolve owning model (Event or League) by public UUID.
     *
     * @return array{0:string,1:\App\Models\Event|\App\Models\League}
     */
    protected function resolveOwnerOr404(string $kind, string $uuid): array
    {
        $kind = strtolower($kind);

        if ($kind === 'event') {
            $owner = Event::query()
                ->where('public_uuid', $uuid)
                ->firstOrFail();

            return ['event', $owner];
        }

        if ($kind === 'league') {
            $owner = League::query()
                ->where('public_uuid', $uuid)
                ->firstOrFail();

            return ['league', $owner];
        }

        abort(404);
    }

    /**
     * Step 1: CLS participants.
     *
     * Events:
     *   - First visit: show line time dropdown.
     *   - After selecting: show participants filtered by selected line time,
     *     excluding already-checked-in archers.
     *
     * Leagues:
     *   - Show a simple participant pick list (no line times here).
     *     (We do NOT filter by prior check-ins so archers can check in for multiple weeks.)
     */
    public function participants(Request $request, string $kind, string $uuid)
    {
        [$kind, $owner] = $this->resolveOwnerOr404($kind, $uuid);

        if ($kind === 'event') {
            /** @var Event $owner */
            // Load line times oldest → newest
            $owner->loadMissing(['lineTimes' => function ($q) {
                $q->orderBy('line_date')->orderBy('start_time');
            }]);

            $lineTimes = $owner->lineTimes;

            $selectedLineTimeId = $request->integer('line_time_id') ?: null;
            $selectedLineTime = null;
            $participants = collect();

            if ($selectedLineTimeId) {
                $selectedLineTime = $lineTimes->firstWhere('id', $selectedLineTimeId);

                if ($selectedLineTime) {
                    // Only participants assigned to this line time
                    // AND who have not already checked in for this event.
                    $participants = $owner->participants()
                        ->where('line_time_id', $selectedLineTime->id)
                        ->whereDoesntHave('checkins', function ($q) use ($owner) {
                            $q->where('event_id', $owner->id);
                        })
                        ->orderBy('last_name')
                        ->orderBy('first_name')
                        ->get();
                }
            }

            return view('public.cls.participants', [
                'kind' => $kind,
                'owner' => $owner,
                'lineTimes' => $lineTimes,
                'selectedLineTime' => $selectedLineTime,
                'participants' => $participants,
            ]);
        }

        if ($kind === 'league') {
            /** @var League $owner */
            $league = $owner;

            // League CLS: dropdown of all roster participants (no per-league check-in filter)
            // Because check-ins are per-week, we allow the same archer to check in for multiple weeks.
            $participants = $league->participants()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            return view('public.cls.participants', [
                'kind' => $kind,
                'owner' => $league,
                'lineTimes' => collect(),
                'selectedLineTime' => null,
                'participants' => $participants,
            ]);
        }

        abort(404);
    }

    /**
     * Step 1 POST: participant selection submit.
     *
     * Events:
     *   - Validates chosen participant + line_time_id.
     *   - Creates / ensures an event_checkins row.
     *   - Redirects to lane step.
     *
     * Leagues:
     *   - Validates chosen participant.
     *   - Redirects to league CLS lane step (week + lane selection).
     */
    public function participantsSubmit(Request $request, string $kind, string $uuid)
    {
        [$kind, $owner] = $this->resolveOwnerOr404($kind, $uuid);

        $data = $request->validate([
            'participant_id' => ['required', 'integer'],
            'line_time_id' => ['nullable', 'integer'],
        ]);

        if ($kind === 'event') {
            /** @var Event $event */
            $event = $owner;

            // Roster participant
            $participant = $event->participants()
                ->findOrFail($data['participant_id']);

            // Effective line time: form value (if present) or participant record
            $lineTimeId = $data['line_time_id'] ?? $participant->line_time_id;

            // Safety: ensure the participant is actually assigned to that line time
            if ($lineTimeId && (int) $participant->line_time_id !== (int) $lineTimeId) {
                abort(400, 'Participant is not assigned to that line time.');
            }

            // Snapshot name/email for reporting
            $name = trim(($participant->first_name ?? '').' '.($participant->last_name ?? ''));

            // Normalize lane fields from the roster
            $laneNumber = $participant->assigned_lane;
            $laneSlot = $participant->assigned_slot
                ? strtoupper($participant->assigned_slot)
                : 'single';

            // Keep event_checkins in sync with the roster lane + line time
            EventCheckin::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'participant_id' => $participant->id,
                ],
                [
                    'event_line_time_id' => $lineTimeId,
                    'lane_number' => $laneNumber,
                    'lane_slot' => $laneSlot,

                    // Identity snapshot (mirrors league_checkins semantics)
                    'participant_name' => $name,
                    'participant_email' => $participant->email,
                    'first_name' => $participant->first_name,
                    'last_name' => $participant->last_name,
                    'email' => $participant->email,

                    'checked_in_at' => now(),
                ]
            );

            return redirect()->route('public.cls.lane', [
                'kind' => $kind,
                'uuid' => $uuid,
                'participant' => $participant->id,
            ]);
        }

        if ($kind === 'league') {
            /** @var League $league */
            $league = $owner;

            // Roster participant
            $participant = $league->participants()
                ->findOrFail($data['participant_id']);

            // For league CLS, week + lane will be chosen on the next step.
            return redirect()->route('public.cls.lane', [
                'kind' => $kind,
                'uuid' => $uuid,
                'participant' => $participant->id,
            ]);
        }

        abort(404);
    }

    /**
     * Step 2: Lane / line time page.
     *
     * Events:
     *   - Lanes are assigned in the back office and are read-only here.
     *
     * Leagues:
     *   - Archer picks week + lane, with taken lanes hidden.
     */
    public function lane(Request $request, string $kind, string $uuid, int $participant)
    {
        [$kind, $owner] = $this->resolveOwnerOr404($kind, $uuid);

        if ($kind === 'event') {
            /** @var Event $owner */
            $participant = $owner->participants()->findOrFail($participant);

            $lineTime = $participant->line_time_id
                ? EventLineTime::query()->find($participant->line_time_id)
                : null;

            return view('public.cls.lane', [
                'kind' => $kind,
                'owner' => $owner,
                'participant' => $participant,
                'lineTime' => $lineTime,
                // League-only fields left null/empty
                'weeks' => collect(),
                'laneOptions' => [],
                'takenLanesByWeek' => [],
            ]);
        }

        if ($kind === 'league') {
            /** @var League $owner */
            $league = $owner;
            $participant = $league->participants()->findOrFail($participant);

            // All weeks for this league
            $weeks = $league->weeks()
                ->orderBy('week_number')
                ->get();

            // Use League::laneOptions() so we respect lane_breakdown
            $rawOptions = $league->laneOptions();
            $laneOptions = [];

            foreach ($rawOptions as $code => $label) {
                // Normalize to "Lane 1 (A)" style when there is a slot letter
                if (preg_match('/^(\d+)([A-D])$/', $code, $m)) {
                    $laneOptions[$code] = 'Lane '.$m[1].' ('.$m[2].')';
                } else {
                    $laneOptions[$code] = $label; // e.g. "Lane 1" for single lanes
                }
            }

            // Build [week_number => ['1A', '1B', '2', ...]] of already-taken lanes
            $checkins = LeagueCheckin::query()
                ->where('league_id', $league->id)
                ->get(['week_number', 'lane_number', 'lane_slot']);

            $takenLanesByWeek = [];

            foreach ($checkins as $c) {
                $slot = $c->lane_slot && strtolower($c->lane_slot) !== 'single'
                    ? strtoupper($c->lane_slot)
                    : '';
                $code = $c->lane_number.$slot; // e.g. "3A" or "5"

                $takenLanesByWeek[$c->week_number][] = $code;
            }

            return view('public.cls.lane', [
                'kind' => $kind,
                'owner' => $league,
                'participant' => $participant,
                'lineTime' => null,
                'weeks' => $weeks,
                'laneOptions' => $laneOptions,
                'takenLanesByWeek' => $takenLanesByWeek,
            ]);
        }

        abort(404);
    }

    /**
     * Step 2 POST: confirm lane & continue to scoring.
     *
     * Events:
     *   - Lane is read-only here; just proceed to scoring.
     *
     * Leagues:
     *   - Persist week + lane selection to league_checkins,
     *     then hand off to CLS scoring start.
     */
    public function laneSubmit(Request $request, string $kind, string $uuid, int $participant)
    {
        [$kind, $owner] = $this->resolveOwnerOr404($kind, $uuid);

        if ($kind === 'event') {
            /** @var Event $owner */
            $participant = $owner->participants()->findOrFail($participant);
            // NOTE: lane is read-only for events here; no updates occur.

            return redirect()->route('public.cls.scoring.start', [
                'kind' => $kind,
                'uuid' => $uuid,
                'participant' => $participant->id,
            ]);
        }

        if ($kind === 'league') {
            /** @var League $owner */
            $league = $owner;
            $participant = $league->participants()->findOrFail($participant);

            $data = $request->validate([
                'week_number' => ['required', 'integer', 'min:1'],
                'lane_code' => ['required', 'string'],
            ]);

            // Resolve week from league + week_number
            $week = LeagueWeek::query()
                ->where('league_id', $league->id)
                ->where('week_number', $data['week_number'])
                ->firstOrFail();

            // Decode lane_code into lane_number + lane_slot
            $code = strtoupper(trim($data['lane_code']));
            if (! preg_match('/^(\d+)([A-D])?$/', $code, $m)) {
                return back()->withErrors(['lane_code' => 'Invalid lane selection.']);
            }

            $laneNumber = (int) $m[1];
            $laneSlot = $m[2] ?? 'single'; // use 'single' when no letter

            // Prevent double booking: same league + week + lane + slot
            $taken = LeagueCheckin::query()
                ->where('league_id', $league->id)
                ->where('week_number', $week->week_number)
                ->where('lane_number', $laneNumber)
                ->where('lane_slot', $laneSlot)
                ->exists();

            if ($taken) {
                return back()->withErrors([
                    'lane_code' => 'That lane is already taken for the selected week.',
                ])->withInput();
            }

            // Snapshot name/email similar to event_checkins / legacy league_checkins
            $name = trim(($participant->first_name ?? '').' '.($participant->last_name ?? ''));

            $checkin = LeagueCheckin::create([
                'league_id' => $league->id,
                'week_number' => $week->week_number,
                'participant_id' => $participant->id,
                'lane_number' => $laneNumber,
                'lane_slot' => $laneSlot,
                'participant_name' => $name,
                'first_name' => $participant->first_name,
                'last_name' => $participant->last_name,
                'email' => $participant->email,
                'checked_in_at' => now(),
            ]);

            // Hand off to CLS scoring start, passing checkin id for league via session
            return redirect()->route('public.cls.scoring.start', [
                'kind' => $kind,
                'uuid' => $uuid,
                'participant' => $participant->id,
            ])->with('cls_league_checkin_id', $checkin->id);
        }

        abort(404);
    }

    /**
     * Step 3: scoring start.
     *
     * Events:
     *   - Enforce tablet/kiosk vs personal-device rules.
     *   - Create/find EventScore row and refresh its scoring config from the ruleset
     *     so the keypad scale always matches the current ruleset.
     *
     * Leagues:
     *   - Enforce tablet/kiosk vs personal-device rules.
     *   - Create/find a LeagueWeekScore row and seed ends (legacy league semantics),
     *     then route into CLS record() using the legacy league scoring design.
     */
    public function startScoring(Request $request, string $kind, string $uuid, int $participant)
    {
        [$kind, $owner] = $this->resolveOwnerOr404($kind, $uuid);

        //
        // EVENTS (ruleset-driven CLS scoring)
        //
        if ($kind === 'event') {
            /** @var Event $owner */
            $event = $owner;
            $participantModel = $event->participants()->findOrFail($participant);

            // If this event is configured for tablet/kiosk scoring,
            // do NOT show the scoring UI on the archer's *own* device.
            // But if we’re coming from a kiosk, bypass this.
            $isFromKiosk = (bool) $request->session()->pull('cls_from_kiosk', false);

            if ($event->scoring_mode === Event::SCORING_TABLET && ! $isFromKiosk) {
                $name = trim(($participantModel->first_name ?? '').' '.($participantModel->last_name ?? ''));

                return view('public.cls.wait-for-tablet', [
                    'kind' => $kind,
                    'owner' => $event,
                    'participantName' => $name ?: 'Archer',
                ]);
            }

            // Personal-device flow
            $lineTimeId = $participantModel->line_time_id;
            if (! $lineTimeId) {
                abort(400, 'This participant does not have an assigned line time yet.');
            }

            $ruleset = $event->ruleset;
            $overrides = $event->rulesetOverride?->overrides ?? [];

            // Pull scoring settings, preferring overrides when present.
            $arrowsPerEnd = (int) ($overrides['arrows_per_end'] ?? $ruleset->arrows_per_end ?? 3);
            $endsPlanned = (int) ($overrides['ends_per_session'] ?? $ruleset->ends_per_session ?? 10);
            $scoringValues = $overrides['scoring_values'] ?? $ruleset->scoring_values ?? null;
            $xValue = $overrides['x_value'] ?? $ruleset->x_value ?? null;
            $scoringSystem = (string) ($overrides['scoring_system'] ?? $ruleset->scoring_system ?? '10');

            if (is_array($scoringValues) && ! empty($scoringValues)) {
                $maxScore = max(array_map('intval', $scoringValues));
            } else {
                $maxScore = 10;
            }

            // One score row per event + line_time + participant.
            $score = EventScore::firstOrCreate(
                [
                    'event_id' => $event->id,
                    'event_line_time_id' => $lineTimeId,
                    'event_participant_id' => $participantModel->id,
                ]
            );

            // Always refresh from the ruleset so keypad follows the current scale.
            $score->fill([
                'arrows_per_end' => $arrowsPerEnd,
                'ends_planned' => $endsPlanned,
                'scoring_system' => $scoringSystem,
                'scoring_values' => $scoringValues,
                'x_value' => $xValue,
                'max_score' => $maxScore,
            ]);
            $score->save();

            return redirect()->route('public.cls.scoring.record', [
                'kind' => $kind,
                'uuid' => $uuid,
                'score' => $score->id,
            ]);
        }

        //
        // LEAGUES (CLS wired to legacy league scoring design)
        //
        if ($kind === 'league') {
            /** @var League $league */
            $league = $owner;
            $participantModel = $league->participants()->findOrFail($participant);

            // Normalize scoring_mode to a string ("personal_device", "tablet", etc.)
            $mode = is_string($league->scoring_mode)
                ? $league->scoring_mode
                : ($league->scoring_mode->value ?? $league->scoring_mode);

            // If this league is tablet/kiosk mode, tell the archer they'll receive a tablet
            // on their *own* device. Kiosk-origin flows bypass this.
            $isFromKiosk = (bool) $request->session()->pull('cls_from_kiosk', false);

            if ($mode !== 'personal_device' && ! $isFromKiosk) {
                $name = trim(($participantModel->first_name ?? '').' '.($participantModel->last_name ?? ''));

                return view('public.cls.wait-for-tablet', [
                    'kind' => $kind,
                    'owner' => $league,
                    'participantName' => $name ?: 'Archer',
                ]);
            }

            // PERSONAL-DEVICE LEAGUE FLOW

            // Prefer the fresh check-in id passed from laneSubmit
            $checkinId = (int) $request->session()->pull('cls_league_checkin_id', 0);

            if (! $checkinId) {
                // Fallback: latest check-in for this participant in this league
                $checkinId = LeagueCheckin::query()
                    ->where('league_id', $league->id)
                    ->where('participant_id', $participantModel->id)
                    ->latest('id')
                    ->value('id');
            }

            if (! $checkinId) {
                abort(404, 'CLS-L1: No league check-in found for this archer.');
            }

            $checkin = LeagueCheckin::query()->findOrFail($checkinId);

            // Determine which week this is for that league.
            $week = LeagueWeek::query()
                ->where('league_id', $league->id)
                ->where('week_number', $checkin->week_number)
                ->firstOrFail();

            // League scoring does NOT use rulesets – it’s driven by the league fields
            // (arrows_per_end, ends_per_day, x_ring_value, max_per_arrow).
            $score = LeagueWeekScore::firstOrCreate(
                [
                    'league_id' => $league->id,
                    'league_participant_id' => $participantModel->id,
                    'league_week_id' => $week->id,
                ],
                [
                    'arrows_per_end' => $league->arrows_per_end,
                    'ends_planned' => $league->ends_per_day,
                    'x_value' => $league->x_ring_value,
                    'max_score' => $league->max_per_arrow,
                ]
            );

            // Seed ends if needed (same behavior as legacy league scoring).
            if ($score->ends()->count() < $score->ends_planned) {
                $existingNumbers = $score->ends()
                    ->orderBy('end_number')
                    ->pluck('end_number')
                    ->all();

                for ($i = 1; $i <= $score->ends_planned; $i++) {
                    if (in_array($i, $existingNumbers, true)) {
                        continue;
                    }

                    $score->ends()->create([
                        'end_number' => $i,
                        'scores' => [],
                        'total' => 0,
                        'x_count' => 0,
                    ]);
                }
            }

            // Route into CLS record() with the legacy league scoring design.
            return redirect()->route('public.cls.scoring.record', [
                'kind' => 'league',
                'uuid' => $uuid,
                'score' => $score->id,
            ]);
        }

        abort(404);
    }

    /**
     * CLS scoring grid endpoint — wraps the Livewire scoring component.
     *
     * Events:
     *   - Uses the CLS scoring UI (public.cls.scoring-record).
     *
     * Leagues:
     *   - Reuses the legacy league scoring UI (public.scoring.record),
     *     but under CLS routes.
     */
    public function record(string $kind, string $uuid, int $score)
    {
        [$kind, $owner] = $this->resolveOwnerOr404($kind, $uuid);

        //
        // EVENTS → CLS scoring UI (new component)
        //
        if ($kind === 'event') {
            /** @var Event $owner */
            $scoreModel = EventScore::query()
                ->with(['event', 'participant'])
                ->findOrFail($score);

            if ($scoreModel->event_id !== $owner->id) {
                abort(404, 'CLS-E3');
            }

            // Prefer kiosk flags from session if present
            $session = request()->session();
            $sessionKioskMode = $session->get('kiosk_mode');
            $sessionKioskReturnTo = $session->get('kiosk_return_to');

            $kioskMode = $sessionKioskMode ?? ($owner->scoring_mode === Event::SCORING_TABLET);
            $kioskReturnTo = $sessionKioskReturnTo ?? null;

            return view('public.cls.scoring-record', [
                'kind' => $kind,
                'owner' => $owner,
                'score' => $scoreModel,
                'kioskMode' => $kioskMode,
                'kioskReturnTo' => $kioskReturnTo,
            ]);
        }

        //
        // LEAGUES → legacy league scoring UI, but via CLS route
        //
        if ($kind === 'league') {
            /** @var League $owner */
            $scoreModel = LeagueWeekScore::query()
                ->with(['league', 'participant'])
                ->findOrFail($score);

            if ($scoreModel->league_id !== $owner->id) {
                abort(404, 'CLS-L3');
            }

            $mode = is_string($owner->scoring_mode)
                ? $owner->scoring_mode
                : ($owner->scoring_mode->value ?? $owner->scoring_mode);

            // Prefer kiosk flags from session if present (e.g., kiosk board flow)
            $session = request()->session();
            $sessionKioskMode = $session->get('kiosk_mode');
            $sessionKioskReturnTo = $session->get('kiosk_return_to');

            $kioskMode = $sessionKioskMode ?? ($mode !== 'personal_device');
            $kioskReturnTo = $sessionKioskReturnTo ?? null;

            // Re-use the existing league scoring view + Livewire component.
            return view('public.cls.scoring-record', [
                'kind' => $kind,
                'owner' => $owner,
                'uuid' => $uuid,
                'score' => $scoreModel,
                'kioskMode' => $kioskMode,
                'kioskReturnTo' => $kioskReturnTo,
            ]);

        }

        abort(404);
    }

    /**
     * CLS scoring summary endpoint.
     *
     * Events:
     *   - Uses CLS summary UI (public.cls.scoring-summary).
     *
     * Leagues:
     *   - Reuses the legacy league summary UI (public.scoring.summary),
     *     but via the CLS route, so the whole flow is under /cls.
     */
    public function summary(string $kind, string $uuid, int $score)
    {
        [$kind, $owner] = $this->resolveOwnerOr404($kind, $uuid);

        //
        // EVENTS (ruleset-driven CLS scoring)
        //
        if ($kind === 'event') {
            /** @var Event $owner */
            $event = $owner;

            $scoreModel = EventScore::query()
                ->with(['event', 'participant', 'ends'])
                ->findOrFail($score);

            if ($scoreModel->event_id !== $event->id) {
                abort(404, 'CLS-E4');
            }

            return view('public.cls.scoring-summary', [
                'kind' => $kind,
                'owner' => $event,
                'score' => $scoreModel,
            ]);
        }

        //
        // LEAGUES (CLS summary for legacy league scoring design)
        //
        if ($kind === 'league') {
            /** @var League $owner */
            $league = $owner;

            $scoreModel = LeagueWeekScore::query()
                ->with(['league', 'participant', 'week', 'ends'])
                ->findOrFail($score);

            if ($scoreModel->league_id !== $league->id) {
                abort(404, 'CLS-L4');
            }

            return view('public.cls.scoring-summary', [
                'kind' => $kind,
                'owner' => $league,
                'score' => $scoreModel,
            ]);
        }

        abort(404);
    }
}
