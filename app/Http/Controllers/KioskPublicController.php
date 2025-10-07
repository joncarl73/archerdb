<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\KioskSession;
use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueParticipant;
use App\Models\LeagueWeek;
use App\Models\LeagueWeekScore;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KioskPublicController extends Controller
{
    /**
     * Is the given league week the one scheduled for "today"?
     * Uses app timezone; swap for $league->timezone if you store one.
     */
    private function isTodayWeek(League $league, LeagueWeek $week): bool
    {
        $tz = config('app.timezone');
        $today = Carbon::now($tz)->toDateString();
        $weekDate = Carbon::parse($week->date, $tz)->toDateString();

        return $weekDate === $today;
    }

    /**
     * /k/{token} — lane board / landing
     */
    public function landing(string $token): RedirectResponse|View
    {
        $session = KioskSession::where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $session) {
            return redirect()->route('home')
                ->with('toast', ['type' => 'warning', 'message' => 'That kiosk session has ended.']);
        }

        // Prefer event if linked; fall back to session->league
        $event = null;
        $league = $session->league;
        if (Schema::hasColumn('kiosk_sessions', 'event_id') && $session->event_id) {
            $event = Event::find($session->event_id);
            if (! $league && $event && $event->league) {
                $league = $event->league;
            }
        }

        // If an Event exists and is personal-only, block kiosk landing
        if ($event && $event->scoring_mode === 'personal') {
            return redirect()->route('home')
                ->with('toast', ['type' => 'warning', 'message' => 'Kiosk is disabled for this event.']);
        }

        if (! $league) {
            return redirect()->route('home')
                ->with('toast', ['type' => 'warning', 'message' => 'This kiosk session is not attached to a league.']);
        }

        // Find the active "week" for this session (prefer event_id if present)
        $weekQuery = LeagueWeek::query()
            ->where('week_number', $session->week_number);

        if ($event && Schema::hasColumn('league_weeks', 'event_id')) {
            $weekQuery->where('event_id', $event->id);
        } else {
            $weekQuery->where('league_id', $league->id);
        }

        $week = $weekQuery->firstOrFail();

        // Participants assigned to this kiosk session (IDs, deduped)
        $participantIds = is_array($session->participants)
            ? $session->participants
            : (json_decode((string) $session->participants, true) ?: []);
        $participantIds = array_values(array_unique(array_map('intval', $participantIds)));

        // Check-ins for this week (prefer event_id if present)
        $checkinsQuery = LeagueCheckin::with('participant')
            ->where('week_number', $session->week_number);

        if ($event && Schema::hasColumn('league_checkins', 'event_id')) {
            $checkinsQuery->where('event_id', $event->id);
        } else {
            $checkinsQuery->where('league_id', $league->id);
        }

        if (count($participantIds) > 0) {
            $checkinsQuery->whereIn('participant_id', $participantIds);
        }

        $checkins = $checkinsQuery
            ->orderBy('lane_number')
            ->orderBy('lane_slot')
            ->get();

        // Pretty list of assigned names (prefer event scope if available)
        $namesQuery = LeagueParticipant::query();
        if ($event && Schema::hasColumn('league_participants', 'event_id')) {
            $namesQuery->where('event_id', $event->id);
        } else {
            $namesQuery->where('league_id', $league->id);
        }

        $assignedNames = [];
        if (count($participantIds)) {
            $assignedNames = $namesQuery
                ->whereIn('id', $participantIds)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['first_name', 'last_name'])
                ->map(fn ($p) => trim(($p->first_name ?? '').' '.($p->last_name ?? '')))
                ->all();
        }

        return view('public.kiosk.landing', compact('session', 'league', 'week', 'checkins', 'assignedNames'));
    }

    /**
     * /k/{token}/score/{checkin}
     * - Enable kiosk mode ONLY if:
     *   (a) kiosk session is valid,
     *   (b) the check-in’s week == kiosk session week,
     *   (c) that kiosk session week is TODAY.
     * - Otherwise, clear kiosk flags and behave like personal scoring.
     */
    public function score(string $token, int $checkinId): RedirectResponse
    {
        $session = KioskSession::where('token', $token)
            ->where('is_active', true)
            ->first();

        // Load the check-in (needed in both paths)
        $checkin = LeagueCheckin::with('participant')->findOrFail($checkinId);

        // Resolve league (from checkin, then session, then event->league)
        $league = null;
        if ($checkin->league_id) {
            $league = League::find($checkin->league_id);
        }
        $event = null;
        if (Schema::hasColumn('kiosk_sessions', 'event_id') && $session && $session->event_id) {
            $event = Event::find($session->event_id);
        }
        if (! $league && $session && $session->league) {
            $league = $session->league;
        }
        if (! $league && ! $event && Schema::hasColumn('league_checkins', 'event_id') && $checkin->event_id) {
            $event = Event::find($checkin->event_id);
        }
        if (! $league && $event && $event->league) {
            $league = $event->league;
        }

        if (! $league) {
            return redirect()->route('home')
                ->with('toast', ['type' => 'warning', 'message' => 'Unable to resolve league context for scoring.']);
        }

        // The week the archer is scoring FOR (could be makeup week). Prefer event scope if present.
        $checkinWeekQuery = LeagueWeek::query()->where('week_number', $checkin->week_number);
        if ($event && Schema::hasColumn('league_weeks', 'event_id')) {
            $checkinWeekQuery->where('event_id', $event->id);
        } else {
            $checkinWeekQuery->where('league_id', $league->id);
        }
        $checkinWeek = $checkinWeekQuery->firstOrFail();

        // Create/load score for THAT (possibly past) week
        $where = [
            'league_id' => $league->id,
            'league_week_id' => $checkinWeek->id,
            'league_participant_id' => $checkin->participant_id,
        ];
        if ($event && Schema::hasColumn('league_week_scores', 'event_id')) {
            $where['event_id'] = $event->id;
        }

        $arrowsPerEnd = (int) ($league->arrows_per_end ?? 3);
        $endsPlanned = (int) ($league->ends_per_day ?? 10);
        $xValue = (int) ($league->x_ring_value ?? 10);

        $score = LeagueWeekScore::firstOrCreate(
            $where,
            [
                'arrows_per_end' => $arrowsPerEnd,
                'ends_planned' => $endsPlanned,
                'max_score' => 10,
                'x_value' => $xValue,
            ]
        );

        // Decide kiosk vs personal (match session week + must be today). Prefer event scope when available.
        $allowKiosk = false;
        if ($session) {
            // If event exists, ensure kiosk isn't forbidden at the event level
            if ($event && $event->scoring_mode === 'personal') {
                $allowKiosk = false;
            } else {
                $sessionWeekQuery = LeagueWeek::query()->where('week_number', $session->week_number);
                if ($event && Schema::hasColumn('league_weeks', 'event_id')) {
                    $sessionWeekQuery->where('event_id', $event->id);
                } else {
                    $sessionWeekQuery->where('league_id', $league->id);
                }
                $sessionWeek = $sessionWeekQuery->first();

                // Kiosk only if scoring the SAME week as the kiosk session AND that week is today.
                if ($sessionWeek && $checkinWeek->id === $sessionWeek->id && $this->isTodayWeek($league, $sessionWeek)) {
                    $allowKiosk = true;
                }
            }
        }

        if ($allowKiosk) {
            session()->put('kiosk_mode', true);
            session()->put('kiosk_return_to', route('kiosk.landing', ['token' => $token]));
        } else {
            session()->forget('kiosk_mode');
            session()->forget('kiosk_return_to');
        }

        // Existing scoring record route expects a league UUID
        return redirect()->route('public.scoring.record', [$league->public_uuid, $score->id]);
    }
}
