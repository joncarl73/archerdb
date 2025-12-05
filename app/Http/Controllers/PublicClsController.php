<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventLineTime;
use App\Models\EventScore;
use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueWeek;
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
            // League CLS: simple archer pick list (no line times here)
            $participants = $owner->participants()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            return view('public.cls.participants', [
                'kind' => $kind,
                'owner' => $owner,
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
     * For events, lanes are assigned in the back office and are read-only here.
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
            ]);
        }

        if ($kind === 'league') {
            /** @var League $owner */
            $league = $owner;
            $participant = $league->participants()->findOrFail($participant);

            // All weeks for this league (legacy uses this as the week dropdown)
            $weeks = $league->weeks()
                ->orderBy('week_number')
                ->get();

            // Use League::laneOptions() so we respect lane_breakdown (single, ab, abcd).
            // Then reformat labels so that suffixed codes show as "Lane 1 (A)" etc.
            $rawOptions = $league->laneOptions(); // e.g. ['1' => 'Lane 1', '1A' => 'Lane 1A', ...]
            $laneOptions = [];

            foreach ($rawOptions as $code => $label) {
                // If code looks like "10A", "3B", etc., convert to "Lane 10 (A)"
                if (preg_match('/^(\d+)([A-D])$/', $code, $m)) {
                    $laneOptions[$code] = 'Lane '.$m[1].' ('.$m[2].')';
                } else {
                    // Single-lane codes remain as-is: "Lane 1", "Lane 2", ...
                    $laneOptions[$code] = $label;
                }
            }

            return view('public.cls.lane', [
                'kind' => $kind,
                'owner' => $league,
                'participant' => $participant,
                'lineTime' => null,
                'weeks' => $weeks,
                'laneOptions' => $laneOptions,
            ]);
        }

        abort(404);
    }

    /**
     * Step 2 POST: confirm lane & continue to scoring.
     *
     * For events we do not change lane here (organizer-assigned only).
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

            // Hand off to CLS scoring start, passing checkin id for league
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
     * Creates or finds an EventScore (League integration later) and redirects
     * to the CLS scoring grid.
     */
    public function startScoring(Request $request, string $kind, string $uuid, int $participant)
    {
        [$kind, $owner] = $this->resolveOwnerOr404($kind, $uuid);

        if ($kind === 'event') {
            /** @var Event $owner */
            $participantModel = $owner->participants()->findOrFail($participant);

            $lineTimeId = $participantModel->line_time_id;
            if (! $lineTimeId) {
                abort(400, 'This participant does not have an assigned line time yet.');
            }

            $ruleset = $owner->ruleset;
            $overrides = $owner->rulesetOverride?->overrides ?? [];

            // Pull scoring settings, preferring overrides when present.
            $arrowsPerEnd = (int) ($overrides['arrows_per_end'] ?? $ruleset->arrows_per_end ?? 3);
            $endsPlanned = (int) ($overrides['ends_per_session'] ?? $ruleset->ends_per_session ?? 10);
            $scoringValues = $overrides['scoring_values'] ?? $ruleset->scoring_values ?? null;
            $xValue = $overrides['x_value'] ?? $ruleset->x_value ?? null;
            $scoringSystem = (string) ($overrides['scoring_system'] ?? '10');

            if (is_array($scoringValues) && ! empty($scoringValues)) {
                $maxScore = max(array_map('intval', $scoringValues));
            } else {
                $maxScore = 10;
            }

            // One score row per event + line_time + participant.
            $score = EventScore::firstOrCreate(
                [
                    'event_id' => $owner->id,
                    'event_line_time_id' => $lineTimeId,
                    'event_participant_id' => $participantModel->id,
                ],
                [
                    'arrows_per_end' => $arrowsPerEnd,
                    'ends_planned' => $endsPlanned,
                    'scoring_system' => $scoringSystem,
                    'scoring_values' => $scoringValues,
                    'x_value' => $xValue,
                    'max_score' => $maxScore,
                ]
            );

            return redirect()->route('public.cls.scoring.record', [
                'kind' => $kind,
                'uuid' => $uuid,
                'score' => $score->id,
            ]);
        }

        if ($kind === 'league') {
            /** @var League $owner */
            $league = $owner;

            // Prefer the fresh check-in id passed from laneSubmit
            $checkinId = (int) $request->session()->pull('cls_league_checkin_id', 0);

            if (! $checkinId) {
                // Fallback: latest check-in for this participant in this league
                $checkinId = LeagueCheckin::query()
                    ->where('league_id', $league->id)
                    ->where('participant_id', $participant)
                    ->latest('id')
                    ->value('id');
            }

            if (! $checkinId) {
                abort(404, 'CLS-L1: No league check-in found for this archer.');
            }

            // Hand off to the existing personal-device scoring flow for leagues.
            // This will:
            //   - Validate league + mode
            //   - Create/find LeagueWeekScore
            //   - Seed ends
            //   - Redirect to public.scoring.record
            return redirect()->route('public.scoring.start', [
                $league->public_uuid,
                $checkinId,
            ]);
        }

        abort(404);
    }

    /**
     * CLS scoring grid endpoint — wraps the Livewire scoring component.
     */
    public function record(string $kind, string $uuid, int $score)
    {
        [$kind, $owner] = $this->resolveOwnerOr404($kind, $uuid);

        if ($kind === 'event') {
            /** @var Event $owner */
            $scoreModel = EventScore::query()
                ->with(['event', 'participant'])
                ->findOrFail($score);

            if ($scoreModel->event_id !== $owner->id) {
                abort(404, 'CLS-E3');
            }

            $kioskMode = ($owner->scoring_mode === Event::SCORING_TABLET);

            return view('public.cls.scoring-record', [
                'kind' => $kind,
                'owner' => $owner,
                'score' => $scoreModel,
                'kioskMode' => $kioskMode,
                'kioskReturnTo' => null, // we’ll wire this when we unify kiosk boards
            ]);
        }

        abort(501, 'League CLS scoring will be wired in later.');
    }

    /**
     * CLS scoring summary endpoint.
     */
    public function summary(string $kind, string $uuid, int $score)
    {
        [$kind, $owner] = $this->resolveOwnerOr404($kind, $uuid);

        if ($kind === 'event') {
            /** @var Event $owner */
            $scoreModel = EventScore::query()
                ->with(['event', 'participant', 'ends'])
                ->findOrFail($score);

            if ($scoreModel->event_id !== $owner->id) {
                abort(404, 'CLS-E4');
            }

            return view('public.cls.scoring-summary', [
                'kind' => $kind,
                'owner' => $owner,
                'score' => $scoreModel,
            ]);
        }

        abort(501, 'League CLS scoring will be wired in later.');
    }
}
