<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\League;
use App\Models\LeagueCheckin;
use App\Models\LeagueWeekEnd;
use App\Models\LeagueWeekScore;
use App\Observers\LeagueCheckinObserver;
use App\Observers\LeagueWeekEndObserver;
use App\Observers\LeagueWeekScoreObserver;
use App\Policies\LeaguePolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // -------- Blade directives --------
        Blade::if('corporate', function () {
            $u = Auth::user();

            return $u && in_array($u->role, [UserRole::Administrator, UserRole::Corporate], true);
        });

        Blade::if('admin', function () {
            $u = Auth::user();

            return $u && $u->role === UserRole::Administrator;
        });

        Blade::if('pro', function () {
            $u = Auth::user();

            return $u && $u->isPro();
        });

        // -------- Policies --------
        Gate::policy(League::class, LeaguePolicy::class);
        // If you add an EventPolicy later, this will wire itself up safely:
        if (class_exists(\App\Policies\EventPolicy::class)) {
            Gate::policy(Event::class, \App\Policies\EventPolicy::class);
        }

        // -------- Eloquent morph map (for Product.productable etc.) --------
        // Use short aliases so new rows store 'league' / 'event' instead of FQCNs.
        // We intentionally use morphMap (NOT enforceMorphMap) to remain compatible
        // with any historical rows that still store the FQCN.
        Relation::morphMap([
            'league' => \App\Models\League::class,
            'event' => \App\Models\Event::class,
            // add other polymorphic types here if you have them
        ]);

        // If/when all historical rows are migrated to aliases, you can switch to:
        // Relation::enforceMorphMap([
        //     'league' => \App\Models\League::class,
        //     'event'  => \App\Models\Event::class,
        // ]);

        // -------- Observers --------
        LeagueWeekEnd::observe(LeagueWeekEndObserver::class);
        LeagueCheckin::observe(LeagueCheckinObserver::class);
        LeagueWeekScore::observe(LeagueWeekScoreObserver::class);
    }
}
