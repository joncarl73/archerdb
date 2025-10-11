<?php

// app/Http/Middleware/EnsureCorporateOnboardingCompleted.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureCorporateOnboardingCompleted
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->role === 'corporate') {
            $company = $user->company; // may be null
            $onboardingRoute = 'corporate.onboarding';

            if ((! $company || ! $company->completed_at) && ! $request->routeIs($onboardingRoute)) {
                return redirect()->route($onboardingRoute);
            }
        }

        return $next($request);
    }
}
