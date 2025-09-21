<?php

// app/Http/Controllers/Corporate/LeagueCheckinController.php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use App\Models\League;
use Illuminate\Support\Facades\Gate;

class LeagueCheckinController extends Controller
{
    public function show(League $league)
    {
        Gate::authorize('update', $league);

        $publicUrl = route('public.checkin.participants', $league->public_uuid);

        return view('leagues.checkin', [
            'league' => $league,
            'publicUrl' => $publicUrl,
        ]);
    }

    // Minimal inline QR (pure SVG; no packages). Uses simple QR render via a tiny data URI fallback
    public function qr(League $league)
    {
        Gate::authorize('update', $league);

        $url = route('public.checkin.participants', $league->public_uuid);

        // If you have simplesoftwareio/qrcode, you can return that instead:
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(240)->format('svg')->generate($url);

            return response($svg, 200)->header('Content-Type', 'image/svg+xml');
        }

        // Fallback: tiny SVG placeholder with URL text (not a scannable QR, but avoids errors)
        $safe = e($url);
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240">
  <rect width="100%" height="100%" fill="#f3f4f6"/>
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="12" fill="#111827">
    QR package not installed
  </text>
  <text x="50%" y="65%" dominant-baseline="middle" text-anchor="middle" font-size="10" fill="#374151">
    {$safe}
  </text>
</svg>
SVG;

        return response($svg, 200)->header('Content-Type', 'image/svg+xml');
    }

    public function week(League $league, int $participantId)
    {
        $weeks = $league->weeks()->orderBy('week_number')->get();
        $laneOptions = $league->laneOptions();

        return view('leagues.checkin.week', compact('league', 'participantId', 'weeks', 'laneOptions'));
    }
}
