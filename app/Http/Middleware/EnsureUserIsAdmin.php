<?php

// app/Http/Middleware/EnsureUserIsAdmin.php
namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $u = Auth::user();
        if (!$u || $u->role !== UserRole::Administrator) {
            abort(403);
        }
        return $next($request);
    }
}

