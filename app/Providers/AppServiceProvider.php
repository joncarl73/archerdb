<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use App\Enums\UserRole;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Blade directive for corporate/admin-only content
        Blade::if('corporate', function () {
            $u = Auth::user();
            return $u && in_array($u->role, [UserRole::Administrator, UserRole::Corporate], true);
        });

        // Blade directive for admin-only content
        Blade::if('admin', function () {
            $u = Auth::user();
            return $u && $u->role === UserRole::Administrator;
        });
    }
}
