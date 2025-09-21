<?php

// app/Http/Controllers/PublicCheckinController.php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueParticipant;
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

        $p = \App\Models\LeagueParticipant::where('league_id', $league->id)->findOrFail($participant);

        $weeks = \App\Models\LeagueWeek::where('league_id', $league->id)
            ->orderBy('week_number')
            ->get(['id', 'week_number', 'date']);

        // ✅ Use short letters, not human labels
        $letters = $league->lane_breakdown->letters();          // [] or ['A','B'] or ['A','B','C','D']
        $positionsPerLane = $league->lane_breakdown->positionsPerLane(); // 1,2,4

        $laneOptions = [];
        for ($i = 1; $i <= (int) $league->lanes_count; $i++) {
            if ($positionsPerLane === 1) {
                // Single: Lane 1, Lane 2, ...
                $code = (string) $i;
                $laneOptions[] = ['value' => $code, 'label' => "Lane {$code}"];
            } else {
                // AB / ABCD: Lane 1A, 1B, [1C, 1D], Lane 2A...
                foreach ($letters as $L) {
                    $code = $i.$L; // no hyphen, no descriptive text
                    $laneOptions[] = ['value' => $code, 'label' => "Lane {$code}"];
                }
            }
        }

        // make sure the view gets `$laneOptions`
        return response()->view('public.checkin.details', compact('league', 'p', 'weeks', 'laneOptions'));
    }

    public function detailsSubmit(Request $request, string $uuid, int $participant)
    {
        $league = $this->leagueOr404($uuid);
        $p = LeagueParticipant::where('league_id', $league->id)->findOrFail($participant);

        // Validate week_number is one of the league's weeks, and lane is a compact code like "5" or "5A"
        $request->validate([
            'week_number' => [
                'required', 'integer', 'between:1,'.$league->length_weeks,
                Rule::exists('league_weeks', 'week_number')->where('league_id', $league->id),
            ],
            'lane' => ['required', 'string', 'max:10'], // e.g. "5" or "5A"
        ]);

        // Parse lane into lane_number + lane_slot
        $laneCode = trim($request->input('lane'));     // "5" or "5A"
        $laneNumber = null;
        $laneSlot = 'single';                          // default for single-lane

        if (ctype_digit($laneCode)) {
            $laneNumber = (int) $laneCode;
            $laneSlot = 'single';
        } elseif (preg_match('/^(\d+)([A-D])$/i', $laneCode, $m)) {
            $laneNumber = (int) $m[1];
            $laneSlot = strtoupper($m[2]);          // "A" | "B" | "C" | "D"
        } else {
            return back()->withErrors(['lane' => 'Invalid lane selection.'])->withInput();
        }

        // Optional: sanity-check against league config
        $max = (int) $league->lanes_count;
        if ($laneNumber < 1 || $laneNumber > $max) {
            return back()->withErrors(['lane' => 'Lane number out of range.'])->withInput();
        }
        $allowedLetters = $league->lane_breakdown->letters(); // [] | ['A','B'] | ['A','B','C','D']
        if ($laneSlot !== 'single' && ! in_array($laneSlot, $allowedLetters, true)) {
            return back()->withErrors(['lane' => 'Lane slot not allowed for this league.'])->withInput();
        }

        // Create the check-in using the actual schema
        LeagueCheckin::create([
            'league_id' => $league->id,
            'participant_id' => $p->id,
            // If your table also has convenience columns:
            'participant_name' => $p->first_name.' '.$p->last_name ?? null,
            'participant_email' => $p->email ?? null,

            'week_number' => (int) $request->input('week_number'),
            'lane_number' => $laneNumber,
            'lane_slot' => $laneSlot, // 'single' or 'A'/'B'/'C'/'D'

            'checked_in_at' => now(),
        ]);

        return redirect()
            ->route('public.checkin.ok', ['uuid' => $uuid])
            ->with('ok_name', $p->first_name.' '.$p->last_name);
    }

    public function ok(string $uuid)
    {
        $league = $this->leagueOr404($uuid);
        $name = session('ok_name');

        return response()->view('public.checkin.ok', compact('league', 'name'));
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
