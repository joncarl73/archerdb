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

        // Allow admins or corporate users
        if (!$u || !in_array($u->role, [UserRole::Administrator, UserRole::Corporate], true)) {
            abort(403);
        }

        return $next($request);
    }
}
