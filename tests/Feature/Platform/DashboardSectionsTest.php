<?php

use App\Core\Permissions\Role;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

// Every dashboard area from the SRS has a page: built modules use controllers, the rest are previews
// with sample data (Documentation §7.3), each behind the permission its real module will use.

dataset('preview pages', [
    ['/approvals', 'approvals/index'],
    ['/categories', 'categories/index'],
    ['/inventory', 'products/inventory'],
    ['/returns', 'orders/returns'],
    ['/analytics', 'analytics/index'],
    ['/marketing', 'marketing/index'],
    ['/marketing/campaigns', 'marketing/campaigns'],
    ['/marketing/discounts', 'marketing/discounts'],
    ['/marketing/abandoned', 'marketing/abandoned'],
    ['/seo', 'seo/index'],
    ['/builder', 'builder/editor'],
    ['/builder/theme', 'builder/theme'],
    ['/builder/media', 'builder/media'],
    ['/finance', 'finance/index'],
    ['/finance/reports', 'finance/reports'],
    ['/finance/expenses', 'finance/expenses'],
    ['/finance/budgets', 'finance/budgets'],
    ['/finance/ledger', 'finance/ledger'],
    ['/settings/payments', 'settings/payments'],
    ['/settings/shipping', 'settings/shipping'],
    ['/settings/domain', 'settings/domain'],
    ['/settings/notifications', 'settings/notifications'],
    ['/settings/accessibility', 'settings/accessibility'],
]);

test('owners can open every dashboard area', function (string $path, string $component) {
    signedInMember(Role::Owner);

    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with('preview pages');

test('FR-ACC-03 previews follow the permission matrix: staff see neither money nor marketing', function () {
    signedInMember(Role::Staff);

    foreach (['/finance', '/finance/ledger', '/marketing', '/seo', '/builder', '/approvals', '/returns', '/settings/payments'] as $path) {
        $this->get($path)->assertForbidden();
    }
    // Their own display settings and the areas they work in stay open.
    $this->get('/settings/accessibility')->assertOk();
    $this->get('/inventory')->assertOk();
    $this->get('/analytics')->assertOk();
});

test('a route listing several permissions lets in a member with any one of them', function () {
    // Accountants have ledger.view and budgets.view but not marketing or design.
    signedInMember(Role::Accountant);

    $this->get('/finance')->assertOk();
    $this->get('/finance/budgets')->assertOk();
    $this->get('/analytics')->assertOk();
    $this->get('/seo')->assertForbidden();
    $this->get('/builder')->assertForbidden();
});

test('managers reach finance and budgets, but not the owner-only payment settings', function () {
    signedInMember(Role::Manager);

    $this->get('/finance')->assertOk();
    $this->get('/finance/budgets')->assertOk(); // budgets.view
    $this->get('/settings/payments')->assertForbidden(); // settings.manage is owner-only
});

test('home "This week" sums the last 7 Cairo days and compares them with the 7 before', function () {
    [, $store] = signedInMember(Role::Owner);
    $product = app(CurrentStore::class)->runAs($store, fn () => Product::factory()->live()->create(['price' => 50000, 'stock' => 20]));

    $order = function () use ($product, $store): Order {
        $this->post(route('orders.store'), [
            'customer_name' => 'Mona Adel', 'phone' => '01001234567', 'governorate' => 'cairo', 'address' => '12 Tahrir St',
            'channel' => 'instagram', 'payment_method' => 'cod', 'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        return app(CurrentStore::class)->runAs($store, fn () => Order::query()->latest('id')->firstOrFail());
    };

    $today = $order();
    $lastWeek = $order();
    $lastWeek->forceFill(['placed_at' => Carbon::now('Africa/Cairo')->subDays(9)])->save();

    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->component('today/index')
        ->where('week.amount', $today->total)
        ->where('week.change', 0)
        ->has('week.days', 14)
        ->where('week.days.13', $today->total)
        ->where('week.days.4', $lastWeek->total));
});

test('home "This week" stays empty until the store has a sale', function () {
    signedInMember(Role::Owner);

    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('week', null));
});

test('translations are sent once per language, not with every click', function () {
    signedInMember(Role::Owner);
    $version = $this->get('/settings/account')->viewData('page')['version'];
    $key = 'translations:en:'.filemtime(lang_path('en.json'));
    $inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $version];

    // A visit from a client that has not got them yet: the dictionary comes along.
    $first = $this->get('/settings/accessibility', $inertia)->assertOk()->json();
    expect($first['props'])->toHaveKey('translations')
        ->and($first['props']['translations']['common.save'])->toBe('Save');

    // A client that already holds this language: the next page arrives without them.
    $next = $this->get('/settings/members', $inertia + ['X-Inertia-Except-Once-Props' => $key])->assertOk()->json();
    expect($next['props'])->not->toHaveKey('translations');

    // Switching language changes the key, so the new dictionary is sent.
    $this->get('/locale/ar');
    $arabic = $this->get('/settings/members', $inertia + ['X-Inertia-Except-Once-Props' => $key])->assertOk()->json();
    expect($arabic['props']['translations']['common.save'])->toBe('حفظ');
});
