<?php

// Shopper pages on <store>.<domain>, resolved by ResolveStoreFromHost. /ar/... and /en/... URLs.
// Reserved names (app, www, ...) never match here, so they fall through to the platform routes.

use App\Core\Tenancy\ReservedSubdomains;
use App\Core\Tenancy\ResolveStoreFromHost;
use App\Modules\Platform\Http\Controllers\StorefrontController;
use App\Modules\Platform\Http\Middleware\StorefrontGate;
use Illuminate\Support\Facades\Route;

$reserved = implode('|', array_map('preg_quote', ReservedSubdomains::all()));

Route::domain('{store}.'.config('minivora.domain'))
    ->where(['store' => '(?!(?:'.$reserved.')\.)[a-z0-9-]+'])
    ->middleware([ResolveStoreFromHost::class, StorefrontGate::class])
    ->name('storefront.')
    ->group(function () {
        Route::get('/', [StorefrontController::class, 'root'])->name('root');
        Route::get('/__preview', [StorefrontController::class, 'preview'])->middleware('signed:relative')->name('preview');
        Route::post('/__unlock', [StorefrontController::class, 'unlock'])->middleware('throttle:20,1')->name('unlock');
        Route::get('/{locale}', [StorefrontController::class, 'home'])->whereIn('locale', config('minivora.locales'))->name('home');
    });
