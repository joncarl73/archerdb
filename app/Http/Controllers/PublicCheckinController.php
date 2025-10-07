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

        $event = $league->event ?? null;

        $weeks = \App\Models\LeagueWeek::query()
            ->forContext($event, $league)
            ->orderBy('week_number')
            ->get(['id', 'week_number', 'date']);

        // ✅ Use short letters, not human labels
        $letters = $league->lane_breakdown->letters();                 // [] or ['A','B'] or ['A','B','C','D']
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

        // Validate week_number is a valid league week and lane is "5" or "5A" style
        $request->validate([
            'week_number' => [
                'required', 'integer', 'between:1,'.$league->length_weeks,
                Rule::exists('league_weeks', 'week_number')->where(function ($q) use ($league) {
                    $event = $league->event ?? null;
                    if (\Illuminate\Support\Facades\Schema::hasColumn('league_weeks', 'event_id') && $event) {
                        $q->where('event_id', $event->id);
                    } else {
                        $q->where('league_id', $league->id);
                    }
                }),

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

        // ✅ Check if already checked in for this week
        $existing = LeagueCheckin::query()
            ->where('league_id', $league->id)
            ->where('participant_id', $p->id)
            ->where('week_number', $weekNumber)
            ->first();

        if ($existing) {
            // 🚫 Do NOT change lane, week, or timestamps — keep original record as-is
            $origLaneLabel = $existing->lane_number.($existing->lane_slot === 'single' ? '' : $existing->lane_slot);

            return redirect()
                ->route('public.checkin.ok', ['uuid' => $uuid])
                ->with([
                    'ok_name' => $p->first_name.' '.$p->last_name,
                    'ok_repeat' => true,
                    'ok_week' => $existing->week_number,
                    'ok_lane' => $origLaneLabel,
                    'ok_checkin_id' => $existing->id, // still handy for "Start scoring"
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

        return redirect()
            ->route('public.checkin.ok', ['uuid' => $uuid])
            ->with([
                'ok_name' => $p->first_name.' '.$p->last_name,
                'ok_repeat' => false,
                'ok_week' => $weekNumber,
                'ok_lane' => $laneLabel,
                'ok_checkin_id' => $checkin->id, // ← NEW: needed for Start scoring
            ]);
    }

    public function ok(string $uuid)
    {
        $league = $this->leagueOr404($uuid);

        $name = session('ok_name');                // string|null
        $repeat = (bool) session('ok_repeat', false);
        $week = session('ok_week');                // int|null
        $lane = session('ok_lane');                // string like "5" or "5A"
        $checkinId = session('ok_checkin_id');          // int|null

        // If someone hits this page directly without a session, show a gentle fallback
        return response()->view('public.checkin.ok', [
            'league' => $league,
            'name' => $name,
            'repeat' => $repeat,
            'week' => $week,
            'lane' => $lane,
            'checkinId' => $checkinId, // Blade uses this to build the Start scoring link
        ]);
    }

    public function week(League $league, string $participant)
    {
        $event = $league->event ?? null;
        $weeks = \App\Models\LeagueWeek::query()->forContext($event, $league)->orderBy('week_number')->get();
        $laneOptions = $league->laneOptions();

        return view('public.leagues.checkin.week', [
            'league' => $league,
            'participant' => $participant,
            'weeks' => $weeks,
            'laneOptions' => $laneOptions,
        ]);
    }
}
