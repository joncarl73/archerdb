<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventLineTime;
use App\Models\EventScore;
use App\Models\League;
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
     * Step 1: Event CLS – line time → participant pick.
     *
     * For events:
     *   - First visit: show line time dropdown.
     *   - After selecting: show participants filtered by selected line time.
     *
     * League CLS will be wired in later.
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

        // League CLS flow will be wired in later; for now, nothing to do.
        return view('public.cls.participants', [
            'kind' => $kind,
            'owner' => $owner,
            'lineTimes' => collect(),
            'selectedLineTime' => null,
            'participants' => collect(),
        ]);
    }

    /**
     * Step 1 POST: participant selection submit.
     *
     * For events:
     *   - Validates chosen participant + line_time_id.
     *   - Creates / ensures an event_checkins row.
     *   - Redirects to lane step.
     *
     * League CLS will be wired in later.
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

        // League CLS integration will come later.
        abort(501, 'League CLS integration not implemented yet.');
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
        } else {
            abort(501, 'League CLS lane selection will be wired in later.');
        }

        return view('public.cls.lane', [
            'kind' => $kind,
            'owner' => $owner,
            'participant' => $participant,
            'lineTime' => $lineTime,
        ]);
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
        } else {
            abort(501, 'League CLS lane selection will be wired in later.');
        }

        return redirect()->route('public.cls.scoring.start', [
            'kind' => $kind,
            'uuid' => $uuid,
            'participant' => $participant->id,
        ]);
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

        // League path will be wired in later.
        abort(501, 'League CLS scoring will be wired in later.');
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
