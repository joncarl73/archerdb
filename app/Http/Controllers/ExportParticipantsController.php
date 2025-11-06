<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventLineTime;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportParticipantsController
{
    public function __invoke(Request $request, Event $event): StreamedResponse
    {
        $filename = 'event-participants-export-'.$event->id.'.csv';

        return response()->streamDownload(function () use ($event) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM for Excel
            fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));

            // Columns requested
            fputcsv($out, [
                'first_name',
                'last_name',
                'email',
                'division_name',
                'bow_type',
                'is_para',
                'uses_wheelchair',
                'line_time',      // derived label
                'assigned_lane',
                'assigned_slot',
                'notes',
            ]);

            // Preload line-times for labels
            $labels = EventLineTime::query()
                ->where('event_id', $event->id)
                ->get()
                ->keyBy('id')
                ->map(function ($lt) {
                    $date = \Carbon\Carbon::parse($lt->line_date)->format('n/j/Y');
                    $start = \Carbon\Carbon::parse($lt->start_time)->format('g:i A');
                    $end = $lt->end_time ? \Carbon\Carbon::parse($lt->end_time)->format('g:i A') : null;

                    return 'Line '.$date.' '.$start.($end ? ' → '.$end : '');
                })
                ->all();

            $event->participants()
                ->orderBy('last_name')->orderBy('first_name')
                ->chunk(500, function ($chunk) use ($out, $labels) {
                    foreach ($chunk as $p) {
                        fputcsv($out, [
                            $p->first_name,
                            $p->last_name,
                            $p->email,
                            $p->division_name,
                            $p->bow_type,
                            $p->is_para ? 'true' : 'false',
                            $p->uses_wheelchair ? 'true' : 'false',
                            $p->line_time_id ? ($labels[$p->line_time_id] ?? '') : '',
                            $p->assigned_lane,
                            $p->assigned_slot,
                            $p->notes,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
