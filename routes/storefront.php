<?php

// Shopper pages on <store>.<domain>, resolved by ResolveStoreFromHost. /ar/... and /en/... URLs.
// Reserved names (app, www, ...) never match here, so they fall through to the platform routes.

use App\Core\Tenancy\ReservedSubdomains;
use App\Core\Tenancy\ResolveStoreFromHost;
use App\Core\Tenancy\StoreUrl;
use App\Modules\Analytics\Http\Controllers\TrackController;
use App\Modules\Cart\Http\Controllers\CartController;
use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Checkout\Http\Controllers\WebhookController;
use App\Modules\Marketing\Http\Controllers\SubscriberController;
use App\Modules\Orders\Http\Controllers\TrackOrderController;
use App\Modules\Platform\Http\Controllers\StorefrontController;
use App\Modules\Platform\Http\Middleware\StorefrontGate;
use App\Modules\Seo\Http\Controllers\FeedController;
use App\Modules\Seo\Http\Controllers\LlmsTxtController;
use App\Modules\Seo\Http\Controllers\RobotsController;
use App\Modules\Seo\Http\Controllers\SitemapController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

$reserved = implode('|', array_map('preg_quote', ReservedSubdomains::all()));

// STOREFRONT_MODE=path serves the same routes at <APP_URL>/s/<store> instead (StoreUrl).
$group = StoreUrl::pathMode()
    ? Route::prefix('s/{store}')->where(['store' => '(?!(?:'.$reserved.')$)[a-z0-9-]+'])
    : Route::domain('{store}.'.config('minivora.domain'))->where(['store' => '(?!(?:'.$reserved.')\.)[a-z0-9-]+']);

$group
    ->middleware([ResolveStoreFromHost::class, StorefrontGate::class])
    ->name('storefront.')
    ->group(function () {
        Route::get('/', [StorefrontController::class, 'root'])->name('root');
        Route::get('/__preview', [StorefrontController::class, 'preview'])->middleware('signed:relative')->name('preview');
        Route::post('/__unlock', [StorefrontController::class, 'unlock'])->middleware('throttle:20,1')->name('unlock');
        Route::get('/{locale}', [StorefrontController::class, 'home'])->whereIn('locale', config('minivora.locales'))->name('home');

        // Shopper pages built by the store builder (FR-BLD-01, FR-CAT-04, FR-CAT-07, FR-CAT-11)
        Route::prefix('/{locale}')->whereIn('locale', config('minivora.locales'))->group(function () {
            Route::get('/shop', [StorefrontController::class, 'shop'])->name('shop');
            Route::get('/categories/{slug}', [StorefrontController::class, 'category'])->name('category');
            Route::get('/products/{slug}', [StorefrontController::class, 'product'])->name('product');
            Route::get('/pages/{slug}', [StorefrontController::class, 'page'])->name('page');
            Route::get('/search', [StorefrontController::class, 'search'])->name('search');
            Route::get('/cart', [StorefrontController::class, 'cart'])->name('cart');
        });

        // SEO crawlers & product feeds (FR-SEO-05, FR-SEO-06, FR-SEO-15, FR-SEO-16)
        Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('seo.sitemap');
        Route::get('/robots.txt', [RobotsController::class, 'index'])->name('seo.robots');
        Route::get('/llms.txt', [LlmsTxtController::class, 'index'])->name('seo.llms');
        Route::get('/feeds/google.xml', [FeedController::class, 'google'])->name('seo.feeds.google');
        Route::get('/feeds/meta.tsv', [FeedController::class, 'meta'])->name('seo.feeds.meta');

        // Consent-gated analytics & newsletter (FR-ANA-01, FR-ANA-06, FR-MKT-05)
        Route::post('/track', [TrackController::class, 'store'])->middleware('throttle:120,1')->name('analytics.track');
        Route::post('/newsletter/subscribe', [SubscriberController::class, 'store'])->middleware('throttle:20,1')->name('newsletter.subscribe');

        // Cart (FR-CRT-01..04)
        Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
        Route::post('/cart/items', [CartController::class, 'store'])->middleware('throttle:60,1')->name('cart.store');
        Route::put('/cart/items/{item}', [CartController::class, 'update'])->whereNumber('item')->name('cart.update');
        Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->whereNumber('item')->name('cart.destroy');

        // Single-page guest checkout & lead capture (FR-PAY-01..12, NFR-USE-03)
        Route::get('/{locale}/checkout', [CheckoutController::class, 'show'])->whereIn('locale', config('minivora.locales'))->name('checkout.show');
        Route::post('/checkout/lead', [CheckoutController::class, 'captureLead'])->middleware('throttle:30,1')->name('checkout.lead');
        Route::get('/{locale}/orders/{number}/thank-you', [CheckoutController::class, 'confirmation'])->whereIn('locale', config('minivora.locales'))->whereNumber('number')->name('checkout.confirmation');
        Route::get('/{locale}/track-order', [TrackOrderController::class, 'page'])->whereIn('locale', config('minivora.locales'))->name('orders.track.page');
        Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('checkout.store');

        // Payment webhooks (FR-PAY-09, NFR-SEC-14)
        Route::post('/webhooks/{gateway}', [WebhookController::class, 'handle'])->withoutMiddleware([ValidateCsrfToken::class])->name('webhooks.handle');

        // Public order tracking (FR-ORD-05, FR-ORD-07)
        Route::post('/track-order', [TrackOrderController::class, 'lookup'])->middleware('throttle:20,1')->name('orders.track');
    });
