<?php

// /api/v1: storefront AJAX calls (cart, search, tracking), payment webhooks, future apps.

use App\Modules\Platform\Http\Controllers\StoreSignupController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Live store-address check on the create-your-store form. FR-ONB-02
    Route::get('/subdomains/check', [StoreSignupController::class, 'check'])->middleware('throttle:60,1')->name('api.subdomains.check');
});
