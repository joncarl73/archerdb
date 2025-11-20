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
     * GET e/{uuid}/checkin → pick participant (events: no lane picking)
     */
    public function participants(Request $request, string $uuid)
    {
        $event = $this->findEventOr404($uuid);

        // IMPORTANT: return Eloquent models (no mapping to arrays)
        $participants = $event->participants()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('public.events.participants', [
            'event' => $event,
            'participants' => $participants,
        ]);
    }

    /**
     * POST e/{uuid}/checkin → submit participant (mode auto-picked from event)
     *
     * We no longer require a 'mode' field. We infer it from $event->scoring_mode:
     *   - 'kiosk'   → public.events.scoring.kiosk-wait
     *   - otherwise → public.events.scoring.personal-start
     */
    public function submitParticipants(Request $request, string $uuid)
    {
        $event = $this->findEventOr404($uuid);

        $data = $request->validate([
            'participant_id' => ['required', 'integer'],
        ]);

        $participant = $event->participants()
            ->select(['id'])
            ->whereKey($data['participant_id'])
            ->first();

        if (! $participant) {
            throw ValidationException::withMessages([
                'participant_id' => 'Please select a valid participant.',
            ]);
        }

        // Decide scoring mode from event setting (fallback to 'personal')
        // Expecting something like: 'kiosk' | 'personal' | null
        $mode = (string) ($event->scoring_mode ?? 'personal');
        $mode = strtolower($mode) === 'kiosk' ? 'kiosk' : 'personal';

        if ($mode === 'kiosk') {
            if (RouteFacade::has('public.events.scoring.kiosk-wait')) {
                return redirect()->route('public.events.scoring.kiosk-wait', [
                    'uuid' => $event->public_uuid,
                    'pid' => $participant->id,
                ]);
            }

            return back()->withErrors([
                'mode' => 'Kiosk scoring route is not defined. Add route name public.events.scoring.kiosk-wait.',
            ]);
        }

        // Personal device
        if (RouteFacade::has('public.events.scoring.personal-start')) {
            return redirect()->route('public.events.scoring.personal-start', [
                'uuid' => $event->public_uuid,
                'pid' => $participant->id,
            ]);
        }

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

        $participant = $event->participants()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->findOrFail($participantId);

        // If needed, create a checkin/score session here.
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
