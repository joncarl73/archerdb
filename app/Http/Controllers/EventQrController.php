<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Facades\Gate;

class EventQrController extends Controller
{
    public function downloadCheckinQr(Event $event)
    {
        Gate::authorize('view', $event);

        $url = route('public.cls.participants', [
            'kind' => 'event',
            'uuid' => $event->public_uuid,
        ]);

        $svg = \QrCode::format('svg')->size(600)->margin(1)->errorCorrection('M')->generate($url);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="event-'.$event->id.'-checkin-qr.svg"',
        ]);
    }
}
