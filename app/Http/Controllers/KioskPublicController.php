<?php

namespace App\Http\Controllers;

use App\Models\KioskSession;
use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueParticipant;
use App\Models\LeagueWeek;
use App\Models\LeagueWeekScore;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
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

        $league = $session->league;

        $week = LeagueWeek::where('league_id', $league->id)
            ->where('week_number', $session->week_number)
            ->firstOrFail();

        $participantIds = is_array($session->participants)
            ? $session->participants
            : (json_decode((string) $session->participants, true) ?: []);
        $participantIds = array_values(array_unique(array_map('intval', $participantIds)));

        $checkins = LeagueCheckin::with('participant')
            ->where('league_id', $league->id)
            ->where('week_number', $session->week_number)
            ->when(count($participantIds) > 0, fn ($q) => $q->whereIn('participant_id', $participantIds))
            ->orderBy('lane_number')
            ->orderBy('lane_slot')
            ->get();

        $assignedNames = [];
        if (count($participantIds)) {
            $assignedNames = LeagueParticipant::where('league_id', $league->id)
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
        $league = League::findOrFail($checkin->league_id);

        // The week the archer is scoring FOR (could be makeup week)
        $checkinWeek = LeagueWeek::where('league_id', $league->id)
            ->where('week_number', $checkin->week_number)
            ->firstOrFail();

        // Create/load score for THAT (possibly past) week
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

        // Decide kiosk vs personal
        $allowKiosk = false;
        if ($session && $session->league_id === $league->id) {
            $sessionWeek = LeagueWeek::where('league_id', $league->id)
                ->where('week_number', $session->week_number)
                ->first();

            // Kiosk only if scoring the SAME week as the kiosk session AND that week is today.
            if ($sessionWeek && $checkinWeek->id === $sessionWeek->id && $this->isTodayWeek($league, $sessionWeek)) {
                $allowKiosk = true;
            }
        }

        if ($allowKiosk) {
            session()->put('kiosk_mode', true);
            session()->put('kiosk_return_to', route('kiosk.landing', ['token' => $token]));
        } else {
            session()->forget('kiosk_mode');
            session()->forget('kiosk_return_to');
        }

        return redirect()->route('public.scoring.record', [$league->public_uuid, $score->id]);
    }
}
