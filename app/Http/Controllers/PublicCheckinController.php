<?php

// app/Http/Controllers/PublicCheckinController.php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueParticipant;
use App\Models\LeagueWeek;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicCheckinController extends Controller
{
    protected function leagueOr404(string $uuid): League
    {
        return League::query()
            ->where('public_uuid', $uuid)
            ->firstOrFail();
    }

    public function participants(string $uuid)
    {
        $league = $this->leagueOr404($uuid);

        $participants = LeagueParticipant::query()
            ->where('league_id', $league->id)
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return response()->view('public.checkin.participants', compact('league', 'participants'));
    }

    public function participantsSubmit(Request $request, string $uuid)
    {
        $league = $this->leagueOr404($uuid);

        $data = $request->validate([
            'participant_id' => ['required', 'integer',
                Rule::exists('league_participants', 'id')->where('league_id', $league->id),
            ],
        ]);

        return redirect()->route('public.checkin.details', [
            'uuid' => $uuid,
            'participant' => $data['participant_id'],
        ]);
    }

    public function details(string $uuid, int $participant)
    {
        $league = $this->leagueOr404($uuid);

        $p = LeagueParticipant::where('league_id', $league->id)->findOrFail($participant);

        $weeks = LeagueWeek::where('league_id', $league->id)
            ->orderBy('week_number')
            ->get(['id', 'week_number', 'date']);

        // Short letters for lane breakdown
        $letters = $league->lane_breakdown->letters();                  // [] or ['A','B'] or ['A','B','C','D']
        $positionsPerLane = $league->lane_breakdown->positionsPerLane(); // 1,2,4

        $laneOptions = [];
        for ($i = 1; $i <= (int) $league->lanes_count; $i++) {
            if ($positionsPerLane === 1) {
                $code = (string) $i;
                $laneOptions[] = ['value' => $code, 'label' => "Lane {$code}"];
            } else {
                foreach ($letters as $L) {
                    $code = $i.$L; // e.g. 5A
                    $laneOptions[] = ['value' => $code, 'label' => "Lane {$code}"];
                }
            }
        }

        return response()->view('public.checkin.details', compact('league', 'p', 'weeks', 'laneOptions'));
    }

    public function detailsSubmit(Request $request, string $uuid, int $participant)
    {
        $league = $this->leagueOr404($uuid);
        $p = LeagueParticipant::where('league_id', $league->id)->findOrFail($participant);

        // Validate week_number is a valid league week and lane is "5" or "5A" style
        $request->validate([
            'week_number' => [
                'required', 'integer', 'between:1,'.$league->length_weeks,
                Rule::exists('league_weeks', 'week_number')->where('league_id', $league->id),
            ],
            'lane' => ['required', 'string', 'max:10'],
        ]);

        // Parse lane into number + slot
        $laneCode = trim($request->input('lane'));     // "5" or "5A"
        $laneNumber = null;
        $laneSlot = 'single';                          // default for single-lane

        if (ctype_digit($laneCode)) {
            $laneNumber = (int) $laneCode;
            $laneSlot = 'single';
        } elseif (preg_match('/^(\d+)([A-D])$/i', $laneCode, $m)) {
            $laneNumber = (int) $m[1];
            $laneSlot = strtoupper($m[2]);            // "A" | "B" | "C" | "D"
        } else {
            return back()->withErrors(['lane' => 'Invalid lane selection.'])->withInput();
        }

        // Sanity-check against league config
        $maxLanes = (int) $league->lanes_count;
        if ($laneNumber < 1 || $laneNumber > $maxLanes) {
            return back()->withErrors(['lane' => 'Lane number out of range.'])->withInput();
        }
        $allowedLetters = $league->lane_breakdown->letters(); // [] | ['A','B'] | ['A','B','C','D']
        if ($laneSlot !== 'single' && ! in_array($laneSlot, $allowedLetters, true)) {
            return back()->withErrors(['lane' => 'Lane slot not allowed for this league.'])->withInput();
        }

        $weekNumber = (int) $request->input('week_number');
        $laneLabel = $laneNumber.($laneSlot === 'single' ? '' : $laneSlot);

        // Already checked in for this week?
        $existing = LeagueCheckin::query()
            ->where('league_id', $league->id)
            ->where('participant_id', $p->id)
            ->where('week_number', $weekNumber)
            ->first();

        if ($existing) {
            // Do not mutate the existing record — just send them to OK with that checkin id
            return redirect()->route('public.checkin.ok', [
                'uuid' => $uuid,
                'checkin' => $existing->id,
            ]);
        }

        // New check-in
        $checkin = LeagueCheckin::create([
            'league_id' => $league->id,
            'participant_id' => $p->id,
            'participant_name' => trim($p->first_name.' '.$p->last_name),
            'participant_email' => $p->email ?: null,
            'week_number' => $weekNumber,
            'lane_number' => $laneNumber,
            'lane_slot' => $laneSlot,
            'checked_in_at' => now(),
        ]);

        return redirect()->route('public.checkin.ok', [
            'uuid' => $uuid,
            'checkin' => $checkin->id,
        ]);
    }

    /**
     * OK page — fully deterministic from URL/DB (no flash).
     *
     * Route-model binds {checkin} to LeagueCheckin; we also validate it belongs to {uuid}.
     */
    public function ok(string $uuid, LeagueCheckin $checkin)
    {
        $league = $this->leagueOr404($uuid);

        // Guard: ensure the checkin belongs to this league
        if ((int) $checkin->league_id !== (int) $league->id) {
            abort(404);
        }

        // Derive display fields
        $name = $checkin->participant_name ?: trim(($checkin->first_name ?? '').' '.($checkin->last_name ?? ''));
        $week = (int) $checkin->week_number;
        $lane = $checkin->lane_number.($checkin->lane_slot === 'single' ? '' : $checkin->lane_slot);
        $checkinId = (int) $checkin->id;

        // "Repeat" if someone already had a checkin for this week prior to this record's creation.
        // If you store a dedicated flag, swap this logic to use it.
        $repeat = false;
        if ($checkin->wasRecentlyCreated === false) {
            $repeat = true;
        }

        // Optional: pre-fetch the week row so Blade can render the scheduled date without another query
        $weekRow = LeagueWeek::where('league_id', $league->id)
            ->where('week_number', $week)
            ->first();

        return response()->view('public.checkin.ok', [
            'league' => $league,
            'name' => $name,
            'repeat' => $repeat,
            'week' => $week,
            'lane' => $lane,
            'checkinId' => $checkinId,
            'weekRow' => $weekRow,
        ]);
    }

    public function week(League $league, string $participant)
    {
        $weeks = $league->weeks()->orderBy('week_number')->get();
        $laneOptions = $league->laneOptions();

        return view('public.leagues.checkin.week', [
            'league' => $league,
            'participant' => $participant,
            'weeks' => $weeks,
            'laneOptions' => $laneOptions,
        ]);
    }
}
