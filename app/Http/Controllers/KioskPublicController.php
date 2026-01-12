<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventKioskSession;
use App\Models\KioskSession;
use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KioskPublicController extends Controller
{
    /**
     * Shared public kiosk landing board: /k/{token}
     *
     * - If the token belongs to a League KioskSession, show the league kiosk board.
     * - Otherwise, if it belongs to an EventKioskSession, show the event kiosk board.
     */
    public function landing(string $token)
    {
        // 1) Try league kiosk session first
        $session = KioskSession::query()
            ->with('league')
            ->where('token', $token)
            ->where('is_active', true)
            ->first();

        if ($session) {
            /** @var League $league */
            $league = $session->league;

            if (! $league) {
                abort(404);
            }

            // The kiosk session is bound to a specific week_number
            $week = LeagueWeek::query()
                ->where('league_id', $league->id)
                ->where('week_number', $session->week_number)
                ->firstOrFail();

            // Normalize participants array from the session
            $participantIds = is_array($session->participants)
                ? $session->participants
                : (json_decode((string) $session->participants, true) ?: []);

            $participantIds = array_values(array_unique(array_map('intval', $participantIds)));

            // Pull all check-ins for these participants for that week
            $checkins = LeagueCheckin::query()
                ->where('league_id', $league->id)
                ->where('week_number', $week->week_number)
                ->when(! empty($participantIds), fn ($q) => $q->whereIn('participant_id', $participantIds))
                ->orderBy('lane_number')
                ->orderBy('lane_slot')
                ->get();

            return view('public.kiosk.landing', [
                'league' => $league,
                'week' => $week,
                'session' => $session,
                'checkins' => $checkins,
            ]);
        }

        // 2) Fall back to event kiosk session
        $session = EventKioskSession::query()
            ->with(['event', 'lineTime'])
            ->where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        /** @var Event $event */
        $event = $session->event;

        if (! $event) {
            abort(404);
        }

        $lineTime = $session->lineTime;

        // Normalize participants from kiosk session
        $participantIds = is_array($session->participants)
            ? $session->participants
            : (json_decode((string) $session->participants, true) ?: []);

        $participantIds = array_values(array_unique(array_map('intval', $participantIds)));

        $checkinsQuery = EventCheckin::query()
            ->where('event_id', $event->id);

        if ($session->event_line_time_id) {
            $checkinsQuery->where('event_line_time_id', $session->event_line_time_id);
        }

        if (! empty($participantIds)) {
            $checkinsQuery->whereIn('participant_id', $participantIds);
        }

        // Use lane_number / lane_slot (matches EventCheckin model)
        $checkins = $checkinsQuery
            ->orderBy('lane_number')
            ->orderBy('lane_slot')
            ->get();

        return view('public.kiosk.event-landing', [
            'event' => $event,
            'lineTime' => $lineTime,
            'session' => $session,
            'checkins' => $checkins,
        ]);
    }

    /**
     * When an archer taps their name on the kiosk board:
     *   /k/{token}/score/{checkin}
     *
     * For leagues:
     *   - validate kiosk session + LeagueCheckin
     *   - set CLS kiosk flags + league check-in
     *   - hand off to CLS startScoring(kind='league')
     *
     * For events:
     *   - validate EventKioskSession + EventCheckin
     *   - set CLS kiosk flags
     *   - hand off to CLS startScoring(kind='event')
     */
    public function score(Request $request, string $token, int $checkinId): RedirectResponse
    {
        // 1) Try as a LEAGUE kiosk session
        $session = KioskSession::query()
            ->with('league')
            ->where('token', $token)
            ->where('is_active', true)
            ->first();

        if ($session) {
            /** @var League $league */
            $league = $session->league;

            if (! $league) {
                abort(404);
            }

            $checkin = LeagueCheckin::query()->findOrFail($checkinId);

            if ($checkin->league_id !== $league->id || (int) $checkin->week_number !== (int) $session->week_number) {
                abort(404);
            }

            // Optional: ensure participant is part of kiosk session
            $allowedIds = is_array($session->participants)
                ? $session->participants
                : (json_decode((string) $session->participants, true) ?: []);
            $allowedIds = array_map('intval', $allowedIds);

            if (! empty($allowedIds) && ! in_array((int) $checkin->participant_id, $allowedIds, true)) {
                abort(403, 'Participant is not part of this kiosk session.');
            }

            // Mark this as a CLS kiosk flow and store the league check-in
            $request->session()->put('cls_from_kiosk', true);
            $request->session()->put('cls_league_checkin_id', $checkin->id);
            $request->session()->put('kiosk_mode', true);
            $request->session()->put('kiosk_return_to', route('kiosk.landing', ['token' => $token]));

            // Hand off to CLS scoring start for LEAGUE
            return redirect()->route('public.cls.scoring.start', [
                'kind' => 'league',
                'uuid' => $league->public_uuid,
                'participant' => $checkin->participant_id,
            ]);
        }

        // 2) Otherwise treat as an EVENT kiosk session
        $eSession = EventKioskSession::query()
            ->with('event')
            ->where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        /** @var Event $event */
        $event = $eSession->event;

        if (! $event) {
            abort(404);
        }

        $checkin = EventCheckin::query()->findOrFail($checkinId);

        if ($checkin->event_id !== $event->id) {
            abort(404);
        }

        if ($eSession->event_line_time_id && (int) $checkin->event_line_time_id !== (int) $eSession->event_line_time_id) {
            abort(404);
        }

        // Optional: ensure participant is part of this kiosk session
        $allowedIds = is_array($eSession->participants)
            ? $eSession->participants
            : (json_decode((string) $eSession->participants, true) ?: []);
        $allowedIds = array_map('intval', $allowedIds);

        if (! empty($allowedIds) && ! in_array((int) $checkin->participant_id, $allowedIds, true)) {
            abort(403, 'Participant is not part of this kiosk session.');
        }

        // Mark this as a CLS kiosk flow for events
        $request->session()->put('cls_from_kiosk', true);
        $request->session()->put('kiosk_mode', true);
        $request->session()->put('kiosk_return_to', route('kiosk.landing', ['token' => $token]));

        // Hand off to CLS scoring start for EVENT
        return redirect()->route('public.cls.scoring.start', [
            'kind' => 'event',
            'uuid' => $event->public_uuid,
            'participant' => $checkin->participant_id,
        ]);
    }
}
