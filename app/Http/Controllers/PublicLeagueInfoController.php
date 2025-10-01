<?php

namespace App\Http\Controllers;

use App\Models\League;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicLeagueInfoController extends Controller
{
    public function show(Request $request, string $uuid)
    {
        $league = League::query()
            ->where('public_uuid', $uuid)
            ->with('info')
            ->firstOrFail();

        // Require an info record and that it’s published
        if (! $league->info || ! $league->info->is_published) {
            abort(404);
        }

        // Banner URL (if any)
        $bannerUrl = null;
        if ($league->info->banner_path && Storage::disk('public')->exists($league->info->banner_path)) {
            $bannerUrl = Storage::url($league->info->banner_path);
        }

        // Registration window (applies to both open/closed)
        $tz = config('app.timezone');
        $today = now($tz)->startOfDay();

        $start = $league->registration_start_date ? $league->registration_start_date->copy()->startOfDay() : null;
        $end = $league->registration_end_date ? $league->registration_end_date->copy()->endOfDay() : null;

        $window = 'unknown'; // before | during | after | unknown
        if ($start && $end) {
            if ($today->lt($start)) {
                $window = 'before';
            } elseif ($today->gt($end)) {
                $window = 'after';
            } else {
                $window = 'during';
            }
        } elseif ($start && ! $end) {
            $window = $today->lt($start) ? 'before' : 'during';
        } elseif (! $start && $end) {
            $window = $today->gt($end) ? 'after' : 'during';
        }

        // CTA logic:
        // - If in window "during" AND a registration_url exists, show a “Register” button.
        // - Otherwise show a subtle status notice (“opens”, “closed”, etc.)
        $registrationUrl = $league->info->registration_url ?: null;

        return view('public.league-info', [
            'league' => $league,
            'bannerUrl' => $bannerUrl,
            'contentHtml' => (string) $league->info->content_html,
            'window' => $window,
            'start' => $start,
            'end' => $end,
            'registrationUrl' => $registrationUrl,
        ]);
    }
}
