<?php

namespace App\Http\Controllers;

use App\Models\League;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ParticipantImportReturnController extends Controller
{
    public function __invoke(Request $request, League $league)
    {
        Gate::authorize('update', $league);

        // We do not commit here; the webhook will finalize the import.
        // Show a simple message telling the user the payment result or that it’s being confirmed.
        $sessionId = (string) $request->query('session_id', '');

        return view('livewire.corporate.leagues.participants.return', [
            'league' => $league,
            'sessionId' => (string) $request->query('session_id', ''),
        ]);
    }
}
