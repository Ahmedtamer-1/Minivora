<?php

// Platform host routes (the root domain and app.<domain>): create-your-store, sign-in, owner
// dashboard (Inertia) and platform admin. Storefront subdomains are matched first in storefront.php.

use App\Ai\Http\Controllers\ChatController;
use App\Core\Tenancy\ResolveStoreFromSession;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Modules\Accounts\Http\Controllers\AccountSettingsController;
use App\Modules\Accounts\Http\Controllers\AuthController;
use App\Modules\Accounts\Http\Controllers\EmailVerificationController;
use App\Modules\Accounts\Http\Controllers\InvitationController;
use App\Modules\Accounts\Http\Controllers\MemberController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Orders\Http\Controllers\CustomerController;
use App\Modules\Orders\Http\Controllers\OrderController;
use App\Modules\Platform\Http\Controllers\ActivityController;
use App\Modules\Platform\Http\Controllers\DashboardController;
use App\Modules\Platform\Http\Controllers\LocaleController;
use App\Modules\Platform\Http\Controllers\PlatformAdminController;
use App\Modules\Platform\Http\Controllers\StoreSettingsController;
use App\Modules\Platform\Http\Controllers\StoreSignupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StoreSignupController::class, 'landing'])->name('home');
Route::get('/locale/{locale}', [LocaleController::class, 'update'])->name('locale');

// Invitations work signed in or out.
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitations.accept');

// Email verification link: may be opened signed out, the signature proves it.
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

Route::middleware('guest')->group(function () {
    Route::post('/create-store', [StoreSignupController::class, 'store'])->middleware('throttle:10,1')->name('stores.signup');
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');

    // Create another store with the same account. FR-ONB-08
    Route::get('/stores/create', [StoreSignupController::class, 'create'])->name('stores.create');
    Route::post('/stores', [StoreSignupController::class, 'storeForUser'])->middleware('throttle:10,1')->name('stores.store');
    Route::post('/stores/{storeId}/switch', [StoreSignupController::class, 'switch'])->whereNumber('storeId')->name('stores.switch');

    // Everything below works inside the selected store.
    Route::middleware(ResolveStoreFromSession::class)->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'home'])->name('dashboard');
        Route::post('/onboarding', [DashboardController::class, 'saveProfile'])->middleware('store.can:design.manage')->name('onboarding.save');
        Route::post('/onboarding/skip', [DashboardController::class, 'skipProfile'])->name('onboarding.skip');
        Route::get('/preview', [StoreSettingsController::class, 'preview'])->name('store.preview');

        // AI assistant: full-screen chat. Facts are filtered by the member's permissions.
        Route::get('/ai', [ChatController::class, 'show'])->name('ai.chat');
        Route::post('/ai/chat', [ChatController::class, 'send'])->middleware('throttle:20,1')->name('ai.chat.send');

        // Catalog. FR-CAT-01
        Route::get('/products', [ProductController::class, 'index'])->middleware('store.can:products.view')->name('products.index');
        Route::middleware('store.can:products.manage')->group(function () {
            Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ProductController::class, 'store'])->middleware('throttle:30,1')->name('products.store');
            Route::post('/products/import', [ProductController::class, 'import'])->middleware('throttle:10,1')->name('products.import');
            Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->whereNumber('product')->name('products.edit');
            Route::put('/products/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])->whereNumber('product')->name('products.destroy');
        });

        // Orders and customers. FR-ORD
        Route::middleware('store.can:orders.view')->group(function () {
            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/export', [OrderController::class, 'export'])->middleware('throttle:10,1')->name('orders.export');
            Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
            Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('/customers/lookup', [CustomerController::class, 'lookup'])->middleware('throttle:60,1')->name('customers.lookup');
            Route::get('/customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
        });
        Route::middleware('store.can:orders.manage')->group(function () {
            Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
            Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:30,1')->name('orders.store');
            Route::post('/orders/bulk-status', [OrderController::class, 'bulkStatus'])->name('orders.bulk');
            Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])->whereNumber('order')->name('orders.status');
            Route::put('/orders/{order}/fulfilment', [OrderController::class, 'updateFulfilment'])->whereNumber('order')->name('orders.fulfilment');
            Route::put('/orders/{order}/notes', [OrderController::class, 'updateNotes'])->whereNumber('order')->name('orders.notes');
        });

        // Your own account, whatever your role.
        Route::get('/settings/account', [AccountSettingsController::class, 'edit'])->name('account.edit');
        Route::put('/settings/account', [AccountSettingsController::class, 'updateProfile'])->name('account.update');
        Route::put('/settings/account/password', [AccountSettingsController::class, 'updatePassword'])->middleware('throttle:6,1')->name('account.password');

        Route::middleware('store.can:settings.manage')->group(function () {
            Route::get('/settings', [StoreSettingsController::class, 'edit'])->name('settings.general');
            Route::put('/settings/general', [StoreSettingsController::class, 'updateGeneral'])->name('settings.general.update');
            Route::put('/settings/storefront-password', [StoreSettingsController::class, 'updateStorefrontPassword'])->name('settings.password.update');
            Route::get('/settings/activity', [ActivityController::class, 'index'])->name('settings.activity');
        });

        Route::middleware('store.can:design.manage')->group(function () {
            Route::post('/settings/publish', [StoreSettingsController::class, 'publish'])->name('settings.publish');
            Route::post('/settings/unpublish', [StoreSettingsController::class, 'unpublish'])->name('settings.unpublish');
        });

        Route::middleware('store.can:members.manage')->group(function () {
            Route::get('/settings/members', [MemberController::class, 'index'])->name('members.index');
            Route::post('/settings/members', [MemberController::class, 'invite'])->middleware('throttle:20,1')->name('members.invite');
            Route::put('/settings/members/{member}', [MemberController::class, 'update'])->whereNumber('member')->name('members.update');
            Route::delete('/settings/members/{member}', [MemberController::class, 'destroy'])->whereNumber('member')->name('members.destroy');
            Route::delete('/settings/invitations/{invitation}', [MemberController::class, 'revokeInvitation'])->whereNumber('invitation')->name('invitations.revoke');
        });
    });

    // Platform admin. FR-ACC-08
    Route::middleware(EnsurePlatformAdmin::class)->prefix('admin')->name('admin.')->group(function () {
        Route::get('/stores', [PlatformAdminController::class, 'index'])->name('stores.index');
        Route::post('/stores/{store}/suspend', [PlatformAdminController::class, 'suspend'])->name('stores.suspend');
        Route::post('/stores/{store}/restore', [PlatformAdminController::class, 'restore'])->name('stores.restore');
    });
});
