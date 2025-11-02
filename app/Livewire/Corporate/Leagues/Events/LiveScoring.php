<?php

namespace App\Livewire\Corporate\Events;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventLineTime;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class LiveScoring extends Component
{
    public Event $event;

    public EventLineTime $lineTime;

    /** Simple payload for the view */
    public array $checkins = [];

    public function mount(Event $event, EventLineTime $lineTime): void
    {
        Gate::authorize('view', $event);

        // (optional) ensure the line time belongs to this event
        if ((int) $lineTime->event_id !== (int) $event->id) {
            abort(404);
        }

        $this->event = $event;
        $this->lineTime = $lineTime;

        $this->loadCheckins();
    }

    public function loadCheckins(): void
    {
        $rows = EventCheckin::query()
            ->where('event_id', $this->event->id)
            ->where('event_line_time_id', $this->lineTime->id)
            ->orderBy('lane_number')
            ->orderBy('lane_slot')
            ->get([
                'id',
                'participant_id',
                'participant_name',
                'lane_number',
                'lane_slot',
            ]);

        $this->checkins = $rows->map(function ($r) {
            $lane = (string) ($r->lane_number ?? '');
            if ($lane !== '' && $r->lane_slot && $r->lane_slot !== 'single') {
                $lane .= $r->lane_slot;
            }

            return [
                'id' => (int) $r->id,
                'participant_id' => (int) $r->participant_id,
                'participant_name' => (string) ($r->participant_name ?: 'Unknown'),
                'lane' => $lane !== '' ? $lane : null,
            ];
        })->all();
    }

    public function render()
    {
        return view('livewire.corporate.events.live-scoring');
    }
}
