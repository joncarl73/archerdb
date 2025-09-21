<?php

use App\Models\League;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'profile.completed'])
    ->name('dashboard');

Route::middleware(['auth', 'profile.completed'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/archer-profile', 'settings.archer-profile')->name('settings.archer-profile');

    // Gear Loadout Routes
    Volt::route('gear/loadouts', 'gear.loadouts')->name('gear.loadouts');

    // Personal Training Routes
    Volt::route('/training', 'training.index')->name('training.index');
    Volt::route('/training/{session}/record', 'training.record')->name('training.record')->whereNumber('session');
    Volt::route('/training/{session}/stats', 'training.stats')->name('training.stats');
});

// Onboarding Routes
Volt::route('/onboarding', 'pages.onboarding')->name('onboarding')->middleware(['auth']);

// Admin Routes
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Volt::route('users', 'admin.users')->name('users');
        Volt::route('manufacturers', 'admin.manufacturers')->name('manufacturers');
    });

// Stop Impersonation Route
// routes/web.php
Route::middleware(['auth'])->group(function () {
    Route::post('/impersonation/stop', function () {
        $orig = session()->pull('impersonator_id');
        if ($orig) {
            \Illuminate\Support\Facades\Auth::loginUsingId($orig);
        }

        return redirect()->route('admin.users');
    })->name('impersonate.stop');
});

// League Routes
Route::middleware(['auth', 'profile.completed', 'corporate'])
    ->prefix('corporate')
    ->name('corporate.')
    ->group(function () {
        // Leagues (Volt pages)
        Volt::route('leagues', 'leagues.index')->name('leagues.index');
        Volt::route('leagues/{league}', 'leagues.show')
            ->name('leagues.show')
            ->whereNumber('league'); // uses route model binding for League

        // CSV template download
        Route::get('leagues/{league}/participants/template.csv', function (League $league) {
            Gate::authorize('update', $league);

            $csv = "first_name,last_name,email\n";

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="participants-template-'.$league->id.'.csv"',
            ]);
        })->name('leagues.participants.template');

        // (Optional) Export existing participants
        Route::get('leagues/{league}/participants/export.csv', function (League $league) {
            Gate::authorize('view', $league);

            $filename = 'participants-export-'.$league->id.'.csv';

            return response()->streamDownload(function () use ($league) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['first_name', 'last_name', 'email', 'member']); // header
                $league->participants()
                    ->orderBy('last_name')->orderBy('first_name')
                    ->chunk(500, function ($chunk) use ($out) {
                        foreach ($chunk as $p) {
                            fputcsv($out, [
                                $p->first_name,
                                $p->last_name,
                                $p->email,
                                $p->user_id ? 'yes' : 'no',
                            ]);
                        }
                    });
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        })->name('leagues.participants.export');
    });

// Outside page for non members for leagues
Route::get('/l/{uuid}', [\App\Http\Controllers\PublicLeagueController::class, 'show'])
    ->name('leagues.public');

require __DIR__.'/auth.php';
