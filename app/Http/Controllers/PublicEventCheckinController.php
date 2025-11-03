<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Validation\ValidationException;

class PublicEventCheckinController extends Controller
{
    protected function findEventOr404(string $uuid): Event
    {
        return Event::query()->where('public_uuid', $uuid)->firstOrFail();
    }

    /**
     * GET e/{uuid}/checkin  → pick participant (no lanes for events)
     */
    public function participants(Request $request, string $uuid)
    {
        $event = $this->findEventOr404($uuid);

        // Expect an Event::participants() -> hasMany(EventParticipant::class)
        // Table has first_name/last_name/email (no "name" column).
        $participants = $event->participants()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'name' => trim(($p->first_name ?? '').' '.($p->last_name ?? '')) ?: 'Unknown',
                'email' => (string) ($p->email ?? ''),
            ]);

        return view('public.events.participants', compact('event', 'participants'));
    }

    /**
     * POST e/{uuid}/checkin → submit participant + mode (personal|kiosk)
     */
    public function submitParticipants(Request $request, string $uuid)
    {
        $event = $this->findEventOr404($uuid);

        $data = $request->validate([
            'participant_id' => ['required', 'integer'],
            'mode' => ['required', 'in:personal,kiosk'],
        ]);

        $participant = $event->participants()
            ->select(['id']) // minimal select
            ->whereKey($data['participant_id'])
            ->first();

        if (! $participant) {
            throw ValidationException::withMessages([
                'participant_id' => 'Please select a valid participant.',
            ]);
        }

        // Skip lane selection entirely for events.

        if ($data['mode'] === 'kiosk') {
            // Prefer explicit event-scoped kiosk wait route
            if (RouteFacade::has('public.events.scoring.kiosk-wait')) {
                return redirect()->route('public.events.scoring.kiosk-wait', [
                    'uuid' => $event->public_uuid,
                    'pid' => $participant->id,
                ]);
            }

            // Fallback: if you keep a generic kiosk route, adjust name/params here.
            return back()->withErrors([
                'mode' => 'Kiosk scoring route is not defined. Add route name public.events.scoring.kiosk-wait.',
            ]);
        }

        // Personal-device start (no lane picking)
        if (RouteFacade::has('public.events.scoring.personal-start')) {
            return redirect()->route('public.events.scoring.personal-start', [
                'uuid' => $event->public_uuid,
                'pid' => $participant->id,
            ]);
        }

        // Fallback: if you already have a start route that expects a checkin id,
        // you’ll need to adapt the flow to create a checkin first. For now, error.
        return back()->withErrors([
            'mode' => 'Personal scoring route is not defined. Add route name public.events.scoring.personal-start.',
        ]);
    }

    /**
     * GET e/{uuid}/scoring/personal-start?pid=123
     * Minimal hand-off page to your scoring UI (no lane selection).
     */
    public function personalStart(Request $request, string $uuid)
    {
        $event = $this->findEventOr404($uuid);
        $participantId = (int) $request->query('pid');

        // Ensure the pid belongs to this event
        $participant = $event->participants()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->findOrFail($participantId);

        // If your scoring UI needs a score/checkin row, create/mint it here.

        return view('public.events.scoring.personal-start', [
            'event' => $event,
            'participant' => $participant,
        ]);
    }

    /**
     * GET e/{uuid}/scoring/kiosk-wait?pid=123
     * Simple holding page indicating the archer will score on a kiosk.
     */
    public function kioskWait(Request $request, string $uuid)
    {
        $event = $this->findEventOr404($uuid);
        $participantId = (int) $request->query('pid');

        $participant = $event->participants()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->findOrFail($participantId);

        return view('public.events.scoring.kiosk-wait', [
            'event' => $event,
            'participant' => $participant,
        ]);
    }
}
