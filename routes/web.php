<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth','profile.completed'])
    ->name('dashboard');

Route::middleware(['auth','profile.completed'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/archer-profile','settings.archer-profile')->name('settings.archer-profile');

    // Gear Loadout Routes
    Volt::route('gear/loadouts','gear.loadouts')->name('gear.loadouts');

    // Personal Training Routes
    Volt::route('/training','training.index')->name('training.index');
    Volt::route('/training/{session}/record','training.record')->name('training.record')->whereNumber('session');
    Volt::route('/training/{session}/stats','training.stats')->name('training.stats');
});

// Onboarding Routes
Volt::route('/onboarding','pages.onboarding')->name('onboarding')->middleware(['auth']);

// Admin Routes
Route::middleware(['auth','admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Volt::route('users','admin.users')->name('users');
        Volt::route('manufacturers','admin.manufacturers')->name('manufacturers');
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



require __DIR__.'/auth.php';