<?php

// ← NEW
use App\Http\Controllers\PublicCheckinController;
use App\Http\Controllers\PublicLeagueInfoController;
use App\Http\Controllers\PublicScoringController;
use App\Models\League;
use Illuminate\Support\Facades\Gate;
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
        Volt::route('leagues/{league}/info', 'corporate.leagues.info-editor')
            ->name('leagues.info.edit')
            ->whereNumber('league');

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

        // Scoring Routes
        Route::get('leagues/{league}/scoring-sheet',
            [\App\Http\Controllers\LeagueScoringSheetController::class, '__invoke'])
            ->name('leagues.scoring_sheet')
            ->middleware(['auth']);

        // Kiosk Manager (private) — already in your file
        Volt::route('leagues/{league}/kiosks', 'corporate.kiosks.index')
            ->name('manager.kiosks.index');

        // Live Score View
        Route::get('leagues/{league}/weeks/{week}/live', \App\Livewire\Corporate\Leagues\LiveScoring::class)
            ->name('leagues.weeks.live');

    });

// --- PUBLIC (no auth) ---
Route::prefix('l/{uuid}')->group(function () {
    // Public check-in flow
    Route::get('/checkin', [PublicCheckinController::class, 'participants'])
        ->name('public.checkin.participants'); // pick participant

    Route::post('/checkin', [PublicCheckinController::class, 'participantsSubmit'])
        ->name('public.checkin.participants.submit');

    Route::get('/checkin/{participant}', [PublicCheckinController::class, 'details'])
        ->whereNumber('participant')
        ->name('public.checkin.details'); // pick week + lane

    Route::post('/checkin/{participant}', [PublicCheckinController::class, 'detailsSubmit'])
        ->whereNumber('participant')
        ->name('public.checkin.details.submit');

    Route::get('/checkin/ok', [PublicCheckinController::class, 'ok'])
        ->name('public.checkin.ok'); // confirmation

    // Personal-device scoring
    Route::get('/start-scoring/{checkin}', [PublicScoringController::class, 'start'])
        ->whereNumber('checkin')
        ->name('public.scoring.start');

    Route::get('/score/{score}', [PublicScoringController::class, 'record'])
        ->whereNumber('score')
        ->name('public.scoring.record');

    Route::get('/scoring/{score}/summary', [PublicScoringController::class, 'summary'])
        ->whereNumber('score') // ← added for consistency
        ->name('public.scoring.summary');

    Route::get('/info', [PublicLeagueInfoController::class, 'show'])
        ->name('public.league.info');
});

/**
 * NEW: Public kiosk tablet routes (unguarded, tokenized)
 *  - /k/{token} shows the lane-filtered list of checked-in archers
 *  - /k/{token}/score/{checkin} hands off to the existing scoring record screen
 */
Route::get('/k/{token}', [\App\Http\Controllers\KioskPublicController::class, 'landing'])
    ->name('kiosk.landing');

Route::get('/k/{token}/score/{checkin}', [\App\Http\Controllers\KioskPublicController::class, 'score'])
    ->whereNumber('checkin')
    ->name('kiosk.score');

require __DIR__.'/auth.php';
