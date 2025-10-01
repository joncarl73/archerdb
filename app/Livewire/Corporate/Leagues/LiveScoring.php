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

    /**
     * Public data consumed by the blade. Each row represents one archer's score row.
     * Shape: { id:int, checkin_id:?int, participant_id:?int, name:string, lane:?int, slot:?string, x:int, tens:int, nines:int, score:int }
     */
    public array $rows = [];

    /** @var array<string, array<string, mixed>> Internal index keyed by s:{id} OR p:{participant_id} OR c:{checkin_id} */
    protected array $rowsByKey = [];

    public function mount(League $league, LeagueWeek $week): void
    {
        Gate::authorize('view', $league);
        $this->league = $league;
        $this->week = $week;
        $this->loadRows(); // initial snapshot (polling will keep it fresh)
    }

    public function render(): mixed
    {
        return view('livewire.corporate.leagues.live-scoring')
            ->layout('components.layouts.public', [
                'league' => $this->league,
                'kiosk' => request()->boolean('kiosk'),
                'forceTheme' => 'light',
            ]);
    }

    public function loadRows(): void
    {
        $weekId = $this->week->id;
        $weekNumber = $this->week->week_number;
        $leagueId = $this->league->id;

        $base = DB::table('league_checkins as lc')
            ->leftJoin('league_participants as lp', 'lp.id', '=', 'lc.participant_id')
            ->leftJoin('league_week_scores as lws', function ($j) use ($weekId) {
                $j->on('lws.league_participant_id', '=', 'lc.participant_id')
                    ->where('lws.league_week_id', '=', $weekId);
            })
            ->where('lc.league_id', $leagueId)
            ->where('lc.week_number', $weekNumber)
            ->select([
                'lc.id as checkin_id',
                'lc.participant_id as participant_id',
                DB::raw("COALESCE(CONCAT(lp.last_name, ', ', lp.first_name), lc.participant_name) as name"),
                'lc.lane_number as lane',
                'lc.lane_slot as slot',

                // NOTE: alias score id distinctly so we don't collide with checkin_id etc.
                'lws.id as score_id',
                'lws.total_score as score',
                'lws.x_count as x',
                'lws.x_value as x_value',
            ])
            ->orderBy('lc.lane_number')
            ->orderBy('lc.lane_slot')
            ->get();

        // Gather score_ids for 10/9 tally
        $scoreIds = $base->pluck('score_id')->filter()->map(fn ($v) => (int) $v)->all();

        // Map score_id => x_value (used to interpret 11 as X when x_value=11)
        $xValByScoreId = [];
        foreach ($base as $r) {
            if (! empty($r->score_id)) {
                $xValByScoreId[(int) $r->score_id] = (int) ($r->x_value ?? 10);
            }
        }

        // Pre-compute tens/nines per score_id
        $tenNineByScore = [];
        if ($scoreIds) {
            $ends = DB::table('league_week_ends')
                ->whereIn('league_week_score_id', $scoreIds)
                ->get(['league_week_score_id', 'scores']);

            foreach ($ends as $er) {
                $sid = (int) $er->league_week_score_id;
                $vals = $er->scores ? json_decode($er->scores, true) : [];
                if (! is_array($vals)) {
                    continue;
                }

                $t = 0;
                $n = 0;
                $xVal = $xValByScoreId[$sid] ?? 10; // 10 or 11 typically

                foreach ($vals as $v) {
                    if (is_string($v)) {
                        $s = strtoupper(trim($v));
                        if ($s === 'X') {
                            // X never counts toward 10s bucket
                            continue;
                        }
                        if (is_numeric($s)) {
                            $v = (int) $s;
                        } else {
                            continue;
                        }
                    }

                    if (! is_int($v)) {
                        // ignore non-int garbage
                        continue;
                    }

                    // If X is encoded numerically as 11 and x_value=11, don't count as 10
                    if ($v === 11 && $xVal === 11) {
                        continue; // it's an X
                    }

                    if ($v === 10) {
                        $t++;
                    } elseif ($v === 9) {
                        $n++;
                    }
                }

                $acc = $tenNineByScore[$sid] ?? ['tens' => 0, 'nines' => 0];
                $tenNineByScore[$sid] = ['tens' => $acc['tens'] + $t, 'nines' => $acc['nines'] + $n];
            }
        }

        $rows = [];
        foreach ($base as $r) {
            $scoreId = (int) ($r->score_id ?? 0);
            $tens = $scoreId ? ($tenNineByScore[$scoreId]['tens'] ?? 0) : 0;
            $nines = $scoreId ? ($tenNineByScore[$scoreId]['nines'] ?? 0) : 0;

            $rows[] = $this->normalizeRow([
                'id' => $scoreId,
                'checkin_id' => $r->checkin_id ? (int) $r->checkin_id : null,
                'participant_id' => $r->participant_id ? (int) $r->participant_id : null,
                'name' => $r->name ?? '—',
                'lane' => isset($r->lane) ? (int) $r->lane : null,
                'slot' => $r->slot ?? null,
                'score' => (int) ($r->score ?? 0),
                'x' => (int) ($r->x ?? 0),
                'tens' => (int) $tens,
                'nines' => (int) $nines,
            ]);
        }

        $this->reindexRows($rows);
        $this->resortRows();
    }

    /**
     * Merge a single updated row (from polling delta).
     *
     * @param  array<string,mixed>  $row
     */
    public function applyDelta(array $row): void
    {
        // Normalize incoming types
        $row['id'] = isset($row['id']) ? (int) $row['id'] : null;
        $row['participant_id'] = isset($row['participant_id']) ? (int) $row['participant_id'] : null;
        $row['checkin_id'] = isset($row['checkin_id']) ? (int) $row['checkin_id'] : null;
        $row['score'] = isset($row['score']) ? (int) $row['score'] : 0;
        $row['x'] = isset($row['x']) ? (int) $row['x'] : 0;
        $row['tens'] = isset($row['tens']) ? (int) $row['tens'] : 0;
        $row['nines'] = isset($row['nines']) ? (int) $row['nines'] : 0;

        // find existing index by id, then participant_id, then checkin_id
        $idx = null;
        foreach ($this->rows as $i => $r) {
            if (! empty($row['id']) && ! empty($r['id']) && (int) $r['id'] === (int) $row['id']) {
                $idx = $i;
                break;
            }
            if (! empty($row['participant_id']) && ! empty($r['participant_id']) && (int) $r['participant_id'] === (int) $row['participant_id']) {
                $idx = $i;
                break;
            }
            if (! empty($row['checkin_id']) && ! empty($r['checkin_id']) && (int) $r['checkin_id'] === (int) $row['checkin_id']) {
                $idx = $i;
                break;
            }
        }

        $payload = [
            'id' => $row['id'] ?? ($idx !== null ? ($this->rows[$idx]['id'] ?? null) : null),
            'checkin_id' => $row['checkin_id'] ?? ($idx !== null ? ($this->rows[$idx]['checkin_id'] ?? null) : null),
            'participant_id' => $row['participant_id'] ?? ($idx !== null ? ($this->rows[$idx]['participant_id'] ?? null) : null),
            'name' => $row['name'] ?? ($idx !== null ? $this->rows[$idx]['name'] : '—'),
            'lane' => $row['lane'] ?? ($idx !== null ? $this->rows[$idx]['lane'] : null),
            'slot' => $row['slot'] ?? ($idx !== null ? $this->rows[$idx]['slot'] : null),
            'score' => $row['score'],
            'x' => $row['x'],
            'tens' => $row['tens'],
            'nines' => $row['nines'],
        ];

        if ($idx === null) {
            $this->rows[] = $payload;
        } else {
            $this->rows[$idx] = array_merge($this->rows[$idx], $payload);
        }

        // keep UI order stable
        $this->rows = $this->sorted($this->rows);
    }

    /* ------------------------ Helpers ------------------------ */

    /** @param array<int,array<string,mixed>> $rows */
    protected function reindexRows(array $rows): void
    {
        $this->rowsByKey = [];
        foreach ($rows as $r) {
            $this->rowsByKey[$this->keyForRow($r)] = $r;
        }
        $this->rows = array_values($this->rowsByKey);
    }

    /** @return array<string,mixed> */
    protected function normalizeRow(array $r): array
    {
        $r['id'] = (int) ($r['id'] ?? 0);
        $r['checkin_id'] = array_key_exists('checkin_id', $r) ? (is_null($r['checkin_id']) ? null : (int) $r['checkin_id']) : null;
        $r['participant_id'] = array_key_exists('participant_id', $r) ? (is_null($r['participant_id']) ? null : (int) $r['participant_id']) : null;
        $r['name'] = (string) ($r['name'] ?? '—');
        $r['lane'] = array_key_exists('lane', $r) ? (is_null($r['lane']) ? null : (int) $r['lane']) : null;
        $r['slot'] = $r['slot'] ?? null;
        $r['x'] = (int) ($r['x'] ?? 0);
        $r['tens'] = (int) ($r['tens'] ?? 0);
        $r['nines'] = (int) ($r['nines'] ?? 0);
        $r['score'] = (int) ($r['score'] ?? 0);

        return $r;
    }

    /** @param array<string,mixed> $r */
    protected function keyForRow(array $r): string
    {
        if (! empty($r['id'])) {
            return 's:'.(int) $r['id'];
        }
        if (! empty($r['participant_id'])) {
            return 'p:'.(int) $r['participant_id'];
        }
        if (! empty($r['checkin_id'])) {
            return 'c:'.(int) $r['checkin_id'];
        }

        return 'n:'.md5(($r['name'] ?? '-').'|'.($r['lane'] ?? '').'|'.($r['slot'] ?? ''));
    }

    /** Sort and store $this->rowsByKey -> $this->rows (lane, slot, then scoreboard). */
    protected function resortRows(): void
    {
        $this->rows = $this->sorted(array_values($this->rowsByKey));
    }

    /** Public-ish sorter used by Blade and applyDelta */
    public function sorted(array $rows): array
    {
        // A-D before 'single'; nulls last
        static $slotOrder = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'single' => 5, '' => 98, null => 99];

        usort($rows, function ($a, $b) use ($slotOrder) {
            // lane asc (nulls last)
            $la = $a['lane'] ?? PHP_INT_MAX;
            $lb = $b['lane'] ?? PHP_INT_MAX;
            if ($la !== $lb) {
                return $la <=> $lb;
            }

            // slot custom order
            $sa = $slotOrder[$a['slot'] ?? null] ?? 99;
            $sb = $slotOrder[$b['slot'] ?? null] ?? 99;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            // score desc, then X desc, 10s desc, 9s desc
            if (($a['score'] ?? 0) !== ($b['score'] ?? 0)) {
                return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
            }
            if (($a['x'] ?? 0) !== ($b['x'] ?? 0)) {
                return ($b['x'] ?? 0) <=> ($a['x'] ?? 0);
            }
            if (($a['tens'] ?? 0) !== ($b['tens'] ?? 0)) {
                return ($b['tens'] ?? 0) <=> ($a['tens'] ?? 0);
            }
            if (($a['nines'] ?? 0) !== ($b['nines'] ?? 0)) {
                return ($b['nines'] ?? 0) <=> ($a['nines'] ?? 0);
            }

            // finally, name A→Z
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $rows;
    }
}
