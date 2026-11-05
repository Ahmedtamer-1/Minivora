<?php

use App\Http\Middleware\EnsureStorePermission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Storefront subdomains first; anything else is the platform host.
            Route::middleware('web')->group(base_path('routes/storefront.php'));
            Route::middleware(['web', SetLocale::class, HandleInertiaRequests::class])
                ->group(base_path('routes/dashboard.php'));
        },
    )
    ->withCommands([
        __DIR__.'/../app/Core/Console',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'store.can' => EnsureStorePermission::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
