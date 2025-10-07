<?php

// app/Http/Controllers/KioskManagerController.php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\KioskSession;
use App\Models\League;
use App\Models\LeagueWeek;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class KioskManagerController extends Controller
{
    /**
     * Resolve the Event linked to this League (if any).
     */
    private function eventForLeague(League $league): ?Event
    {
        // Use loaded relation if present; otherwise fetch once.
        if (isset($league->event)) {
            return $league->event;
        }

        return $league->event()->first();
    }

    // ensure this route is protected (admin/corporate as you prefer)
    public function index(League $league)
    {
        $event = $this->eventForLeague($league);

        // If an Event exists and it's personal-only, block kiosk management
        if ($event && $event->scoring_mode === 'personal') {
            abort(403, 'Kiosk is disabled for this event.');
        }

        // Weeks: prefer event_id if the column exists and an event is linked; else fall back to league_id
        $weeksQuery = LeagueWeek::query()->orderBy('week_number');
        if ($event && Schema::hasColumn('league_weeks', 'event_id')) {
            $weeksQuery->where('event_id', $event->id);
        } else {
            $weeksQuery->where('league_id', $league->id);
        }
        $weeks = $weeksQuery->get(['id', 'week_number', 'date']);

        // Sessions: prefer event_id if available; else league_id
        $sessionsQuery = KioskSession::query()->latest();
        if ($event && Schema::hasColumn('kiosk_sessions', 'event_id')) {
            $sessionsQuery->where('event_id', $event->id);
        } else {
            $sessionsQuery->where('league_id', $league->id);
        }
        $sessions = $sessionsQuery->get();

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

        // You can pass $event to the view if you want to conditionally show UI
        return view('manager.kiosk.index', [
            'league' => $league,
            'weeks' => $weeks,
            'sessions' => $sessions,
            'laneOptions' => $laneOptions,
            'event' => $event,
        ]);
    }

    private function maxPeriodsForContext(?\App\Models\Event $event, ?\App\Models\League $league): int
    {
        $q = \App\Models\LeagueWeek::query()->forContext($event, $league);

        return max(1, (int) $q->count());
    }

    public function store(Request $request, League $league)
    {
        $event = $this->eventForLeague($league);

        // If an Event exists and it's personal-only, block kiosk creation
        if ($event && $event->scoring_mode === 'personal') {
            abort(403, 'Kiosk is disabled for this event.');
        }

        $max = $this->maxPeriodsForContext($event, $league); // from your Step 4 resolver
        $data = $request->validate([
            'week_number' => ['required', 'integer', "between:1,{$max}"],
            'lanes' => ['required', 'array', 'min:1'],
            'lanes.*' => ['string', 'max:10'],
        ]);

        // Prepare payload; include event_id if the column exists
        $payload = [
            'league_id' => $league->id,             // keep legacy pointer for leagues
            'week_number' => $data['week_number'],
            'lanes' => array_values($data['lanes']),
            'token' => Str::random(40),
            'is_active' => true,
            'created_by' => auth()->id(),
        ];

        if ($event && Schema::hasColumn('kiosk_sessions', 'event_id')) {
            $payload['event_id'] = $event->id;
        }

        $session = KioskSession::create($payload);

        return back()->with('ok', 'Kiosk session created.')->with('token', $session->token);
    }
}
