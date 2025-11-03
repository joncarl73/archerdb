<?php

// ← NEW
use App\Http\Controllers\CheckoutReturnController;
use App\Http\Controllers\LeagueQrController;
use App\Http\Controllers\ManageProPortalController;
use App\Http\Controllers\ParticipantImportReturnController;
use App\Http\Controllers\ProLandingController;
use App\Http\Controllers\ProReturnController;
use App\Http\Controllers\PublicCheckinController;
use App\Http\Controllers\PublicLeagueController;
use App\Http\Controllers\PublicLeagueInfoController;
use App\Http\Controllers\PublicScoringController;
use App\Http\Controllers\StartConnectForCurrentSellerController;
use App\Http\Controllers\StartLeagueCheckoutController;
use App\Http\Controllers\StartParticipantImportCheckoutController;
use App\Http\Controllers\StartProCheckoutController;
use App\Http\Controllers\StripeReturnController;
use App\Http\Controllers\StripeWebhookController;
use App\Models\Event;
use App\Models\League;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('landing.index');
})->name('home');

// Legal stuff
Route::view('/privacy', 'landing.privacy')->name('landing.privacy');
Route::view('/terms', 'landing.terms')->name('landing.terms');
Route::view('/contact', 'landing.contact')->name('landing.contact');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'profile.completed'])
    ->name('dashboard');

// (Legacy) Public league info landing by UUID
Route::get('/events/{uuid}', [PublicLeagueController::class, 'infoLanding'])->name('public.league.info.landing');

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

    // Payment Routes
    Route::post('/checkout/leagues/{league:public_uuid}/start', StartLeagueCheckoutController::class)
        ->name('checkout.league.start');

    // After Stripe redirects back (informational — fulfillment happens via webhook)
    Route::get('/checkout/return', CheckoutReturnController::class)
        ->name('checkout.return');

    // Upgrade to Pro Web Routes
    Route::get('/pro', ProLandingController::class)->name('pro.landing');
    Route::post('/pro/checkout', StartProCheckoutController::class)->name('pro.checkout.start');
    Route::get('/pro/return', ProReturnController::class)->name('pro.return');
    Route::get('/pro/manage', ManageProPortalController::class)->name('pro.manage');
});

// Onboarding Routes
Volt::route('/onboarding', 'pages.onboarding')->name('onboarding')->middleware(['auth']);
Volt::route('/onboarding/corporate', 'pages.corporate-onboarding')->name('corporate.onboarding')->middleware(['auth']);

// Webhook Routes
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

// Corporate settings
Route::middleware(['auth', 'corporate'])->group(function () {
    Volt::route('settings/company', 'settings.company')->name('settings.company');
});

// Admin Routes
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Volt::route('users', 'admin.users')->name('users');
        Volt::route('manufacturers', 'admin.manufacturers')->name('manufacturers');
        Volt::route('pricing-tiers', 'admin.pricing-tiers.index')->name('pricing.tiers.index');
        Volt::route('companies/pricing', 'admin.company-pricing.index')->name('companies.pricing.index');
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

// =====================
// LEAGUE ROUTES (corp)
// =====================
Route::middleware(['auth', 'profile.completed', 'corporate', 'corporate.completed'])
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

        Volt::route('leagues/{league}/participants', 'leagues.participants')
            ->name('leagues.participants.index')
            ->whereNumber('league');

        Volt::route(
            'leagues/{league}/participants/import/confirm/{import}',
            'corporate.leagues.participants.import-confirm'
        )->name('leagues.participants.import.confirm')
            ->whereNumber('league')
            ->whereNumber('import');

        // ✅ Start Checkout (controller)
        Route::post(
            'leagues/{league}/participants/import/{import}/start-checkout',
            StartParticipantImportCheckoutController::class
        )->name('leagues.participants.import.startCheckout')
            ->whereNumber('league')
            ->whereNumber('import');

        // ✅ Return page (controller → blade)
        Route::get(
            'leagues/{league}/participants/import/return',
            ParticipantImportReturnController::class
        )->name('leagues.participants.import.return')
            ->whereNumber('league');

        // Company & League Access
        Volt::route('companies/{company}/members', 'corporate.company.members')->name('companies.members');
        Volt::route('leagues/{league}/access', 'corporate.leagues.access')->name('leagues.access');

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
            ->name('leagues.scoring_sheet');

        // Kiosk Manager (private)
        Volt::route('leagues/{league}/kiosks', 'corporate.kiosks.index')
            ->name('manager.kiosks.index');

        // Live Score View (per week)
        Route::get('leagues/{league}/weeks/{week}/live', \App\Livewire\Corporate\Leagues\LiveScoring::class)
            ->name('leagues.weeks.live');

        // QR League Code
        Route::get('leagues/{league}/checkin-qr.pdf', [LeagueQrController::class, 'downloadCheckinQr'])
            ->name('leagues.qr.pdf');
    });

// ====================
// EVENT ROUTES (corp)
// ====================
Route::middleware(['auth', 'profile.completed', 'corporate', 'corporate.completed'])
    ->prefix('corporate')
    ->name('corporate.')
    ->group(function () {
        // Index + Show
        Volt::route('events', 'events.index')->name('events.index');

        Volt::route('events/{event}', 'events.show')
            ->name('events.show')
            ->whereNumber('event');

        // Create
        Volt::route('events/new', 'corporate.events.create')->name('events.create');

        // (Optional) collaborators management
        Volt::route('events/{event}/access', 'corporate.events.access')
            ->whereNumber('event')
            ->name('events.access');

        // Event kiosk manager (Volt page: resources/views/livewire/corporate/events/kiosks/index.blade.php)
        Volt::route('events/{event}/kiosks', 'corporate.events.kiosks.index')
            ->name('events.kiosks.index')
            ->whereNumber('event');

        // Event live scoring per line-time (Volt page: resources/views/livewire/corporate/events/live-scoring.blade.php)
        Volt::route('events/{event}/lines/{lineTime}/live', 'corporate.events.live-scoring')
            ->name('events.lines.live')
            ->whereNumber('event')
            ->whereNumber('lineTime');

        // Event check-in QR (controller)
        Route::get('events/{event}/checkin-qr.pdf', [\App\Http\Controllers\EventQrController::class, 'downloadCheckinQr'])
            ->name('events.qr.pdf')
            ->whereNumber('event');

        // Rulesets index (shared)
        Volt::route('rulesets', 'rulesets.index')->name('rulesets.index');
    });

// ======================
// PUBLIC LEAGUE ROUTES
// ======================
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

    Route::get('/checkin/ok/{checkin}', [PublicCheckinController::class, 'ok'])
        ->whereNumber('checkin')
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

// =====================
// PUBLIC EVENT ROUTES
// =====================
Route::prefix('e/{uuid}')->group(function () {
    // Public event landing
    Route::get('/', function (string $uuid) {
        $event = \App\Models\Event::query()->where('public_uuid', $uuid)->firstOrFail();

        return view('livewire/public/events/landing', ['event' => $event]);
    })->name('public.event.landing');

    Route::get('/checkin/events', [\App\Http\Controllers\PublicEventCheckinController::class, 'participants'])
        ->name('public.event.checkin.participants');

    // submit participant + branch to personal/kiosk
    Route::post('/checkin/events', [\App\Http\Controllers\PublicEventCheckinController::class, 'submitParticipants'])
        ->name('public.event.checkin.participants.submit');

    // personal device start scoring handoff
    Route::get('/scoring/start', [\App\Http\Controllers\PublicEventCheckinController::class, 'personalStart'])
        ->name('public.scoring.start');

    // kiosk info page (optional intermediate confirmation)
    Route::get('/scoring/kiosk', [\App\Http\Controllers\PublicEventCheckinController::class, 'kioskWait'])
        ->name('public.scoring.kiosk');

});

// =======================
// PUBLIC KIOSK (shared)
// =======================
Route::get('/k/{token}', [\App\Http\Controllers\KioskPublicController::class, 'landing'])
    ->name('kiosk.landing');

Route::get('/k/{token}/score/{checkin}', [\App\Http\Controllers\KioskPublicController::class, 'score'])
    ->whereNumber('checkin')
    ->name('kiosk.score');

/*
 * Stripe Connect / payments
 */
Route::middleware(['auth', 'corporate'])->group(function () {
    Route::get('/payments/connect/start', StartConnectForCurrentSellerController::class)
        ->name('payments.connect.start');

    Route::get('/payments/onboard/return', [StripeReturnController::class, 'return'])
        ->name('payments.onboard.return');

    Route::get('/payments/onboard/refresh', [StripeReturnController::class, 'refresh'])
        ->name('payments.onboard.refresh');
});

require __DIR__.'/auth.php';
