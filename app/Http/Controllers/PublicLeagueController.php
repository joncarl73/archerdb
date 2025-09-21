<?php

namespace App\Http\Controllers;

use App\Models\League;

class PublicLeagueController
{
    /** Display the public (no-auth) league page by UUID. */
    public function show(string $uuid)
    {
        $league = League::query()
            ->where('public_uuid', $uuid)
            ->firstOrFail();

        // Return view with "noindex" to keep crawlers out
        return response()
            ->view('livewire.leagues.public', compact('league'))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
