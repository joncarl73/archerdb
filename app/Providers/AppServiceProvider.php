<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueWeekEnd;
use App\Models\LeagueWeekScore;
use App\Observers\LeagueCheckinObserver;
use App\Observers\LeagueWeekEndObserver;
use App\Observers\LeagueWeekScoreObserver;
use App\Policies\LeaguePolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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

        // Policy mapping for League
        Gate::policy(League::class, LeaguePolicy::class);

        // Observers
        LeagueWeekEnd::observe(LeagueWeekEndObserver::class);
        LeagueCheckin::observe(LeagueCheckinObserver::class);
        LeagueWeekScore::observe(LeagueWeekScoreObserver::class);
    }
}
