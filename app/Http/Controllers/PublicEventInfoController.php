<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Facades\Storage;

class PublicEventInfoController extends Controller
{
    public function show(string $uuid)
    {
        // Load the event + its info; also pull linked league info for legacy bits if needed
        $event = Event::query()
            ->where('public_uuid', $uuid)
            ->with(['info', 'league', 'league.info'])
            ->firstOrFail();

        // Banner URL (prefer EventInfo banner, fall back to LeagueInfo banner)
        $bannerPath = $event->info?->banner_path ?: $event->league?->info?->banner_path;
        $bannerUrl = $bannerPath ? Storage::disk('public')->url($bannerPath) : null;

        // Registration window (starts_on/ends_on live on Event)
        $now = now();
        $isRegistrationOpen = (is_null($event->starts_on) || $event->starts_on->startOfDay()->lte($now))
                           && (is_null($event->ends_on) || $event->ends_on->endOfDay()->gte($now));

        return view('public.event-info', [
            'event' => $event,
            'bannerUrl' => $bannerUrl,
            'isRegistrationOpen' => $isRegistrationOpen,
        ]);
    }
}
