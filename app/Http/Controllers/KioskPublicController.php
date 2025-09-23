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
    public function score(string $token, int $checkinId)
    {
        $session = KioskSession::where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        $league = $session->league;

        $checkin = LeagueCheckin::with('participant')->findOrFail($checkinId);

        abort_unless($checkin->league_id === $league->id, 404);
        abort_unless($checkin->week_number === $session->week_number, 404);

        // Extra guard: ensure this archer is actually assigned to this kiosk session
        $participantIds = is_array($session->participants)
            ? $session->participants
            : (json_decode((string) $session->participants, true) ?: []);
        $participantIds = array_values(array_unique(array_map('intval', $participantIds)));

        if (! in_array((int) $checkin->participant_id, $participantIds, true)) {
            abort(404); // not part of this kiosk assignment
        }

        // Find/create WeekScore (same defaults as personal-device start)
        $week = LeagueWeek::where('league_id', $league->id)
            ->where('week_number', $session->week_number)
            ->firstOrFail();

        $score = LeagueWeekScore::firstOrCreate(
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

        // Seed ends if needed
        $existing = $score->ends()->count();
        if ($existing < $score->ends_planned) {
            for ($i = $existing + 1; $i <= $score->ends_planned; $i++) {
                $score->ends()->create([
                    'end_number' => $i,
                    'scores' => array_fill(0, $score->arrows_per_end, null),
                    'end_score' => 0,
                    'x_count' => 0,
                ]);
            }
        }

        // Pass kiosk flags to the record page so it returns to kiosk after “Done”
        $returnTo = route('kiosk.landing', $token);

        return redirect()
            ->route('public.scoring.record', [$league->public_uuid, $score->id])
            ->with(['kiosk_mode' => true, 'kiosk_return_to' => $returnTo]);
    }
}
