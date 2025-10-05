<?php

use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\EnsureScoringMode;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCorporate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'profile.completed' => EnsureProfileIsComplete::class,
            'admin' => EnsureUserIsAdmin::class,
            'corporate' => EnsureUserIsCorporate::class,
            'scoring.mode' => EnsureScoringMode::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'stripe/*',
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
