<?php

// app/Http/Controllers/KioskPublicController.php

namespace App\Http\Controllers;

use App\Models\KioskSession;
use App\Models\LeagueCheckin;
use App\Models\LeagueParticipant;
use App\Models\LeagueWeek;
use App\Models\LeagueWeekScore;

class KioskPublicController extends Controller
{
    public function landing(string $token)
    {
        $session = KioskSession::where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        $league = $session->league;

        // Exact LeagueWeek row for the session's week_number
        $week = LeagueWeek::where('league_id', $league->id)
            ->where('week_number', $session->week_number)
            ->firstOrFail();

        // Normalize participants array (handle raw JSON string or null)
        $participantIds = is_array($session->participants)
            ? $session->participants
            : (json_decode((string) $session->participants, true) ?: []);
        $participantIds = array_values(array_unique(array_map('intval', $participantIds)));

        // Only assigned + checked-in archers for this week
        $checkins = LeagueCheckin::with('participant')
            ->where('league_id', $league->id)
            ->where('week_number', $session->week_number)
            ->when(count($participantIds) > 0, fn ($q) => $q->whereIn('participant_id', $participantIds))
            ->orderBy('lane_number')
            ->orderBy('lane_slot')
            ->get();

        // Optional: names for header chips
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

        return view('public.kiosk.landing', [
            'session' => $session,
            'league' => $league,
            'week' => $week,
            'checkins' => $checkins,
            'assignedNames' => $assignedNames, // optional chips in header
        ]);
    }

    // Handoff to scoring (creates/loads LeagueWeekScore then jumps to record in kiosk mode)
    // app/Http/Controllers/KioskPublicController.php

    public function score(string $token, int $checkinId)
    {
        $session = KioskSession::where('token', $token)->where('is_active', true)->firstOrFail();
        $league = $session->league;

        $checkin = \App\Models\LeagueCheckin::with('participant')->findOrFail($checkinId);
        abort_unless($checkin->league_id === $league->id, 404);
        abort_unless($checkin->week_number === $session->week_number, 404);

        $week = \App\Models\LeagueWeek::where('league_id', $league->id)
            ->where('week_number', $session->week_number)->firstOrFail();

        $score = \App\Models\LeagueWeekScore::firstOrCreate(
            [
                'league_id' => $league->id,
                'league_week_id' => $week->id,
                'league_participant_id' => $checkin->participant_id,
            ],
            [
                'arrows_per_end' => (int) ($league->arrows_per_end ?? 3),
                'ends_planned' => (int) ($league->ends_per_day ?? 10),
                'max_score' => 10,
                'x_value' => (int) ($league->x_ring_value ?? 10),
            ]
        );

        // Seed ends if needed (unchanged) …

        // Persist kiosk flags so refresh doesn't drop kiosk mode
        $returnTo = route('kiosk.landing', $token);
        session()->put('kiosk_mode', true);
        session()->put('kiosk_return_to', $returnTo);

        return redirect()->route('public.scoring.record', [$league->public_uuid, $score->id]);
    }
}
