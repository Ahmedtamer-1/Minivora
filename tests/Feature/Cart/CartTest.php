<?php

use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Cart\Data\AddToCartData;
use App\Modules\Cart\Http\Controllers\CartController;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartEvent;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\ShippingZone;
use App\Modules\Platform\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

function cartStore(): Store
{
    $owner = User::factory()->create();
    $store = Store::factory()->withMember($owner)->create();
    app(CurrentStore::class)->set($store);

    return $store;
}

function liveProduct(array $overrides = []): Product
{
    return Product::factory()->create(array_merge([
        'name' => 'Leather Bag',
        'price' => 150000, // 1,500 EGP in piasters
        'stock' => 10,
        'status' => ProductStatus::Live,
    ], $overrides));
}

test('FR-CRT-01 guest cart persists by session and user cart persists by account', function () {
    cartStore();
    $service = app(CartService::class);
    $product = liveProduct();

    $guestCartFirst = $service->resolve(null, 'guest-session-123');
    $service->addItem($guestCartFirst, $product->id, 2);

    $guestCartSecond = $service->resolve(null, 'guest-session-123');
    expect($guestCartSecond->id)->toBe($guestCartFirst->id)
        ->and($guestCartSecond->items()->count())->toBe(1)
        ->and($guestCartSecond->items()->first()->quantity)->toBe(2);

    $user = User::factory()->create();
    $userCartFirst = $service->resolve($user, 'user-session-a');
    $service->addItem($userCartFirst, $product->id, 3);

    $userCartSecond = $service->resolve($user, 'user-session-b');
    expect($userCartSecond->id)->toBe($userCartFirst->id)
        ->and($userCartSecond->items()->first()->quantity)->toBe(3);
});

test('FR-CRT-01 guest cart merges into user cart on login capped at stock and deletes guest cart', function () {
    cartStore();
    $service = app(CartService::class);
    $user = User::factory()->create();

    $bag = liveProduct(['name' => 'Bag', 'price' => 100000, 'stock' => 5]);
    $belt = liveProduct(['name' => 'Belt', 'price' => 50000, 'stock' => 4]);

    $guestCart = $service->resolve(null, 'guest-merge-sess');
    $guestCart->update(['discount_code' => 'WELCOME10']);
    $service->addItem($guestCart, $bag->id, 4);
    $service->addItem($guestCart, $belt->id, 2);

    $userCart = $service->resolve($user, 'old-user-sess');
    $service->addItem($userCart, $bag->id, 3);

    $merged = $service->mergeGuestIntoUser('guest-merge-sess', $user);

    expect($merged->id)->toBe($userCart->id)
        ->and($merged->discount_code)->toBe('WELCOME10')
        ->and(Cart::query()->whereKey($guestCart->id)->exists())->toBeFalse();

    $bagItem = $merged->items->firstWhere('product_id', $bag->id);
    $beltItem = $merged->items->firstWhere('product_id', $belt->id);

    // 3 + 4 = 7, capped at stock = 5
    expect($bagItem->quantity)->toBe(5)
        ->and($beltItem->quantity)->toBe(2);
});

test('FR-CRT-02 add, quantity change, and remove each log an accurate CartEvent', function () {
    cartStore();
    $service = app(CartService::class);
    $product = liveProduct(['price' => 85000, 'stock' => 10]);

    $cart = $service->resolve(null, 'events-session');

    // 1. Add 2
    $item = $service->addItem($cart, $product->id, 2);

    // 2. Add 1 more -> quantity_change (+1 -> 3)
    $service->addItem($cart, $product->id, 1);

    // 3. Update quantity to 5 -> quantity_change (+2 -> 5)
    $service->updateQuantity($cart, $item->id, 5);

    // 4. Update quantity to 2 -> quantity_change (-3 -> 2)
    $service->updateQuantity($cart, $item->id, 2);

    // 5. Remove item -> remove (-2 -> 0)
    $service->removeItem($cart, $item->id);

    $events = CartEvent::query()->where('cart_id', $cart->id)->orderBy('id')->get();
    expect($events)->toHaveCount(5);

    expect($events[0]->event_type)->toBe('add')
        ->and($events[0]->delta)->toBe(2)
        ->and($events[0]->quantity_after)->toBe(2)
        ->and($events[0]->unit_price)->toBe(85000);

    expect($events[1]->event_type)->toBe('quantity_change')
        ->and($events[1]->delta)->toBe(1)
        ->and($events[1]->quantity_after)->toBe(3);

    expect($events[2]->event_type)->toBe('quantity_change')
        ->and($events[2]->delta)->toBe(2)
        ->and($events[2]->quantity_after)->toBe(5);

    expect($events[3]->event_type)->toBe('quantity_change')
        ->and($events[3]->delta)->toBe(-3)
        ->and($events[3]->quantity_after)->toBe(2);

    expect($events[4]->event_type)->toBe('remove')
        ->and($events[4]->delta)->toBe(-2)
        ->and($events[4]->quantity_after)->toBe(0);
});

test('FR-CRT-02 updating quantity to zero removes the item and logs a remove event', function () {
    cartStore();
    $service = app(CartService::class);
    $product = liveProduct(['stock' => 5]);
    $cart = $service->resolve(null, 'zero-qty-session');

    $item = $service->addItem($cart, $product->id, 2);
    $result = $service->updateQuantity($cart, $item->id, 0);

    expect($result)->toBeNull()
        ->and(CartItem::query()->whereKey($item->id)->exists())->toBeFalse();

    $removeEvent = CartEvent::query()->where('cart_id', $cart->id)->where('event_type', 'remove')->sole();
    expect($removeEvent->delta)->toBe(-2)
        ->and($removeEvent->quantity_after)->toBe(0);
});

test('FR-CRT-03 out-of-stock, exceeding-stock, and non-live adds are refused', function () {
    cartStore();
    $service = app(CartService::class);
    $cart = $service->resolve(null, 'stock-check-session');

    $outOfStock = liveProduct(['stock' => 0]);
    $limited = liveProduct(['stock' => 3]);
    $draft = liveProduct(['status' => ProductStatus::Draft, 'stock' => 10]);

    expect(fn () => $service->addItem($cart, $outOfStock->id, 1))
        ->toThrow(ValidationException::class);

    expect(fn () => $service->addItem($cart, $limited->id, 4))
        ->toThrow(ValidationException::class);

    expect(fn () => $service->addItem($cart, $draft->id, 1))
        ->toThrow(ValidationException::class);

    expect($cart->items()->count())->toBe(0)
        ->and(CartEvent::query()->count())->toBe(0);
});

test('FR-CRT-03 reconcile adjusts reduced stock, removes sold-out and unpublished items, and updates changed prices with notices', function () {
    cartStore();
    $service = app(CartService::class);
    $cart = $service->resolve(null, 'reconcile-session');

    $reducedStock = liveProduct(['name' => 'Canvas Tote', 'price' => 40000, 'stock' => 5]);
    $soldOut = liveProduct(['name' => 'Silk Scarf', 'price' => 60000, 'stock' => 2]);
    $priceChanged = liveProduct(['name' => 'Linen Shirt', 'price' => 90000, 'stock' => 10]);
    $archived = liveProduct(['name' => 'Draft Hat', 'price' => 30000, 'stock' => 4]);

    $service->addItem($cart, $reducedStock->id, 4);
    $service->addItem($cart, $soldOut->id, 2);
    $service->addItem($cart, $priceChanged->id, 1);
    $service->addItem($cart, $archived->id, 1);

    // Mutate catalog behind the scenes
    $reducedStock->update(['stock' => 2]);
    $soldOut->update(['stock' => 0]);
    $priceChanged->update(['price' => 110000]);
    $archived->update(['status' => ProductStatus::Draft]);

    $notices = $service->reconcile($cart);

    expect($notices)->toHaveCount(4);

    $byProduct = collect($notices)->keyBy('product_id');

    expect($byProduct[$reducedStock->id])->toMatchArray([
        'item_name' => 'Canvas Tote',
        'product_id' => $reducedStock->id,
        'reason' => 'stock_reduced',
        'old_value' => 4,
        'new_value' => 2,
    ])
        ->and($byProduct[$reducedStock->id]['message'])->toContain('Canvas Tote');

    expect($byProduct[$soldOut->id])->toMatchArray([
        'item_name' => 'Silk Scarf',
        'product_id' => $soldOut->id,
        'reason' => 'out_of_stock',
        'old_value' => 2,
        'new_value' => 0,
    ]);

    expect($byProduct[$priceChanged->id])->toMatchArray([
        'item_name' => 'Linen Shirt',
        'product_id' => $priceChanged->id,
        'reason' => 'price_changed',
        'old_value' => 90000,
        'new_value' => 110000,
    ]);

    expect($byProduct[$archived->id])->toMatchArray([
        'item_name' => 'Draft Hat',
        'product_id' => $archived->id,
        'reason' => 'unavailable',
        'old_value' => 1,
        'new_value' => 0,
    ]);

    $remaining = $cart->items()->get()->keyBy('product_id');
    expect($remaining)->toHaveCount(2)
        ->and($remaining[$reducedStock->id]->quantity)->toBe(2)
        ->and($remaining[$priceChanged->id]->unit_price_snapshot)->toBe(110000);
});

test('FR-CRT-04 summary computes subtotal, shipping estimate from ShippingZone, free shipping remaining, and total', function () {
    cartStore();
    $service = app(CartService::class);

    ShippingZone::query()->create([
        'governorate' => 'alexandria',
        'rate' => 7500, // 75 EGP
        'delivery_days' => 2,
        'sort_order' => 1,
    ]);

    $product = liveProduct(['name' => 'Leather Wallet', 'price' => 50000, 'stock' => 10]);
    $cart = $service->resolve(null, 'summary-session');
    $service->addItem($cart, $product->id, 2); // subtotal = 100,000 piasters (1,000 EGP)

    // Threshold 150,000 -> 50,000 remaining, shipping = 7,500, total = 107,500
    $summary = $service->summary($cart, 'alexandria', 150000);

    expect($summary['subtotal'])->toBe(100000)
        ->and($summary['shipping_estimate'])->toBe(7500)
        ->and($summary['free_shipping_remaining'])->toBe(50000)
        ->and($summary['total'])->toBe(107500)
        ->and($summary['notices'])->toBe([])
        ->and($summary['items'])->toHaveCount(1)
        ->and($summary['items'][0]['line_total'])->toBe(100000);

    // Add 1 more -> subtotal = 150,000, meets free shipping threshold -> remaining = 0, shipping = 0
    $service->addItem($cart, $product->id, 1);
    $freeSummary = $service->summary($cart, 'alexandria', 150000);

    expect($freeSummary['subtotal'])->toBe(150000)
        ->and($freeSummary['free_shipping_remaining'])->toBe(0)
        ->and($freeSummary['shipping_estimate'])->toBe(0)
        ->and($freeSummary['total'])->toBe(150000);
});

test('FR-CRT-02 AddToCartData and CartController handle show, store, update, and destroy', function () {
    cartStore();
    $product = liveProduct(['price' => 45000, 'stock' => 6]);
    $controller = app(CartController::class);

    $dto = AddToCartData::from(['product_id' => $product->id, 'quantity' => 2]);
    expect($dto->product_id)->toBe($product->id)
        ->and($dto->variant_id)->toBeNull()
        ->and($dto->quantity)->toBe(2);

    $session = app('session.store');
    $session->start();

    $makeRequest = function (array $payload = []) use ($session): Request {
        $request = Request::create('/cart', 'POST', $payload);
        $request->setLaravelSession($session);

        return $request;
    };

    $storeResponse = $controller->store($makeRequest(['product_id' => $product->id, 'quantity' => 2]));
    expect($storeResponse->getStatusCode())->toBe(201);
    $stored = $storeResponse->getData(true);
    $itemId = $stored['item']['id'];
    expect($stored['cart']['subtotal'])->toBe(90000);

    $updateResponse = $controller->update($makeRequest(['quantity' => 3]), $itemId);
    expect($updateResponse->getStatusCode())->toBe(200)
        ->and($updateResponse->getData(true)['cart']['subtotal'])->toBe(135000);

    $showResponse = $controller->show($makeRequest());
    expect($showResponse->getStatusCode())->toBe(200)
        ->and($showResponse->getData(true)['items'])->toHaveCount(1);

    $destroyResponse = $controller->destroy($makeRequest(), $itemId);
    expect($destroyResponse->getStatusCode())->toBe(200)
        ->and($destroyResponse->getData(true)['cart']['items'])->toBe([]);
});
