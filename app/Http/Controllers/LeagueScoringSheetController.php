<?php

namespace App\Http\Controllers;

use App\Models\League;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class LeagueScoringSheetController extends Controller
{
    public function __invoke(Request $request, League $league)
    {
        Gate::authorize('view', $league);

        // Weeks in ascending order
        $league->load(['weeks' => fn ($q) => $q->orderBy('week_number')]);

        // Participants ordered like your page
        $participants = $league->participants()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        // Pull weekly totals from league_week_scores joined to league_weeks for week_number
        // One row per (participant, week_number) with total_score
        $raw = DB::table('league_week_scores as lws')
            ->join('league_weeks as lw', 'lw.id', '=', 'lws.league_week_id')
            ->where('lws.league_id', $league->id)
            ->select([
                'lws.league_participant_id as participant_id',
                'lw.week_number',
                DB::raw('SUM(lws.total_score) as total_score'), // SUM just in case of duplicates; uniq constraint should keep it 1 row
            ])
            ->groupBy('lws.league_participant_id', 'lw.week_number')
            ->get();

        // Index to [participant_id][week_number] => total_score
        $byParticipantWeek = [];
        foreach ($raw as $r) {
            $byParticipantWeek[$r->participant_id][$r->week_number] = (int) $r->total_score;
        }

        // Build rows for PDF
        $rows = $participants->map(function ($p) use ($league, $byParticipantWeek) {
            $weeks = [];
            $seasonTotal = 0;
            foreach ($league->weeks as $w) {
                $v = $byParticipantWeek[$p->id][$w->week_number] ?? 0;
                $weeks[$w->week_number] = $v;
                $seasonTotal += $v;
            }

            return [
                'name' => "{$p->last_name}, {$p->first_name}",
                'email' => $p->email,
                'weeks' => $weeks,       // keyed by week_number
                'seasonTotal' => $seasonTotal,
            ];
        });

        $pdf = Pdf::loadView('pdf.league-scoring-sheet', [
            'league' => $league,
            'rows' => $rows,
        ])->setPaper('letter', 'landscape');

        $fname = Str::slug($league->title).'-scoring-sheet.pdf';

        return $pdf->download($fname);
    }
}
