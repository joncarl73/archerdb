<?php

// app/Http/Middleware/EnsureUserIsCorporate.php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsCorporate
{
    public function handle(Request $request, Closure $next)
    {
        $u = Auth::user();

        if (! $u) {
            abort(403);
        }

        // Normalize role to enum (works whether it's already an enum or a string)
        $role = $u->role instanceof UserRole
            ? $u->role
            : UserRole::tryFrom((string) $u->role);

        if (! $role || ! in_array($role, [UserRole::Administrator, UserRole::Corporate], true)) {
            abort(403);
        }

        // Admins bypass onboarding checks completely
        if ($role === UserRole::Administrator) {
            return $next($request);
        }

        // --- Corporate users ---
        $company = $u->company; // requires users.company_id + relationship

        // Allow these routes even if company is not completed
        $isAllowedWhileIncomplete =
            $request->routeIs('corporate.onboarding') ||
            $request->routeIs('settings.company');

        if ((! $company || ! $company->completed_at) && ! $isAllowedWhileIncomplete) {
            if (
                $request->isMethod('GET') &&
                ! $request->expectsJson() &&
                $request->accepts(['text/html', 'application/xhtml+xml'])
            ) {
                session(['intended_url' => $request->fullUrl()]);
            }

            return redirect()->route('corporate.onboarding');
        }

        return $next($request);
    }
}
