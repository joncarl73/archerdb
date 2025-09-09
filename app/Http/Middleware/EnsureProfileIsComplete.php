<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureProfileIsComplete
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            $profile = $user->archerProfile;
            $isOnboarding = $request->routeIs('onboarding');

            if (!$isOnboarding && (!$profile || !$profile->completed_at)) {
                return redirect()->route('onboarding');
            }
        }
        return $next($request);
    }
}

