<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueParticipant;
// If/when you add it:
// use App\Models\EventParticipant;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PublicCheckinController extends Controller
{
    // =========================================================================
    // LEAGUE FLOW
    // Routes (l/{uuid}):
    //   GET  /checkin                  -> participants
    //   POST /checkin                  -> participantsSubmit
    //   GET  /checkin/{participant}    -> details
    //   POST /checkin/{participant}    -> detailsSubmit
    //   GET  /checkin/ok/{checkin}     -> ok
    // =========================================================================

    public function participants(string $uuid)
    {
        $league = League::where('public_uuid', $uuid)->firstOrFail();
        $participants = $this->fetchLeagueRoster($league);

        return view('public.checkin.participants', [
            'context' => 'league',
            'league' => $league,
            'participants' => $participants, // [{id,name,email}]
        ]);
    }

    public function participantsSubmit(Request $request, string $uuid)
    {
        $league = League::where('public_uuid', $uuid)->firstOrFail();

        $participantId = (int) $request->input('participant_id');
        if (! $participantId) {
            return back()->withErrors(['participant_id' => 'Please select a participant.']);
        }

        return redirect()->route('public.checkin.details', [
            'uuid' => $uuid,
            'participant' => $participantId,
        ]);
    }

    public function details(string $uuid, int $participant)
    {
        $league = League::where('public_uuid', $uuid)->firstOrFail();

        // Blade renders week + lane/slot (personal-device).
        return view('public.checkin.details', [
            'league' => $league,
            'participantId' => $participant,
        ]);
    }

    public function detailsSubmit(Request $request, string $uuid, int $participant)
    {
        $league = League::where('public_uuid', $uuid)->firstOrFail();

        // Align to schema (lane_number / lane_slot)
        $data = $request->validate([
            'week_number' => ['required', 'integer', 'min:1'],
            'lane_number' => ['required', 'integer', 'min:1'],
            'lane_slot' => ['required', 'string', 'max:1'], // A/B/C/D
        ]);

        // Double-booking guard (same week + lane + slot)
        $taken = LeagueCheckin::where([
            'league_id' => $league->id,
            'week_number' => (int) $data['week_number'],
            'lane_number' => (int) $data['lane_number'],
            'lane_slot' => strtoupper($data['lane_slot']),
        ])->exists();

        if ($taken) {
            return back()->withErrors(['lane_slot' => 'That lane/slot is taken for the selected week.']);
        }

        $checkin = LeagueCheckin::create([
            'league_id' => $league->id,
            'week_number' => (int) $data['week_number'],
            'participant_id' => (int) $participant,
            'user_id' => Auth::id(),
            'lane_number' => (int) $data['lane_number'],
            'lane_slot' => strtoupper($data['lane_slot']),
        ]);

        return redirect()->route('public.checkin.ok', [
            'uuid' => $uuid,
            'checkin' => $checkin->id,
        ]);
    }

    public function ok(string $uuid, int $checkin)
    {
        $league = League::where('public_uuid', $uuid)->firstOrFail();
        $checkinM = LeagueCheckin::where('id', $checkin)
            ->where('league_id', $league->id)
            ->firstOrFail();

        return view('public.checkin.ok', [
            'league' => $league,
            'checkin' => $checkinM,
        ]);
    }

    // =========================================================================
    // EVENT FLOW
    // Routes (e/{uuid}):
    //   GET  /checkin                  -> participantsForEvent
    //   POST /checkin                  -> participantsForEventSubmit
    //   GET  /checkin/{participant}    -> detailsForEvent
    //   POST /checkin/{participant}    -> detailsForEventSubmit
    //   (no OK page in your routes; we go straight to scoring start)
    // =========================================================================

    public function participantsForEvent(string $uuid)
    {
        $event = Event::where('public_uuid', $uuid)->firstOrFail();
        $participants = $this->fetchEventRoster($event); // empty => free-form

        return view('public.checkin.participants', [
            'context' => 'event',
            'event' => $event,
            'participants' => $participants,
        ]);
    }

    public function participantsForEventSubmit(Request $request, string $uuid)
    {
        $event = Event::where('public_uuid', $uuid)->firstOrFail();

        // Either pick from roster or submit free-form
        $participantId = $request->filled('participant_id') ? (int) $request->input('participant_id') : null;

        if (! $participantId) {
            $data = $request->validate([
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['nullable', 'email', 'max:255'],
            ]);
            session([
                'event_checkin_freeform' => [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'] ?? null,
                ],
            ]);
            $participantId = 0; // synthetic free-form flag
        }

        return redirect()->route('public.event.checkin.details', [
            'uuid' => $uuid,
            'participant' => $participantId,
        ]);
    }

    public function detailsForEvent(string $uuid, int $participant)
    {
        $event = Event::where('public_uuid', $uuid)->firstOrFail();

        $lineTimes = $event->lineTimes()
            ->orderBy('line_date')
            ->orderBy('start_time')
            ->get(['id', 'line_date', 'start_time', 'end_time', 'capacity', 'notes']);

        $slots = $this->deriveSlotsFromEvent($event); // ['A'] | ['A','B'] | ['A','B','C','D']

        $freeform = $participant === 0 ? session('event_checkin_freeform', null) : null;

        return view('public.checkin.details-event', [
            'event' => $event,
            'participantId' => $participant,
            'lineTimes' => $lineTimes,
            'slots' => $slots,
            'freeform' => $freeform,
        ]);
    }

    public function detailsForEventSubmit(Request $request, string $uuid, int $participant)
    {
        $event = Event::where('public_uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'event_line_time_id' => ['required', 'integer', 'exists:event_line_times,id'],
            'lane_number' => ['required', 'integer', 'min:1'],
            'lane_slot' => ['required', 'string', 'max:1'], // A/B/C/D
        ]);

        // Prevent double-booking
        $taken = EventCheckin::where([
            'event_id' => $event->id,
            'event_line_time_id' => (int) $data['event_line_time_id'],
            'lane_number' => (int) $data['lane_number'],
            'lane_slot' => strtoupper($data['lane_slot']),
        ])->exists();

        if ($taken) {
            return back()->withErrors(['lane_slot' => 'That lane/slot is already taken for this line time.']);
        }

        $payload = [
            'event_id' => $event->id,
            'event_line_time_id' => (int) $data['event_line_time_id'],
            'participant_id' => $participant ?: null, // null when free-form
            'user_id' => Auth::id(),
            'lane_number' => (int) $data['lane_number'],
            'lane_slot' => strtoupper($data['lane_slot']),
        ];

        // If free-form, persist basic identity onto the row (if those columns exist)
        if ($participant === 0 && is_array(session('event_checkin_freeform'))) {
            $ff = session('event_checkin_freeform');
            foreach (['first_name', 'last_name', 'email'] as $k) {
                if (array_key_exists($k, $ff)) {
                    $payload[$k] = $ff[$k];
                }
            }
        }

        $checkin = EventCheckin::create($payload);

        if ($participant === 0) {
            session()->forget('event_checkin_freeform');
        }

        // No OK route for events in your routes: go straight to scoring-start
        return redirect()->route('public.event.scoring.start', ['checkin' => $checkin->id]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * League public check-in: fetch roster from LeagueParticipant.
     * Returns [{id,name,email}]
     */
    protected function fetchLeagueRoster(League $league)
    {
        return $league->participants()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'name' => trim(($p->first_name ?? '').' '.($p->last_name ?? '')) ?: 'Unknown',
                'email' => (string) ($p->email ?? ''),
            ]);
    }

    /**
     * Event public check-in roster.
     * If EventParticipant doesn’t exist yet, return empty so the blade shows free-form inputs.
     */
    protected function fetchEventRoster(Event $event)
    {
        // If you have EventParticipant, return that roster.
        if (class_exists(\App\Models\EventParticipant::class)) {
            return \App\Models\EventParticipant::query()
                ->where('event_id', $event->id)
                ->select(['id', 'first_name', 'last_name', 'email'])   // <- no "name" column
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn ($p) => [
                    'id' => (int) $p->id,
                    'name' => trim(($p->first_name ?? '').' '.($p->last_name ?? '')) ?: 'Unknown',
                    'email' => (string) ($p->email ?? ''),
                ]);
        }

        return collect(); // free-form path if no EventParticipant model
    }

    /**
     * Determine lane slots allowed by the event’s rules.
     */
    protected function deriveSlotsFromEvent(Event $event): array
    {
        if (method_exists($event, 'effectiveRules')) {
            $schema = $event->effectiveRules();
            $mode = data_get($schema, 'lane_breakdown', 'single');
        } else {
            $schema = $event->ruleset?->schema ?? [];
            $mode = data_get($schema, 'lane_breakdown', 'single');
        }

        return match ($mode) {
            'ab' => ['A', 'B'],
            'abcd' => ['A', 'B', 'C', 'D'],
            default => ['A'],
        };
    }
}
