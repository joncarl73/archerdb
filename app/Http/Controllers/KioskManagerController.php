<?php

// app/Http/Controllers/KioskManagerController.php

namespace App\Http\Controllers;

use App\Models\KioskSession;
use App\Models\League;
use App\Models\LeagueWeek;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KioskManagerController extends Controller
{
    // ensure this route is protected (admin/corporate as you prefer)
    public function index(League $league)
    {
        $weeks = LeagueWeek::where('league_id', $league->id)->orderBy('week_number')->get(['id', 'week_number', 'date']);
        $sessions = KioskSession::where('league_id', $league->id)->latest()->get();

        // Build lane options exactly like your check-in page
        $letters = $league->lane_breakdown->letters();
        $positionsPerLane = $league->lane_breakdown->positionsPerLane();
        $laneOptions = [];
        for ($i = 1; $i <= (int) $league->lanes_count; $i++) {
            if ($positionsPerLane === 1) {
                $laneOptions[] = (string) $i;
            } else {
                foreach ($letters as $L) {
                    $laneOptions[] = $i.$L;
                }
            }
        }

        return view('manager.kiosk.index', compact('league', 'weeks', 'sessions', 'laneOptions'));
    }

    public function store(Request $request, League $league)
    {
        $data = $request->validate([
            'week_number' => ['required', 'integer', 'between:1,'.$league->length_weeks],
            'lanes' => ['required', 'array', 'min:1'],
            'lanes.*' => ['string', 'max:10'],
        ]);

        $session = KioskSession::create([
            'league_id' => $league->id,
            'week_number' => $data['week_number'],
            'lanes' => array_values($data['lanes']),
            'token' => Str::random(40),
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        return back()->with('ok', 'Kiosk session created.')->with('token', $session->token);
    }
}
