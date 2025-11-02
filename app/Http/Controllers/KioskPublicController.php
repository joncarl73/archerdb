<?php

namespace App\Http\Controllers;

use App\Models\Event;             // League kiosk
use App\Models\EventCheckin;
use App\Models\EventKioskSession;
use App\Models\EventLineTime;
use App\Models\KioskSession;
use App\Models\League;
use App\Models\LeagueCheckin;                    // Event support
use App\Models\LeagueParticipant;
use App\Models\LeagueWeek;
use App\Models\LeagueWeekScore;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KioskPublicController extends Controller
{
    /**
     * League helper: Is the given league week scheduled for "today"?
     */
    private function isTodayWeek(League $league, LeagueWeek $week): bool
    {
        $tz = config('app.timezone');
        $today = Carbon::now($tz)->toDateString();
        $weekDate = Carbon::parse($week->date, $tz)->toDateString();

        return $weekDate === $today;
    }

    /**
     * Event helper: Is the given event line time scheduled for "today"?
     * Adjust 'starts_at' to your column name if different (e.g., 'start_at' or 'date').
     */
    private function isTodayLineTime(Event $event, EventLineTime $line): bool
    {
        $tz = config('app.timezone');
        $today = Carbon::now($tz)->toDateString();

        // If you store only a date, change to ->toDateString() on that date column directly.
        $lineDate = Carbon::parse($line->starts_at ?? $line->start_at ?? $line->date, $tz)->toDateString();

        return $lineDate === $today;
    }

    /**
     * /k/{token} — kiosk landing (league OR event)
     */
    public function landing(Request $request, string $token): RedirectResponse|View
    {
        // Try league kiosk (back-compat) first
        $leagueSession = KioskSession::where('token', $token)
            ->where('is_active', true)
            ->first();

        if ($leagueSession) {
            $league = $leagueSession->league;

            $week = LeagueWeek::where('league_id', $league->id)
                ->where('week_number', $leagueSession->week_number)
                ->firstOrFail();

            $participantIds = is_array($leagueSession->participants)
                ? $leagueSession->participants
                : (json_decode((string) $leagueSession->participants, true) ?: []);
            $participantIds = array_values(array_unique(array_map('intval', $participantIds)));

            $checkins = LeagueCheckin::with('participant')
                ->where('league_id', $league->id)
                ->where('week_number', $leagueSession->week_number)
                ->when(count($participantIds) > 0, fn ($q) => $q->whereIn('participant_id', $participantIds))
                ->orderBy('lane_number')->orderBy('lane_slot')
                ->get();

            $assignedNames = [];
            if (count($participantIds)) {
                $assignedNames = LeagueParticipant::where('league_id', $league->id)
                    ->whereIn('id', $participantIds)
                    ->orderBy('last_name')->orderBy('first_name')
                    ->get(['first_name', 'last_name'])
                    ->map(fn ($p) => trim(($p->first_name ?? '').' '.($p->last_name ?? '')))
                    ->all();
            }

            // Stamp kiosk flags used by PublicScoringController
            $request->session()->put('kiosk_mode', true);
            $request->session()->put('kiosk_return_to', url()->current());

            return view('public.kiosk.landing', [
                'mode' => 'league',
                'session' => $leagueSession,
                'league' => $league,
                'week' => $week,
                'checkins' => $checkins,
                'assignedNames' => $assignedNames,
            ]);
        }

        // Otherwise, try event kiosk
        $eventSession = EventKioskSession::where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $eventSession) {
            return redirect()->route('home')
                ->with('toast', ['type' => 'warning', 'message' => 'That kiosk session has ended.']);
        }

        $event = $eventSession->event;
        $line = $eventSession->lineTime;

        $participantIds = is_array($eventSession->participants)
            ? $eventSession->participants
            : (json_decode((string) $eventSession->participants, true) ?: []);
        $participantIds = array_values(array_unique(array_map('intval', $participantIds)));

        // Pull event check-ins *for that line time* and limited to assigned participants
        $checkins = EventCheckin::query()
            ->where('event_id', $event->id)
            ->where('event_line_time_id', $eventSession->event_line_time_id)
            ->when(count($participantIds) > 0, fn ($q) => $q->whereIn('participant_id', $participantIds))
            ->orderBy('lane')->orderBy('slot')
            ->get();

        // For events, use names straight from check-ins (no extra query required)
        $assignedNames = $checkins->map(fn ($c) => (string) ($c->participant_name ?? ''))->filter()->values()->all();

        // Stamp kiosk flags for Event PD/kiosk flow
        $request->session()->put('kiosk_mode', true);
        $request->session()->put('kiosk_return_to', url()->current());

        return view('public.kiosk.landing', [
            'mode' => 'event',
            'session' => $eventSession,
            'event' => $event,
            'lineTime' => $line,
            'checkins' => $checkins,
            'assignedNames' => $assignedNames,
        ]);
    }

    /**
     * /k/{token}/score/{checkin}
     * - League: unchanged behavior (kept exactly as you had it).
     * - Event : mirrors the logic — kiosk only if the check-in belongs to the same line time as the session AND that line time is today.
     *           Otherwise clear kiosk flags and fall back to personal-device flow.
     */
    public function score(Request $request, string $token, int $checkinId): RedirectResponse
    {
        // Try league first
        $leagueSession = KioskSession::where('token', $token)
            ->where('is_active', true)
            ->first();

        if ($leagueSession) {
            // LEAGUE FLOW (unchanged)
            $checkin = LeagueCheckin::with('participant')->findOrFail($checkinId);
            $league = League::findOrFail($checkin->league_id);

            $checkinWeek = LeagueWeek::where('league_id', $league->id)
                ->where('week_number', $checkin->week_number)
                ->firstOrFail();

            $score = LeagueWeekScore::firstOrCreate(
                [
                    'league_id' => $league->id,
                    'league_week_id' => $checkinWeek->id,
                    'league_participant_id' => $checkin->participant_id,
                ],
                [
                    'arrows_per_end' => (int) ($league->arrows_per_end ?? 3),
                    'ends_planned' => (int) ($league->ends_per_day ?? 10),
                    'max_score' => 10,
                    'x_value' => (int) ($league->x_ring_value ?? 10),
                ]
            );

            $allowKiosk = false;
            if ($leagueSession->league_id === $league->id) {
                $sessionWeek = LeagueWeek::where('league_id', $league->id)
                    ->where('week_number', $leagueSession->week_number)
                    ->first();

                if ($sessionWeek && $checkinWeek->id === $sessionWeek->id && $this->isTodayWeek($league, $sessionWeek)) {
                    $allowKiosk = true;
                }
            }

            if ($allowKiosk) {
                $request->session()->put('kiosk_mode', true);
                $request->session()->put('kiosk_return_to', route('kiosk.landing', ['token' => $token]));
            } else {
                $request->session()->forget('kiosk_mode');
                $request->session()->forget('kiosk_return_to');
            }

            return redirect()->route('public.scoring.record', [$league->public_uuid, $score->id]);
        }

        // EVENT FLOW
        $eventSession = EventKioskSession::where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        $checkin = EventCheckin::findOrFail($checkinId);
        $event = Event::findOrFail($checkin->event_id);
        $line = EventLineTime::where('event_id', $event->id)->findOrFail($checkin->event_line_time_id);

        // Kiosk allowed only if the same line time AND that line time is today
        $allowKiosk = (
            (int) $eventSession->event_id === (int) $event->id
            && (int) $eventSession->event_line_time_id === (int) $line->id
            && $this->isTodayLineTime($event, $line)
        );

        if ($allowKiosk) {
            $request->session()->put('kiosk_mode', true);
            $request->session()->put('kiosk_return_to', route('kiosk.landing', ['token' => $token]));
        } else {
            $request->session()->forget('kiosk_mode');
            $request->session()->forget('kiosk_return_to');
        }

        // Hand off to the Event PD/kiosk entry point (creates/fetches the event score)
        // Route name from earlier steps: public.event.scoring.start (uuid, checkin)
        return redirect()->route('public.event.scoring.start', [
            'uuid' => $event->public_uuid,
            'checkin' => $checkinId,
        ]);
    }
}
