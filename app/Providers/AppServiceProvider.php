<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Event;
use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueWeekEnd;
use App\Models\LeagueWeekScore;
use App\Models\Ruleset;
use App\Observers\LeagueCheckinObserver;
use App\Observers\LeagueWeekEndObserver;
use App\Observers\LeagueWeekScoreObserver;
use App\Policies\CompanyPolicy;
use App\Policies\EventPolicy;
use App\Policies\LeaguePolicy;
use App\Policies\RulesetPolicy;
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

        // Blade directive for pro content
        Blade::if('pro', function () {
            $u = Auth::user();

            return $u && $u->isPro();
        });

        // Policy mapping for League
        Gate::policy(League::class, LeaguePolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Ruleset::class, RulesetPolicy::class);

        // Observers
        LeagueWeekEnd::observe(LeagueWeekEndObserver::class);
        LeagueCheckin::observe(LeagueCheckinObserver::class);
        LeagueWeekScore::observe(LeagueWeekScoreObserver::class);
    }
}
