<?php

namespace App\Http\Controllers;

use App\Models\League;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    // app/Http/Controllers/PublicLeagueController.php

    public function infoLanding(string $uuid)
    {
        $league = \App\Models\League::query()
            ->where('public_uuid', $uuid)
            ->with(['info'])
            ->firstOrFail();

        // --- Banner URL resolution ---
        $bannerUrl = null;

        // A) Explicit absolute URL stored in DB
        if (filled($league->info?->banner_url) &&
            Str::startsWith($league->info->banner_url, ['http://', 'https://', '//'])) {
            $bannerUrl = $league->info->banner_url;
        }

        // B) Relative path on the public disk (e.g., "banners/123.jpg")
        if (! $bannerUrl && filled($league->info?->banner_url)) {
            $rel = ltrim($league->info->banner_url, '/');
            if (Storage::disk('public')->exists($rel)) {
                $bannerUrl = Storage::url($rel);
            }
        }

        // C) Conventional locations on public disk (fallbacks)
        if (! $bannerUrl) {
            $candidates = [
                "leagues/{$league->id}/banner.webp",
                "leagues/{$league->id}/banner.jpg",
                "leagues/{$league->id}/banner.png",
            ];
            foreach ($candidates as $path) {
                if (Storage::disk('public')->exists($path)) {
                    $bannerUrl = Storage::url($path);
                    break;
                }
            }
        }

        // D) Spatie Media Library (optional)
        if (! $bannerUrl && method_exists($league->info, 'getFirstMediaUrl')) {
            $url = $league->info->getFirstMediaUrl('banner');
            if ($url) {
                $bannerUrl = $url;
            }
        }

        // Window calc + other vars (unchanged)
        $start = $league->registration_start_date;
        $end = $league->registration_end_date;
        $registrationUrl = optional($league->info)->registration_url;

        $window = null;
        if ($start && $end) {
            $today = now()->startOfDay();
            $window = $today->lt($start) ? 'before' : ($today->gt($end) ? 'after' : 'during');
        }

        $contentHtml = optional($league->info)->content_html ?? '';

        return view('landing.league-info', compact(
            'league', 'start', 'end', 'registrationUrl', 'bannerUrl', 'window', 'contentHtml'
        ));
    }
}
