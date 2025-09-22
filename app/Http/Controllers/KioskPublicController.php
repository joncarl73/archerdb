<?php

// app/Http/Controllers/KioskPublicController.php

namespace App\Http\Controllers;

use App\Models\KioskSession;
use App\Models\LeagueCheckin;
use App\Models\LeagueWeek;
use App\Models\LeagueWeekScore;

class KioskPublicController extends Controller
{
    public function landing(string $token)
    {
        $session = KioskSession::where('token', $token)->where('is_active', true)->firstOrFail();
        $league = $session->league;

        // Find the exact LeagueWeek row for the session's week_number
        $week = LeagueWeek::where('league_id', $league->id)
            ->where('week_number', $session->week_number)->firstOrFail();

        // All check-ins that match that week and the selected lanes
        $checkins = LeagueCheckin::with('participant')
            ->where('league_id', $league->id)
            ->where('week_number', $session->week_number)
            ->where(function ($q) use ($session) {
                $q->whereIn(\DB::raw("CONCAT(lane_number, CASE WHEN lane_slot='single' THEN '' ELSE lane_slot END)"), $session->lanes);
            })
            ->orderBy('lane_number')->orderBy('lane_slot')
            ->get();

        return view('public.kiosk.landing', [
            'session' => $session,
            'league' => $league,
            'week' => $week,
            'checkins' => $checkins,
        ]);
    }

    // handoff to scoring (creates/loads LeagueWeekScore then jumps to record in kiosk mode)
    public function score(string $token, int $checkinId)
    {
        $session = KioskSession::where('token', $token)->where('is_active', true)->firstOrFail();
        $league = $session->league;

        $checkin = \App\Models\LeagueCheckin::with('participant')->findOrFail($checkinId);
        abort_unless($checkin->league_id === $league->id, 404);
        abort_unless($checkin->week_number === $session->week_number, 404);

        // find/create WeekScore exactly like your PublicScoringController@start
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

        // seed ends if needed (same as your start())
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

        // IMPORTANT: pass kiosk flag + return URL to the record page
        $returnTo = route('kiosk.landing', $token);

        return redirect()->route('public.scoring.record', [$league->public_uuid, $score->id])
            ->with(['kiosk_mode' => true, 'kiosk_return_to' => $returnTo]);
    }
}
