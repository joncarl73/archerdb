<?php

use App\Http\Controllers\LiveFeedController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/live/league/{league}/week/{week}', [LiveFeedController::class, 'week'])->whereNumber(['league', 'week']);
