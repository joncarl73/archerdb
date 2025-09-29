<?php

namespace App\Livewire\Corporate\Leagues;

use App\Models\League;
use App\Models\LeagueWeek;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class LiveScoring extends Component
{
    public League $league;

    public LeagueWeek $week;

    public int $refreshMs = 2000;

    public bool $compact = false;

    /** @var array<int,array{name:string,lane:?int,slot:?string,score:int,x:int,tens:int,nines:int}> */
    public array $rows = [];

    public function mount(League $league, LeagueWeek $week): void
    {
        Gate::authorize('view', $league);

        $this->league = $league;
        $this->week = $week;

        $this->loadRows();
    }

    public function render(): mixed
    {
        return view('livewire.corporate.leagues.live-scoring')
            ->layout('layouts.public', [
                'league' => $this->league,
                'kiosk' => request()->boolean('kiosk'), // hide header when ?kiosk=1
                'forceTheme' => 'light',                     // default this screen to light
            ]);
    }

    public function loadRows(): void
    {
        $weekId = $this->week->id;
        $weekNumber = $this->week->week_number;
        $leagueId = $this->league->id;

        // 1) Start from CHECK-INS (one row per lc.id)
        $base = DB::table('league_checkins as lc')
            ->leftJoin('league_participants as lp', 'lp.id', '=', 'lc.participant_id')
            ->leftJoin('league_week_scores as lws', function ($j) use ($weekId) {
                // Only scores for THIS league_week
                $j->on('lws.league_participant_id', '=', 'lc.participant_id')
                    ->where('lws.league_week_id', '=', $weekId);
            })
            ->where('lc.league_id', $leagueId)
            ->where('lc.week_number', $weekNumber)
            ->select([
                'lc.id as checkin_id',
                'lc.participant_id as pid',
                DB::raw("COALESCE(CONCAT(lp.last_name, ', ', lp.first_name), lc.participant_name) as name"),
                'lc.lane_number as lane',
                'lc.lane_slot as slot',
                'lws.id as score_id',
                'lws.total_score as score',
                'lws.x_count as x',
            ])
            ->orderBy('lc.lane_number')
            ->orderBy('lc.lane_slot');

        $rows = $base->get();

        // Initialize rows keyed by CHECK-IN id (not participant_id)
        $checkinRows = [];                  // checkin_id => row data
        $scoreIdToCkId = [];                  // score_id   => checkin_id  (for 10/9 tally merge)

        foreach ($rows as $r) {
            $ck = (int) $r->checkin_id;

            $checkinRows[$ck] = [
                'name' => $r->name ?? '—',
                'lane' => $r->lane,
                'slot' => $r->slot,
                'score' => (int) ($r->score ?? 0),
                'x' => (int) ($r->x ?? 0),
                'tens' => 0,
                'nines' => 0,
            ];

            if ($r->score_id) {
                $scoreIdToCkId[(int) $r->score_id] = $ck;
            }
        }

        // 2) Tally 10s / 9s from league_week_ends for the score rows we found
        if (! empty($scoreIdToCkId)) {
            $ends = DB::table('league_week_ends as lwe')
                ->whereIn('lwe.league_week_score_id', array_keys($scoreIdToCkId))
                ->select(['lwe.league_week_score_id', 'lwe.scores'])
                ->get();

            foreach ($ends as $er) {
                $ck = $scoreIdToCkId[(int) $er->league_week_score_id] ?? null;
                if ($ck === null || ! isset($checkinRows[$ck])) {
                    continue;
                }

                $scores = $er->scores ? json_decode($er->scores, true) : [];
                if (! is_array($scores)) {
                    continue;
                }

                foreach ($scores as $val) {
                    $v = is_string($val) ? (strtoupper($val) === 'X' ? 10 : (int) $val) : (int) $val;
                    if ($v === 10) {
                        $checkinRows[$ck]['tens']++;
                    }
                    if ($v === 9) {
                        $checkinRows[$ck]['nines']++;
                    }
                }
            }
        }

        // 3) Sort for display: lane/slot, then score desc, X, 10s, 9s, name
        $final = array_values($checkinRows);
        usort($final, function ($a, $b) {
            if ($a['lane'] !== null && $b['lane'] !== null && $a['lane'] !== $b['lane']) {
                return $a['lane'] <=> $b['lane'];
            }
            if ($a['slot'] !== null && $b['slot'] !== null && $a['slot'] !== $b['slot']) {
                return strcmp((string) $a['slot'], (string) $b['slot']);
            }
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            if ($a['x'] !== $b['x']) {
                return $b['x'] <=> $a['x'];
            }
            if ($a['tens'] !== $b['tens']) {
                return $b['tens'] <=> $a['tens'];
            }
            if ($a['nines'] !== $b['nines']) {
                return $b['nines'] <=> $a['nines'];
            }

            return strcasecmp($a['name'], $b['name']);
        });

        $this->rows = $final;
    }
}
