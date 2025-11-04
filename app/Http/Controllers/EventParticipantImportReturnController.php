<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventParticipantImportReturnController extends Controller
{
    public function __invoke(Request $request, Event $event)
    {
        Gate::authorize('update', $event);

        // The view already reads ?status=success|canceled
        return view('livewire.corporate.events.participants.return', ['event' => $event]);
    }
}
