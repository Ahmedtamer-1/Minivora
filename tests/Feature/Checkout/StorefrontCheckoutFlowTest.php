<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Platform\Models\Store;

test('FR-PAY-01 FR-NTF-01 the checkout form places the order and shows the confirmation page', function () {
    $store = Store::factory()->create(['subdomain' => 'buyme', 'published_at' => now()]);
    $product = Product::factory()->live()->create(['store_id' => $store->id, 'name' => 'Leather Bag', 'price' => 125000, 'stock' => 5]);

    // One browser: keep the same session cookie across requests, as a real shopper would.
    $this->withCredentials()->withCookie(config('session.cookie'), str_repeat('a', 40));

    $this->postJson(storeUrl('buyme', '/cart/items'), ['product_id' => $product->id, 'quantity' => 2])->assertCreated();
    $this->get(storeUrl('buyme', '/en/checkout'))->assertOk()->assertSee('name="locale"', false);

    $response = $this->post(storeUrl('buyme', '/checkout'), [
        'name' => 'Ahmed Mohamed',
        'phone' => '01012345678',
        'governorate' => 'cairo',
        'address' => '12 Tahrir St, Downtown',
        'payment_method' => 'cod',
        'locale' => 'en',
    ]);

    $order = Order::query()->withoutGlobalScopes()->where('store_id', $store->id)->firstOrFail();
    $response->assertRedirect('/en/orders/'.$order->number.'/thank-you');

    $this->get(storeUrl('buyme', '/en/orders/'.$order->number.'/thank-you'))
        ->assertOk()
        ->assertSee((string) $order->number)
        ->assertSee('Leather Bag')
        ->assertSee('/en/track-order?order='.$order->number, false);

    // Another browser cannot open someone else's confirmation.
    $this->flushSession();
    $this->withCookie(config('session.cookie'), str_repeat('b', 40));
    $this->get(storeUrl('buyme', '/en/orders/'.$order->number.'/thank-you'))->assertNotFound();
});

test('FR-PAY-01 checkout form errors go back to the form instead of returning JSON', function () {
    Store::factory()->create(['subdomain' => 'empty-cart', 'published_at' => now()]);

    $this->from(storeUrl('empty-cart', '/en/checkout'))
        ->post(storeUrl('empty-cart', '/checkout'), ['name' => 'A', 'phone' => '01012345678', 'governorate' => 'cairo', 'address' => 'x', 'payment_method' => 'cod'])
        ->assertRedirect(storeUrl('empty-cart', '/en/checkout'))
        ->assertSessionHasErrors();
});

test('FR-ORD-05 the track-order page renders in the store layout', function () {
    Store::factory()->create(['subdomain' => 'tracker', 'published_at' => now()]);

    $this->get(storeUrl('tracker', '/ar/track-order?order=1042'))->assertOk()->assertSee('value="1042"', false)->assertSee('dir="rtl"', false);
});
