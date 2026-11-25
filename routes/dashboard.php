<?php

// Platform host routes (the root domain and app.<domain>): create-your-store, sign-in, owner
// dashboard (Inertia) and platform admin. Storefront subdomains are matched first in storefront.php.

use App\Ai\Http\Controllers\ApprovalController;
use App\Ai\Http\Controllers\ChatController;
use App\Ai\Http\Controllers\InsightController;
use App\Core\Tenancy\ResolveStoreFromSession;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Modules\Accounts\Http\Controllers\AccountSettingsController;
use App\Modules\Accounts\Http\Controllers\AuthController;
use App\Modules\Accounts\Http\Controllers\EmailVerificationController;
use App\Modules\Accounts\Http\Controllers\InvitationController;
use App\Modules\Accounts\Http\Controllers\MemberController;
use App\Modules\Accounts\Http\Controllers\TwoFactorController;
use App\Modules\Analytics\Http\Controllers\AnalyticsController;
use App\Modules\Builder\Http\Controllers\MediaController;
use App\Modules\Builder\Http\Controllers\BuilderEditorController;
use App\Modules\Builder\Http\Controllers\PageController;
use App\Modules\Builder\Http\Controllers\ThemeController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ReviewController;
use App\Modules\Catalog\Http\Controllers\StaticPageController;
use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Fpa\Http\Controllers\BudgetController;
use App\Modules\Fpa\Http\Controllers\ScenarioController;
use App\Modules\Ledger\Http\Controllers\ExpenseController;
use App\Modules\Ledger\Http\Controllers\FinanceController;
use App\Modules\Ledger\Http\Controllers\JournalController;
use App\Modules\Marketing\Http\Controllers\AdSpendController;
use App\Modules\Marketing\Http\Controllers\CampaignController;
use App\Modules\Marketing\Http\Controllers\PixelController;
use App\Modules\Marketing\Http\Controllers\SubscriberController;
use App\Modules\Notifications\Http\Controllers\AlertController;
use App\Modules\Orders\Http\Controllers\CustomerController;
use App\Modules\Orders\Http\Controllers\OrderController;
use App\Modules\Orders\Http\Controllers\ReturnController;
use App\Modules\Orders\Http\Controllers\ShippingZoneController;
use App\Modules\Platform\Http\Controllers\ActivityController;
use App\Modules\Platform\Http\Controllers\BuilderSeoPagesController;
use App\Modules\Platform\Http\Controllers\CommercePagesController;
use App\Modules\Platform\Http\Controllers\DashboardController;
use App\Modules\Platform\Http\Controllers\GrowthAiPagesController;
use App\Modules\Platform\Http\Controllers\LocaleController;
use App\Modules\Platform\Http\Controllers\PlatformAdminController;
use App\Modules\Platform\Http\Controllers\StoreSettingsController;
use App\Modules\Platform\Http\Controllers\StoreSignupController;
use App\Modules\Seo\Http\Controllers\SeoController;
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
            Route::post('/payments/{payment}/verify-instapay', [CheckoutController::class, 'verifyInstaPay'])->whereNumber('payment')->name('payments.verify-instapay');
        });

        // Live dashboard areas wired to their real modules and controllers.
        Route::get('/approvals', [GrowthAiPagesController::class, 'approvals'])->middleware('store.can:ai.approve')->name('approvals.index');
        Route::middleware('store.can:ai.approve')->prefix('approvals')->name('approvals.')->group(function () {
            Route::get('/list', [ApprovalController::class, 'index'])->name('list');
            Route::post('/{approval}/approve', [ApprovalController::class, 'approve'])->whereNumber('approval')->name('approve');
            Route::post('/{approval}/reject', [ApprovalController::class, 'reject'])->whereNumber('approval')->name('reject');
        });
        Route::prefix('insights')->name('insights.')->group(function () {
            Route::get('/', [InsightController::class, 'index'])->name('index');
            Route::post('/{insight}/act', [InsightController::class, 'act'])->whereNumber('insight')->name('act');
            Route::get('/morning-brief', [InsightController::class, 'morningBrief'])->name('morning-brief');
        });
        Route::middleware('store.can:products.view')->group(function () {
            Route::get('/categories', [CommercePagesController::class, 'categories'])->name('categories.index');
            Route::get('/inventory', [CommercePagesController::class, 'inventory'])->name('inventory.index');
        });
        Route::middleware('store.can:products.manage')->group(function () {
            Route::post('/inventory/{product}/adjust', [CommercePagesController::class, 'adjustStock'])->whereNumber('product')->name('inventory.adjust');
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [CategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->whereNumber('category')->name('categories.destroy');
            Route::post('/categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
            Route::post('/reviews/{review}/approve', [ReviewController::class, 'approve'])->whereNumber('review')->name('reviews.approve');
            Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->whereNumber('review')->name('reviews.destroy');
            Route::get('/static-pages', [StaticPageController::class, 'index'])->name('static-pages.index');
            Route::put('/static-pages/{slug}', [StaticPageController::class, 'upsert'])->name('static-pages.upsert');
            Route::get('/inbox', [StaticPageController::class, 'inbox'])->name('inbox.index');
            Route::post('/inbox/{message}/read', [StaticPageController::class, 'markRead'])->whereNumber('message')->name('inbox.read');
        });
        Route::get('/returns', [CommercePagesController::class, 'returns'])->middleware('store.can:refunds.view')->name('returns.index');
        Route::middleware('store.can:refunds.manage')->group(function () {
            Route::post('/returns', [ReturnController::class, 'store'])->name('returns.store');
            Route::post('/returns/{returnRequest}/approve', [ReturnController::class, 'approve'])->whereNumber('returnRequest')->name('returns.approve');
            Route::post('/returns/{returnRequest}/reject', [ReturnController::class, 'reject'])->whereNumber('returnRequest')->name('returns.reject');
        });
        Route::get('/analytics', [GrowthAiPagesController::class, 'analytics'])->middleware('store.can:orders.view,ledger.view')->name('analytics.index');
        Route::get('/analytics/summary', [AnalyticsController::class, 'index'])->middleware('store.can:orders.view,ledger.view')->name('analytics.summary');
        Route::middleware('store.can:marketing.manage')->prefix('marketing')->name('marketing.')->group(function () {
            Route::get('/', [GrowthAiPagesController::class, 'marketing'])->name('index');
            Route::get('/campaigns', [GrowthAiPagesController::class, 'campaigns'])->name('campaigns');
            Route::get('/discounts', [GrowthAiPagesController::class, 'discounts'])->name('discounts');
            Route::post('/discounts', [GrowthAiPagesController::class, 'storeDiscount'])->name('discounts.store');
            Route::delete('/discounts/{discount}', [GrowthAiPagesController::class, 'destroyDiscount'])->whereNumber('discount')->name('discounts.destroy');
            Route::get('/abandoned', [GrowthAiPagesController::class, 'abandoned'])->name('abandoned');
            Route::get('/pixels', [PixelController::class, 'show'])->name('pixels.show');
            Route::put('/pixels', [PixelController::class, 'update'])->name('pixels.update');
            Route::get('/subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
            Route::get('/subscribers/export', [SubscriberController::class, 'export'])->name('subscribers.export');
            Route::get('/campaigns/verdicts', [CampaignController::class, 'index'])->name('campaigns.verdicts');
            Route::post('/ad-spends', [AdSpendController::class, 'store'])->name('ad-spends.store');
        });
        Route::get('/seo', [BuilderSeoPagesController::class, 'seo'])->middleware('store.can:marketing.manage,design.manage')->name('seo.index');
        Route::middleware('store.can:marketing.manage,design.manage')->prefix('seo')->name('seo.')->group(function () {
            Route::get('/redirects', [SeoController::class, 'redirects'])->name('redirects.index');
            Route::post('/redirects', [SeoController::class, 'storeRedirect'])->name('redirects.store');
            Route::delete('/redirects/{redirect}', [SeoController::class, 'destroyRedirect'])->whereNumber('redirect')->name('redirects.destroy');
            Route::get('/not-found', [SeoController::class, 'notFoundLogs'])->name('not-found');
            Route::post('/audit', [SeoController::class, 'audit'])->name('audit');
        });
        Route::middleware('store.can:design.manage')->prefix('builder')->name('builder.')->group(function () {
            Route::get('/', [BuilderEditorController::class, 'editor'])->name('pages');
            Route::get('/theme', [BuilderSeoPagesController::class, 'theme'])->name('theme');
            Route::get('/media', [BuilderSeoPagesController::class, 'media'])->name('media');
            Route::get('/media/list', [BuilderEditorController::class, 'media'])->name('media.list');
            Route::post('/template', [BuilderSeoPagesController::class, 'applyTemplate'])->name('template');
            Route::post('/pages', [BuilderEditorController::class, 'store'])->name('pages.store');
            Route::get('/pages/{page}', [BuilderEditorController::class, 'show'])->whereNumber('page')->name('pages.show');
            Route::patch('/pages/{page}', [BuilderEditorController::class, 'update'])->whereNumber('page')->name('pages.update');
            Route::delete('/pages/{page}', [BuilderEditorController::class, 'destroy'])->whereNumber('page')->name('pages.destroy');
            Route::put('/pages/{page}/sections', [BuilderEditorController::class, 'saveSections'])->whereNumber('page')->name('sections.save');
            Route::post('/pages/{page}/sections', [PageController::class, 'addSection'])->whereNumber('page')->name('sections.store');
            Route::post('/render-section', [BuilderEditorController::class, 'renderSection'])->middleware('throttle:240,1')->name('sections.render');
            Route::get('/preview/{page}', [BuilderEditorController::class, 'preview'])->whereNumber('page')->name('preview');
            Route::post('/pages/{page}/publish', [BuilderEditorController::class, 'publish'])->whereNumber('page')->name('pages.publish');
            Route::post('/pages/{page}/versions/{version}/restore', [BuilderEditorController::class, 'restore'])->whereNumber('page')->whereNumber('version')->name('pages.restore');
            Route::put('/theme', [ThemeController::class, 'update'])->name('theme.update');
            Route::post('/media', [MediaController::class, 'store'])->name('media.store');
            Route::delete('/media/bulk', [MediaController::class, 'bulkDestroy'])->name('media.bulk-destroy');
        });
        // Finance: ledger, statements, expenses, month close, budgets. FR-LED, FR-FPA, UC-10, UC-11
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [FinanceController::class, 'overview'])->middleware('store.can:ledger.view,budgets.view')->name('index');
            Route::middleware('store.can:ledger.view')->group(function () {
                Route::get('/reports', [FinanceController::class, 'reports'])->name('reports');
                Route::get('/reports/export', [FinanceController::class, 'export'])->middleware('throttle:20,1')->name('reports.export');
                Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses');
                Route::get('/close', [ExpenseController::class, 'close'])->name('close');
                Route::get('/ledger', [JournalController::class, 'index'])->name('ledger');
            });
            Route::middleware('store.can:ledger.manage')->group(function () {
                Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('throttle:60,1')->name('expenses.store');
                Route::post('/expenses/{expense}/pay', [ExpenseController::class, 'pay'])->whereNumber('expense')->name('expenses.pay');
                Route::post('/expenses/{expense}/void', [ExpenseController::class, 'void'])->whereNumber('expense')->name('expenses.void');
                Route::post('/close', [ExpenseController::class, 'closeMonth'])->name('close.store');
                Route::post('/reopen', [ExpenseController::class, 'reopen'])->name('close.reopen');
                Route::post('/catch-up', [ExpenseController::class, 'catchUp'])->middleware('throttle:10,1')->name('catch-up');
                Route::post('/inventory-sync', [ExpenseController::class, 'syncInventory'])->middleware('throttle:10,1')->name('inventory-sync');
                Route::post('/journal', [JournalController::class, 'store'])->middleware('throttle:60,1')->name('journal.store');
                Route::post('/journal/{entry}/reverse', [JournalController::class, 'reverse'])->whereNumber('entry')->name('journal.reverse');
                Route::post('/transfer', [JournalController::class, 'transfer'])->name('transfer');
                Route::post('/accounts', [JournalController::class, 'storeAccount'])->name('accounts.store');
                Route::put('/accounts/{account}', [JournalController::class, 'updateAccount'])->whereNumber('account')->name('accounts.update');
                Route::put('/settings', [JournalController::class, 'updateSettings'])->name('settings.update');
            });
            Route::get('/budgets', [BudgetController::class, 'index'])->middleware('store.can:budgets.view')->name('budgets');
            Route::middleware('store.can:budgets.manage')->group(function () {
                Route::put('/budgets', [BudgetController::class, 'update'])->name('budgets.update');
                Route::post('/budgets/copy', [BudgetController::class, 'copy'])->name('budgets.copy');
                Route::get('/scenarios', [ScenarioController::class, 'index'])->name('scenarios.index');
                Route::post('/scenarios/simulate', [ScenarioController::class, 'simulate'])->name('scenarios.simulate');
                Route::post('/scenarios', [ScenarioController::class, 'store'])->name('scenarios.store');
            });
        });

        // Your own account, whatever your role.
        Route::get('/settings/account', [AccountSettingsController::class, 'edit'])->name('account.edit');
        Route::put('/settings/account', [AccountSettingsController::class, 'updateProfile'])->name('account.update');
        Route::put('/settings/account/password', [AccountSettingsController::class, 'updatePassword'])->middleware('throttle:6,1')->name('account.password');
        Route::post('/settings/account/two-factor/setup', [TwoFactorController::class, 'setup'])->name('account.two-factor.setup');
        Route::post('/settings/account/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('account.two-factor.confirm');
        Route::delete('/settings/account/two-factor', [TwoFactorController::class, 'destroy'])->name('account.two-factor.destroy');
        Route::get('/settings/account/export', [AccountSettingsController::class, 'exportData'])->name('account.export');
        Route::post('/settings/account/delete-request', [AccountSettingsController::class, 'requestDeletion'])->name('account.delete-request');
        // Display and accessibility preferences live on the device; notifications & alerts.
        Route::inertia('/settings/accessibility', 'settings/accessibility')->name('settings.accessibility');
        Route::get('/settings/notifications', [CommercePagesController::class, 'notifications'])->name('settings.notifications');
        Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
        Route::post('/alerts/{alert}/read', [AlertController::class, 'markRead'])->whereNumber('alert')->name('alerts.read');
        Route::post('/settings/notifications/test-email', [AlertController::class, 'sendTestEmail'])->middleware('throttle:6,1')->name('settings.notifications.test-email');

        Route::middleware('store.can:settings.manage')->group(function () {
            Route::get('/settings', [StoreSettingsController::class, 'edit'])->name('settings.general');
            Route::put('/settings/general', [StoreSettingsController::class, 'updateGeneral'])->name('settings.general.update');
            Route::put('/settings/storefront-password', [StoreSettingsController::class, 'updateStorefrontPassword'])->name('settings.password.update');
            Route::get('/settings/activity', [ActivityController::class, 'index'])->name('settings.activity');
            Route::get('/settings/payments', [CommercePagesController::class, 'payments'])->name('settings.payments');
            Route::get('/settings/shipping', [CommercePagesController::class, 'shipping'])->name('settings.shipping');
            Route::post('/settings/shipping/zones', [ShippingZoneController::class, 'store'])->name('settings.shipping.zones.store');
            Route::put('/settings/shipping/zones/{zone}', [ShippingZoneController::class, 'update'])->whereNumber('zone')->name('settings.shipping.zones.update');
            Route::delete('/settings/shipping/zones/{zone}', [ShippingZoneController::class, 'destroy'])->whereNumber('zone')->name('settings.shipping.zones.destroy');
            Route::get('/settings/domain', [CommercePagesController::class, 'domain'])->name('settings.domain');
            Route::put('/settings/domain/maintenance', [CommercePagesController::class, 'updateMaintenance'])->name('settings.domain.maintenance');
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
