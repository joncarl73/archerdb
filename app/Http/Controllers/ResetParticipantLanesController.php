<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ResetParticipantLanesController
{
    public function __invoke(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        $event->participants()->update([
            'assigned_lane' => null,
            'assigned_slot' => null,
        ]);

        return back()->with('status', 'All participant lanes/slots have been cleared.');
    }
}
