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


require __DIR__.'/auth.php';
