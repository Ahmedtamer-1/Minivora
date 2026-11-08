<?php

use App\Core\Permissions\Role;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Notifications\OrderStatusChanged;
use App\Modules\Platform\Models\Store;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function productIn(Store $store, array $state = []): Product
{
    return app(CurrentStore::class)->runAs($store, fn () => Product::factory()->live()->create($state));
}

function inStore(Store $store, Closure $fn): mixed
{
    return app(CurrentStore::class)->runAs($store, $fn);
}

/** Post a manual order and return it. */
function manualOrder(Store $store, array $overrides = []): Order
{
    $product = $overrides['product'] ?? productIn($store, ['name' => 'Linen shirt', 'price' => 85000, 'cost' => 30000, 'stock' => 10]);
    unset($overrides['product']);

    test()->post(route('orders.store'), array_merge([
        'customer_name' => 'Mona Adel',
        'phone' => '+20 100 123 4567',
        'email' => 'mona@example.com',
        'governorate' => 'cairo',
        'city' => 'Nasr City',
        'address' => '12 Abbas El Akkad',
        'channel' => 'instagram',
        'payment_method' => 'cod',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ], $overrides))->assertSessionHasNoErrors();

    return inStore($store, fn () => Order::query()->latest('id')->firstOrFail());
}

test('FR-ORD-04 staff take a manual order: prices snapshot, stock comes off, shopper is saved', function () {
    [, $store] = signedInMember(Role::Staff);
    $product = productIn($store, ['name' => 'Linen shirt', 'price' => 85000, 'cost' => 30000, 'stock' => 10]);

    $order = manualOrder($store, ['product' => $product, 'discount' => '100']);

    expect($order->number)->toBe(1001)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->channel)->toBe('instagram')
        ->and($order->phone)->toBe('01001234567')
        ->and($order->subtotal)->toBe(170000)
        ->and($order->shipping_fee)->toBe(6000)       // Cairo default rate
        ->and($order->discount)->toBe(10000)
        ->and($order->total)->toBe(166000)
        ->and($order->stock_deducted)->toBeTrue();

    inStore($store, function () use ($product, $order) {
        expect($product->fresh()->stock)->toBe(8)
            ->and($product->fresh()->sold_count)->toBe(2)
            ->and($order->items()->sole()->unit_cost)->toBe(30000)
            ->and($order->history()->sole()->to_status)->toBe(OrderStatus::Pending);
        $customer = Customer::query()->sole();
        expect($customer->phone)->toBe('01001234567')
            ->and($customer->orders_count)->toBe(1)
            ->and($customer->total_spent)->toBe(166000);
    });
});

test('FR-ORD-04 a second order from the same phone joins the same customer and numbers go up', function () {
    [, $store] = signedInMember();
    manualOrder($store);
    $second = manualOrder($store, ['phone' => '01001234567', 'customer_name' => 'Mona A.']);

    expect($second->number)->toBe(1002);
    inStore($store, function () {
        $customer = Customer::query()->sole();
        expect($customer->orders_count)->toBe(2)->and($customer->name)->toBe('Mona A.');
    });
});

test('FR-ORD-04 an order cannot take more than is in stock', function () {
    [, $store] = signedInMember();
    $product = productIn($store, ['stock' => 1]);

    $this->post(route('orders.store'), [
        'customer_name' => 'Ali', 'channel' => 'phone', 'payment_method' => 'cod',
        'items' => [['product_id' => $product->id, 'quantity' => 3]],
    ])->assertSessionHasErrors('items');

    inStore($store, fn () => expect(Order::query()->count())->toBe(0)->and($product->fresh()->stock)->toBe(1));
});

test('FR-ORD-01 orders follow the allowed status moves only', function () {
    [, $store] = signedInMember();
    $order = manualOrder($store);

    $this->put(route('orders.status', $order->id), ['status' => 'delivered'])->assertSessionHasErrors('status');
    $this->put(route('orders.status', $order->id), ['status' => 'processing'])->assertSessionHasNoErrors();
    $this->put(route('orders.status', $order->id), ['status' => 'shipped'])->assertSessionHasNoErrors();
    $this->put(route('orders.status', $order->id), ['status' => 'delivered'])->assertSessionHasNoErrors();

    $order = inStore($store, fn () => $order->fresh());
    expect($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->shipped_at)->not->toBeNull()
        ->and($order->paid_at)->not->toBeNull(); // cash on delivery counts as paid once delivered
});

test('FR-ORD-02 every status change is logged with who did it, and the shopper is emailed on shipped', function () {
    Notification::fake();
    [$user, $store] = signedInMember();
    $order = manualOrder($store);

    $this->put(route('orders.status', $order->id), ['status' => 'processing']);
    $this->put(route('orders.status', $order->id), ['status' => 'shipped', 'note' => 'Bosta pickup']);

    $history = inStore($store, fn () => $order->history()->get());
    expect($history->pluck('to_status')->map->value->all())->toBe(['pending', 'processing', 'shipped'])
        ->and($history->last()->actor_name)->toBe($user->name)
        ->and($history->last()->note)->toBe('Bosta pickup');

    Notification::assertSentTimes(OrderStatusChanged::class, 1);
    Notification::assertSentTo(new AnonymousNotifiable, OrderStatusChanged::class, function ($n, $channels, $notifiable) {
        return $n->status === OrderStatus::Shipped && $notifiable->routes['mail'] === 'mona@example.com';
    });
});

test('FR-ORD-03 cancelling an order puts its items back on the shelf, once', function () {
    [, $store] = signedInMember();
    $product = productIn($store, ['stock' => 5]);
    $order = manualOrder($store, ['product' => $product]);
    inStore($store, fn () => expect($product->fresh()->stock)->toBe(3));

    $this->put(route('orders.status', $order->id), ['status' => 'cancelled'])->assertSessionHasNoErrors();

    inStore($store, function () use ($product, $order) {
        expect($product->fresh()->stock)->toBe(5)
            ->and($product->fresh()->sold_count)->toBe(0)
            ->and($order->fresh()->stock_deducted)->toBeFalse()
            ->and(Customer::query()->sole()->total_spent)->toBe(0);
    });
});

test('FR-ORD-03 a returned order restocks too', function () {
    [, $store] = signedInMember();
    $product = productIn($store, ['stock' => 4]);
    $order = manualOrder($store, ['product' => $product, 'status' => 'processing']);

    foreach (['shipped', 'returned'] as $status) {
        $this->put(route('orders.status', $order->id), ['status' => $status])->assertSessionHasNoErrors();
    }

    inStore($store, fn () => expect($product->fresh()->stock)->toBe(4));
});

test('FR-ORD-10 bulk status moves the orders that can and skips the rest', function () {
    [, $store] = signedInMember();
    $a = manualOrder($store);
    $b = manualOrder($store);
    $this->put(route('orders.status', $b->id), ['status' => 'cancelled']);

    $this->post(route('orders.bulk'), ['ids' => [$a->id, $b->id], 'status' => 'processing'])
        ->assertSessionHas('success');

    inStore($store, function () use ($a, $b) {
        expect($a->fresh()->status)->toBe(OrderStatus::Processing)
            ->and($b->fresh()->status)->toBe(OrderStatus::Cancelled);
    });
});

test('FR-ORD-10 orders export to CSV with a BOM so Arabic names survive Excel', function () {
    [, $store] = signedInMember();
    manualOrder($store, ['customer_name' => 'منى عادل']);
    manualOrder($store, ['customer_name' => '=HYPERLINK("x")', 'phone' => '01112223334']);

    $response = $this->get(route('orders.export'));

    $response->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('منى عادل')
        ->and($csv)->toContain("'=HYPERLINK")
        ->and(substr_count(trim($csv), "\n"))->toBe(2);
});

test('FR-ORD-07 FR-ORD-11 courier, tracking link, notes and tags are saved', function () {
    [, $store] = signedInMember();
    $order = manualOrder($store);

    $this->put(route('orders.fulfilment', $order->id), ['courier_name' => 'Bosta', 'tracking_url' => 'https://bosta.co/track/123'])->assertSessionHasNoErrors();
    $this->put(route('orders.fulfilment', $order->id), ['tracking_url' => 'javascript:alert(1)'])->assertSessionHasErrors('tracking_url');
    $this->put(route('orders.notes', $order->id), ['notes' => 'Call after 6pm', 'tags' => ['VIP', 'vip', ' gift ']])->assertSessionHasNoErrors();

    $order = inStore($store, fn () => $order->fresh());
    expect($order->courier_name)->toBe('Bosta')
        ->and($order->tracking_url)->toBe('https://bosta.co/track/123')
        ->and($order->notes)->toBe('Call after 6pm')
        ->and($order->tags)->toBe(['VIP', 'gift']);
});

test('the orders list filters and searches, and the detail page shows next steps', function () {
    [, $store] = signedInMember();
    $order = manualOrder($store, ['customer_name' => 'Karim Samy']);
    manualOrder($store, ['customer_name' => 'Hana', 'phone' => '01223334445', 'status' => 'processing']);

    $this->get(route('orders.index', ['filter' => 'to_ship']))
        ->assertInertia(fn (Assert $page) => $page->component('orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.customer_name', 'Hana')
            ->where('counts.all', 2)
            ->where('counts.pending', 1));

    $this->get(route('orders.index', ['q' => (string) $order->number]))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.customer_name', 'Karim Samy'));

    $this->get(route('orders.show', $order->id))
        ->assertInertia(fn (Assert $page) => $page->component('orders/show')
            ->where('order.next', ['paid', 'processing', 'cancelled'])
            ->has('order.items', 1)
            ->has('order.history', 1)
            ->where('order.customer.orders_count', 1));
});

test('the home page shows today\'s sales and what is waiting to ship', function () {
    [, $store] = signedInMember();
    manualOrder($store, ['status' => 'processing']);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('sales.orders', 1)
        ->where('sales.amount', 176000)
        ->where('toShip.count', 1));
});

test('customers list and phone lookup', function () {
    [, $store] = signedInMember();
    manualOrder($store);

    $this->get(route('customers.index'))->assertInertia(fn (Assert $page) => $page->component('customers/index')
        ->has('customers.data', 1)
        ->where('customers.data.0.name', 'Mona Adel'));

    $this->getJson(route('customers.lookup', ['phone' => '0100 123 4567']))->assertJsonPath('customer.name', 'Mona Adel');
    $this->getJson(route('customers.lookup', ['phone' => '0199']))->assertJsonPath('customer', null);
});

test('an accountant can read orders but not change them', function () {
    [, $store] = signedInMember(Role::Accountant);
    $order = inStore($store, fn () => Order::query()->create(['number' => 1001, 'customer_name' => 'X', 'placed_at' => now()]));

    $this->get(route('orders.index'))->assertOk();
    $this->get(route('orders.create'))->assertForbidden();
    $this->put(route('orders.status', $order->id), ['status' => 'cancelled'])->assertForbidden();
});

test('another store\'s order is a 404', function () {
    [, $mine] = signedInMember();
    $other = Store::factory()->create();
    $order = inStore($other, fn () => Order::query()->create(['number' => 1001, 'customer_name' => 'X', 'placed_at' => now()]));

    $this->get(route('orders.show', $order->id))->assertNotFound();
    $this->put(route('orders.status', $order->id), ['status' => 'cancelled'])->assertNotFound();
});
