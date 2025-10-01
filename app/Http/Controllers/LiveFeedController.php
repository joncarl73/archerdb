<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueWeek;
use App\Models\LeagueWeekScore;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LiveFeedController extends Controller
{
    /**
     * Incremental live-feed for a league + week.
     * Returns rows changed since ?since=<ISO8601> (updated_at watermark).
     *
     * Response:
     * {
     *   "cursor": "2025-09-30T15:03:12.123456Z",
     *   "rows": [
     *     {
     *       "id": 16,                       // LeagueWeekScore id (preferred)
     *       "participant_id": 123,          // if available
     *       "checkin_id": 456,              // if available
     *       "name": "Brian Brady",
     *       "lane": 4,
     *       "slot": "D",
     *       "x": 7,
     *       "tens": 2,
     *       "nines": 2,
     *       "score": 123
     *     }
     *   ]
     * }
     */
    public function week(League $league, LeagueWeek $week, Request $request)
    {
        // Ensure the week belongs to the league
        if ((int) $week->league_id !== (int) $league->id) {
            abort(404);
        }

        // ----- read stable cursor (JSON like {"ts":"2025-09-30T20:15:12.345678Z","last_id":1234}) -----
        $sinceRaw = (string) $request->query('since', '');
        $sinceTs = null;
        $sinceId = 0;
        if ($sinceRaw !== '') {
            try {
                $obj = json_decode($sinceRaw, true, flags: JSON_THROW_ON_ERROR);
                if (is_array($obj)) {
                    $sinceTs = isset($obj['ts']) ? Carbon::parse($obj['ts']) : null;
                    $sinceId = isset($obj['last_id']) ? (int) $obj['last_id'] : 0;
                }
            } catch (\Throwable $e) {
                // bad cursor → treat as cold start
                $sinceTs = null;
                $sinceId = 0;
            }
        }

        // Aliases
        $lws = 'league_week_scores';
        $lp = 'league_participants';
        $lc = 'league_checkins';

        // ----- query changed rows with gap-free predicate -----
        $q = DB::table("$lws as lws")
            ->where('lws.league_id', $league->id)
            ->where('lws.league_week_id', $week->id);

        if ($sinceTs) {
            $q->where(function ($w) use ($sinceTs, $sinceId) {
                $w->where('lws.updated_at', '>', $sinceTs)
                    ->orWhere(function ($w2) use ($sinceTs, $sinceId) {
                        $w2->where('lws.updated_at', '=', $sinceTs)
                            ->where('lws.id', '>', $sinceId);
                    });
            });
        }

        // Join participants for fallback name, and check-ins for lane/slot/name via league/week/participant
        $scores = $q
            ->leftJoin("$lp as lp", 'lp.id', '=', 'lws.league_participant_id')
            ->leftJoin("$lc as lc", function ($join) use ($week) {
                $join->on('lc.league_id', '=', 'lws.league_id')
                    ->on('lc.participant_id', '=', 'lws.league_participant_id')
                    ->where('lc.week_number', '=', $week->week_number);
            })
            ->orderBy('lws.updated_at', 'asc')
            ->orderBy('lws.id', 'asc')
            ->limit(150)
            ->get([
                'lws.id',
                'lws.league_participant_id',
                'lws.total_score',
                'lws.x_count',
                'lws.x_value',        // interpret 11-as-X if x_value=11
                'lws.updated_at',
                'lp.first_name',
                'lp.last_name',
                'lc.id as checkin_id',
                'lc.participant_name',
                'lc.lane_number',
                'lc.lane_slot',
            ]);

        // If no changes, keep prior cursor (if any) so the client won’t skip anything
        if ($scores->isEmpty()) {
            $cursor = $sinceRaw !== '' ? json_decode($sinceRaw, true) : ['ts' => Carbon::now()->toISOString(), 'last_id' => 0];

            return response()->json([
                'cursor' => $cursor,
                'rows' => [],
            ]);
        }

        // Batch load week_ends for these score IDs to derive 10s and 9s
        $scoreIds = $scores->pluck('id')->all();

        $ends = DB::table('league_week_ends')
            ->whereIn('league_week_score_id', $scoreIds)
            ->get(['league_week_score_id', 'scores']); // scores is JSON text

        // Map score_id => x_value (used to interpret 11 as X)
        $xValByScoreId = [];
        foreach ($scores as $r) {
            $xValByScoreId[(int) $r->id] = (int) ($r->x_value ?? 10);
        }

        // Aggregate 10s & 9s per score_id by parsing the JSON
        $tallyByScoreId = [];
        foreach ($ends as $e) {
            $sid = (int) $e->league_week_score_id;
            $vals = $e->scores ? json_decode($e->scores, true) : [];
            if (! is_array($vals)) {
                continue;
            }

            $t = 0;
            $n = 0;
            $xVal = $xValByScoreId[$sid] ?? 10; // 10 or 11 typically

            foreach ($vals as $raw) {
                // normalize
                if (is_string($raw)) {
                    $s = strtoupper(trim($raw));
                    if ($s === '') {
                        continue;
                    }
                    if ($s === 'X') {
                        continue;
                    }          // do not count X as 10
                    if (! is_numeric($s)) {
                        continue;
                    }
                    $raw = (int) $s;
                }
                if (! is_int($raw)) {
                    continue;
                }

                // if X encoded as 11 and x_value=11, skip as X
                if ($raw === 11 && $xVal === 11) {
                    continue;
                }

                if ($raw === 10) {
                    $t++;
                } elseif ($raw === 9) {
                    $n++;
                }
            }
            $tallyByScoreId[$sid] = [
                'tens' => ($tallyByScoreId[$sid]['tens'] ?? 0) + $t,
                'nines' => ($tallyByScoreId[$sid]['nines'] ?? 0) + $n,
            ];
        }

        // Build payload rows
        $rows = $scores->map(function ($r) use ($tallyByScoreId) {
            $sid = (int) $r->id;
            $tens = $tallyByScoreId[$sid]['tens'] ?? 0;
            $nines = $tallyByScoreId[$sid]['nines'] ?? 0;

            // name pref: checkin.participant_name -> "first last" -> '(Guest)'
            $name = trim((string) ($r->participant_name ?? ''));
            if ($name === '') {
                $fname = trim((string) ($r->first_name ?? ''));
                $lname = trim((string) ($r->last_name ?? ''));
                $both = trim($fname.' '.$lname);
                $name = $both !== '' ? $both : '(Guest)';
            }

            return [
                'id' => $sid,
                'participant_id' => (int) $r->league_participant_id,
                'checkin_id' => $r->checkin_id ? (int) $r->checkin_id : null,
                'name' => $name,
                'lane' => $r->lane_number,
                'slot' => $r->lane_slot,
                'x' => (int) $r->x_count,
                'tens' => (int) $tens,
                'nines' => (int) $nines,
                'score' => (int) $r->total_score,
                // 'updated_at'   => optional($r->updated_at)->toIso8601String(),
            ];
        })->values();

        // ----- emit stable cursor from the last source row we returned -----
        $lastSrc = $scores->last(); // ordered asc by ts,id
        $cursor = [
            'ts' => optional($lastSrc->updated_at)->toIso8601String(),
            'last_id' => (int) $lastSrc->id,
        ];

        return response()->json([
            'cursor' => $cursor,
            'rows' => $rows,
        ]);
    }
}
