<?php

use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Checkout\Data\CheckoutData;
use App\Modules\Checkout\Http\Controllers\CheckoutController;
use App\Modules\Checkout\Http\Controllers\WebhookController;
use App\Modules\Checkout\Models\DiscountCode;
use App\Modules\Checkout\Models\Lead;
use App\Modules\Checkout\Models\Payment;
use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Checkout\Services\DiscountService;
use App\Modules\Checkout\Services\EgyptianPhone;
use App\Modules\Checkout\Services\RiskService;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\ShippingZone;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Platform\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

function checkoutStore(): array
{
    $owner = User::factory()->create();
    $store = Store::factory()->withMember($owner)->create();
    app(CurrentStore::class)->set($store);

    return [$store, $owner];
}

function checkoutProduct(array $overrides = []): Product
{
    return Product::factory()->create(array_merge([
        'name' => 'Cotton T-Shirt',
        'price' => 50000, // 500 EGP in piasters
        'cost' => 20000,
        'stock' => 20,
        'status' => ProductStatus::Live,
    ], $overrides));
}

function signedWebhookRequest(string $headerName, string $algo, array $payload, string $secret, array $extraHeaders = []): Request
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac($algo, $body, $secret);

    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_'.strtoupper(str_replace('-', '_', $headerName)) => $signature,
    ];

    foreach ($extraHeaders as $k => $v) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = (string) $v;
    }

    return Request::create('/webhooks/test', 'POST', [], [], [], $server, $body);
}

test('FR-PAY-02 normalizes valid Egyptian mobile numbers and rejects invalid ones', function () {
    expect(EgyptianPhone::normalize('01012345678'))->toBe('01012345678')
        ->and(EgyptianPhone::normalize('+20 111-234-5678'))->toBe('01112345678')
        ->and(EgyptianPhone::normalize('0020 12 2345 6789'))->toBe('01223456789')
        ->and(EgyptianPhone::normalize('+201555555555'))->toBe('01555555555')
        ->and(EgyptianPhone::isValid('01012345678'))->toBeTrue()
        ->and(EgyptianPhone::isValid('01312345678'))->toBeFalse();

    // Invalid prefix 013
    expect(fn () => EgyptianPhone::normalize('01312345678'))->toThrow(ValidationException::class);
    // Too short
    expect(fn () => EgyptianPhone::normalize('0101234567'))->toThrow(ValidationException::class);
    // Too long
    expect(fn () => EgyptianPhone::normalize('010123456789'))->toThrow(ValidationException::class);
    // Non-Egyptian country code
    expect(fn () => EgyptianPhone::normalize('+14155551234'))->toThrow(ValidationException::class);
});

test('FR-PAY-01 guest single-page checkout places an order without an account and deducts stock', function () {
    checkoutStore();
    $cartService = app(CartService::class);
    $checkout = app(CheckoutService::class);

    $product = checkoutProduct(['price' => 45000, 'stock' => 5]);
    $cart = $cartService->resolve(null, 'guest-checkout-sess');
    $cartService->addItem($cart, $product->id, 2);

    $data = CheckoutData::from([
        'name' => 'Salma Hassan',
        'phone' => '+20 100 123 4567',
        'email' => 'salma@example.com',
        'governorate' => 'cairo',
        'address' => '15 Zamalek St, Cairo',
        'payment_method' => 'cod',
    ]);

    $result = $checkout->placeOrder($data, $cart, '41.44.10.20');

    expect($result['order'])->toBeInstanceOf(Order::class)
        ->and($result['order']->phone)->toBe('01001234567')
        ->and($result['order']->customer_name)->toBe('Salma Hassan')
        ->and($result['order']->email)->toBe('salma@example.com')
        ->and($result['order']->governorate)->toBe('cairo')
        ->and($result['order']->subtotal)->toBe(90000)
        ->and($result['order']->shipping_fee)->toBe(6000)
        ->and($result['order']->total)->toBe(96000)
        ->and($result['order']->status)->toBe(OrderStatus::Pending)
        ->and($result['order']->items)->toHaveCount(1)
        ->and($product->fresh()->stock)->toBe(3)
        ->and($cart->items()->count())->toBe(0);
});

test('FR-PAY-03 shipping fee comes from store governorate zone and honors free-shipping threshold', function () {
    checkoutStore();
    ShippingZone::query()->create([
        'governorate' => 'alexandria',
        'rate' => 8500,
        'delivery_days' => 3,
        'sort_order' => 1,
    ]);

    $cartService = app(CartService::class);
    $checkout = app(CheckoutService::class);
    $product = checkoutProduct(['price' => 60000, 'stock' => 10]);

    // Below threshold: pays custom store zone rate (8500)
    $cart1 = $cartService->resolve(null, 'ship-1');
    $cartService->addItem($cart1, $product->id, 1);
    $res1 = $checkout->placeOrder(CheckoutData::from([
        'name' => 'Omar',
        'phone' => '01011111111',
        'governorate' => 'alexandria',
        'address' => 'Smouha',
        'payment_method' => 'cod',
        'free_shipping_threshold' => 100000,
    ]), $cart1);
    expect($res1['order']->shipping_fee)->toBe(8500)
        ->and($res1['order']->total)->toBe(68500);

    // At/above threshold: free shipping (0)
    $cart2 = $cartService->resolve(null, 'ship-2');
    $cartService->addItem($cart2, $product->id, 2);
    $res2 = $checkout->placeOrder(CheckoutData::from([
        'name' => 'Omar',
        'phone' => '01011111111',
        'governorate' => 'alexandria',
        'address' => 'Smouha',
        'payment_method' => 'cod',
        'free_shipping_threshold' => 100000,
    ]), $cart2);
    expect($res2['order']->shipping_fee)->toBe(0)
        ->and($res2['order']->total)->toBe(120000);
});

test('FR-PAY-05 COD supports optional cod_fee and posts balanced journal entry when delivered', function () {
    checkoutStore();
    $cartService = app(CartService::class);
    $checkout = app(CheckoutService::class);
    $orders = app(OrderService::class);

    $product = checkoutProduct(['price' => 50000, 'stock' => 10]);
    $cart = $cartService->resolve(null, 'cod-fee-sess');
    $cartService->addItem($cart, $product->id, 1);

    $result = $checkout->placeOrder(CheckoutData::from([
        'name' => 'Mona',
        'phone' => '01122222222',
        'governorate' => 'cairo',
        'address' => 'Maadi',
        'payment_method' => 'cod',
        'cod_fee' => 1500, // 15 EGP COD fee
    ]), $cart);

    expect($result['payment']->gateway)->toBe('cod')
        ->and($result['payment']->mode)->toBe('test')
        ->and($result['payment']->status)->toBe('pending')
        ->and($result['payment']->cod_fee)->toBe(1500)
        ->and($result['order']->shipping_fee)->toBe(7500) // 6000 Cairo + 1500 COD fee
        ->and($result['order']->total)->toBe(57500)
        ->and($result['payment']->amount)->toBe(57500);

    // Transition COD order to delivered -> posts balanced sale journal entry
    $order = $result['order'];
    $orders->changeStatus($order, OrderStatus::Processing);
    $orders->changeStatus($order, OrderStatus::Shipped);
    $orders->changeStatus($order, OrderStatus::Delivered);

    $saleEntry = JournalEntry::query()->where('source_key', "order:{$order->id}:sale")->first();
    expect($saleEntry)->not->toBeNull()
        ->and($saleEntry->isBalanced())->toBeTrue()
        ->and($saleEntry->total_debit)->toBe(57500);
});

test('FR-PAY-06 InstaPay stores receipt, keeps order pending until staff verifies, and is idempotent', function () {
    [, $staff] = checkoutStore();
    $cartService = app(CartService::class);
    $checkout = app(CheckoutService::class);

    $product = checkoutProduct(['price' => 80000, 'stock' => 5]);
    $cart = $cartService->resolve(null, 'instapay-sess');
    $cartService->addItem($cart, $product->id, 1);

    $result = $checkout->placeOrder(CheckoutData::from([
        'name' => 'Karim',
        'phone' => '01233333333',
        'governorate' => 'cairo',
        'address' => 'Nasr City',
        'payment_method' => 'instapay',
        'receipt_path' => 'receipts/2026/instapay-1001.jpg',
    ]), $cart);

    $order = $result['order'];
    $payment = $result['payment'];

    expect($payment->gateway)->toBe('instapay')
        ->and($payment->mode)->toBe('test')
        ->and($payment->status)->toBe('pending')
        ->and($payment->receipt_path)->toBe('receipts/2026/instapay-1001.jpg')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and(JournalEntry::query()->where('source_id', $order->id)->count())->toBe(0);

    // Staff verifies receipt
    $verified = $checkout->verifyInstaPay($payment, $staff);
    expect($verified->status)->toBe('paid')
        ->and($verified->paid_at)->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(JournalEntry::query()->where('source_key', "order:{$order->id}:sale")->count())->toBe(1);

    // Re-verifying is idempotent (still 1 journal entry)
    $verifiedAgain = $checkout->verifyInstaPay($verified, $staff);
    expect($verifiedAgain->id)->toBe($verified->id)
        ->and(JournalEntry::query()->where('source_key', "order:{$order->id}:sale")->count())->toBe(1);
});

test('FR-PAY-08 Fawry generates deterministic 10-digit reference code in test mode and settles via webhook', function () {
    checkoutStore();
    $cartService = app(CartService::class);
    $checkout = app(CheckoutService::class);

    $product = checkoutProduct(['price' => 40000, 'stock' => 5]);
    $cart = $cartService->resolve(null, 'fawry-sess');
    $cartService->addItem($cart, $product->id, 1);

    $result = $checkout->placeOrder(CheckoutData::from([
        'name' => 'Hossam',
        'phone' => '01544444444',
        'governorate' => 'giza',
        'address' => 'Dokki',
        'payment_method' => 'fawry',
    ]), $cart);

    $payment = $result['payment'];
    expect($payment->gateway)->toBe('fawry')
        ->and($payment->mode)->toBe('test')
        ->and($payment->external_reference)->toMatch('/^\d{10}$/')
        ->and($payment->payload['reference_code'])->toBe($payment->external_reference);

    $secret = 'fawry-test-secret';
    $req = signedWebhookRequest('X-Fawry-Signature', 'sha256', [
        'event_id' => 'evt-fawry-001',
        'reference_code' => $payment->external_reference,
        'order_id' => $result['order']->id,
        'status' => 'PAID',
        'amount' => $payment->amount,
        'gateway_fee' => 500,
    ], $secret);

    $settled = $checkout->handleWebhook('fawry', $req, $secret);
    expect($settled->status)->toBe('paid')
        ->and($settled->gateway_fee)->toBe(500)
        ->and($result['order']->fresh()->status)->toBe(OrderStatus::Paid);
});

test('FR-PAY-10 lead capture normalizes phone, upserts open lead, and marks converted on order placement', function () {
    checkoutStore();
    $cartService = app(CartService::class);
    $checkout = app(CheckoutService::class);

    $lead1 = $checkout->captureLead('+20 109 876 5432', [
        'name' => 'Nour',
        'governorate' => 'cairo',
        'cart_snapshot' => ['subtotal' => 50000],
    ]);

    $lead2 = $checkout->captureLead('01098765432', [
        'email' => 'nour@example.com',
    ]);

    expect($lead2->id)->toBe($lead1->id)
        ->and($lead2->phone)->toBe('01098765432')
        ->and($lead2->name)->toBe('Nour')
        ->and($lead2->email)->toBe('nour@example.com')
        ->and(Lead::query()->count())->toBe(1);

    $product = checkoutProduct();
    $cart = $cartService->resolve(null, 'lead-convert-sess');
    $cartService->addItem($cart, $product->id, 1);

    $res = $checkout->placeOrder(CheckoutData::from([
        'name' => 'Nour',
        'phone' => '00201098765432',
        'governorate' => 'cairo',
        'address' => 'Heliopolis',
        'payment_method' => 'cod',
    ]), $cart);

    expect($lead1->fresh()->converted_order_id)->toBe($res['order']->id);
});

test('FR-PAY-11 FR-CRT-05 discount codes support percentage half-up rounding, fixed, free shipping, and enforce limits', function () {
    checkoutStore();
    $discounts = app(DiscountService::class);
    $cartService = app(CartService::class);
    $checkout = app(CheckoutService::class);

    // Percentage with half-up rounding: 10% of 1005 piasters = 100.5 -> 101 piasters
    DiscountCode::query()->create([
        'code' => 'WELCOME10',
        'type' => DiscountCode::TYPE_PERCENTAGE,
        'value' => 10,
        'min_order_amount' => 1000,
        'max_uses' => 2,
    ]);
    $pct = $discounts->apply('welcome10', 1005, 6000);
    expect($pct['discount_amount'])->toBe(101)
        ->and($pct['free_shipping'])->toBeFalse();

    // Fixed amount discount
    DiscountCode::query()->create([
        'code' => 'SAVE50',
        'type' => DiscountCode::TYPE_FIXED,
        'value' => 5000,
    ]);
    $fixed = $discounts->apply('SAVE50', 50000, 6000);
    expect($fixed['discount_amount'])->toBe(5000)
        ->and($fixed['free_shipping'])->toBeFalse();

    // Free shipping discount
    DiscountCode::query()->create([
        'code' => 'FREESHIP',
        'type' => DiscountCode::TYPE_FREE_SHIPPING,
        'value' => 0,
    ]);
    $fs = $discounts->apply('FREESHIP', 50000, 6000);
    expect($fs['discount_amount'])->toBe(0)
        ->and($fs['free_shipping'])->toBeTrue();

    // Validation failures: inactive, not started, expired, min order, max uses
    DiscountCode::query()->create(['code' => 'OFF', 'type' => 'fixed', 'value' => 1000, 'is_active' => false]);
    expect(fn () => $discounts->apply('OFF', 50000, 6000))->toThrow(ValidationException::class);

    DiscountCode::query()->create(['code' => 'FUTURE', 'type' => 'fixed', 'value' => 1000, 'starts_at' => Carbon::now()->addDay()]);
    expect(fn () => $discounts->apply('FUTURE', 50000, 6000))->toThrow(ValidationException::class);

    DiscountCode::query()->create(['code' => 'EXPIRED', 'type' => 'fixed', 'value' => 1000, 'expires_at' => Carbon::now()->subMinute()]);
    expect(fn () => $discounts->apply('EXPIRED', 50000, 6000))->toThrow(ValidationException::class);

    expect(fn () => $discounts->apply('WELCOME10', 500, 6000))->toThrow(ValidationException::class);

    DiscountCode::query()->create(['code' => 'MAXED', 'type' => 'fixed', 'value' => 1000, 'max_uses' => 1, 'uses_count' => 1]);
    expect(fn () => $discounts->apply('MAXED', 50000, 6000))->toThrow(ValidationException::class);

    // End-to-end checkout applies discount and increments uses_count
    $product = checkoutProduct(['price' => 50000, 'stock' => 10]);
    $cart = $cartService->resolve(null, 'disc-checkout');
    $cartService->addItem($cart, $product->id, 1);

    $res = $checkout->placeOrder(CheckoutData::from([
        'name' => 'Dina',
        'phone' => '01055555555',
        'governorate' => 'cairo',
        'address' => '6th October',
        'payment_method' => 'cod',
        'discount_code' => 'WELCOME10',
    ]), $cart);

    expect($res['order']->discount)->toBe(5000)
        ->and($res['order']->total)->toBe(51000) // 50000 + 6000 - 5000
        ->and(DiscountCode::query()->where('code', 'WELCOME10')->value('uses_count'))->toBe(1);
});

test('FR-PAY-12 risk service flags prior refused COD, >3 orders in 24h by phone or IP, and >5x 30-day AOV', function () {
    checkoutStore();
    $risk = app(RiskService::class);
    $cartService = app(CartService::class);
    $checkout = app(CheckoutService::class);

    // Baseline: clean phone, no prior orders
    expect($risk->evaluate('01066666666', 50000, '10.0.0.1'))->toBe([
        'flagged' => false,
        'reasons' => [],
    ]);

    // Rule (a): prior cancelled COD order
    Order::query()->create([
        'number' => 1001,
        'status' => OrderStatus::Cancelled,
        'channel' => 'website',
        'payment_method' => 'cod',
        'customer_name' => 'Bad Actor',
        'phone' => '01077777777',
        'subtotal' => 20000,
        'shipping_fee' => 6000,
        'discount' => 0,
        'total' => 26000,
        'placed_at' => Carbon::now()->subDays(5),
    ]);
    $ruleA = $risk->evaluate('01077777777', 26000);
    expect($ruleA['flagged'])->toBeTrue()
        ->and($ruleA['reasons'])->toContain('prior_refused_cod');

    // Rule (b): >3 orders from same phone or IP within 24h
    for ($i = 0; $i < 4; $i++) {
        Order::query()->create([
            'number' => 1010 + $i,
            'status' => OrderStatus::Delivered,
            'channel' => 'website',
            'payment_method' => 'card',
            'customer_name' => 'Fast Buyer',
            'phone' => '01088888888',
            'subtotal' => 10000,
            'shipping_fee' => 0,
            'discount' => 0,
            'total' => 10000,
            'notes' => '[ip:192.168.1.99]',
            'placed_at' => Carbon::now()->subHours(2),
        ]);
    }
    $ruleB = $risk->evaluate('01088888888', 10000, '192.168.1.99');
    expect($ruleB['flagged'])->toBeTrue()
        ->and($ruleB['reasons'])->toContain('high_velocity_phone')
        ->and($ruleB['reasons'])->toContain('high_velocity_ip');

    // Rule (c): order total > 5x 30-day store AOV (AOV is 10000 above, so > 50000 triggers)
    $ruleC = $risk->evaluate('01099999999', 55000);
    expect($ruleC['flagged'])->toBeTrue()
        ->and($ruleC['reasons'])->toContain('high_order_value');

    // Custom threshold override
    $custom = $risk->evaluate('01099999999', 55000, null, ['aov_multiplier' => 10]);
    expect($custom['flagged'])->toBeFalse();

    // End-to-end checkout tags flagged order with ['risk_review']
    $product = checkoutProduct(['price' => 60000, 'stock' => 10]);
    $cart = $cartService->resolve(null, 'risk-tag-sess');
    $cartService->addItem($cart, $product->id, 1);

    $res = $checkout->placeOrder(CheckoutData::from([
        'name' => 'High Value',
        'phone' => '01077777777', // also triggers prior_refused_cod
        'governorate' => 'cairo',
        'address' => 'New Cairo',
        'payment_method' => 'cod',
    ]), $cart);

    expect($res['risk']['flagged'])->toBeTrue()
        ->and($res['order']->tags)->toBe(['risk_review']);
});

test('V-04 FR-PAY-04 FR-PAY-07 FR-PAY-09 NFR-SEC-14 NFR-OPS-03 Kashier and Paymob test mode, invalid HMAC 403, stale timestamp 403, and idempotent replayed/delayed webhooks', function () {
    checkoutStore();
    $cartService = app(CartService::class);
    $checkout = app(CheckoutService::class);

    $product = checkoutProduct(['price' => 100000, 'stock' => 20]);

    // 1. Kashier test mode checkout
    $kashierCart = $cartService->resolve(null, 'v04-kashier');
    $cartService->addItem($kashierCart, $product->id, 1);
    $kashierRes = $checkout->placeOrder(CheckoutData::from([
        'name' => 'Tarek',
        'phone' => '01012345678',
        'governorate' => 'cairo',
        'address' => 'Garden City',
        'payment_method' => 'kashier',
    ]), $kashierCart);

    $kashierOrder = $kashierRes['order'];
    $kashierPayment = $kashierRes['payment'];

    expect($kashierPayment->gateway)->toBe('kashier')
        ->and($kashierPayment->mode)->toBe('test')
        ->and($kashierPayment->status)->toBe('pending')
        ->and($kashierPayment->payload['checkout_url'])->toContain('mode=test');

    $kashierSecret = 'kashier-test-secret';

    // Invalid HMAC rejected with 403
    $badReq = Request::create(
        '/webhooks/kashier',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_KASHIER_SIGNATURE' => 'invalid-hmac'],
        json_encode(['event_id' => 'evt-bad', 'external_reference' => $kashierPayment->external_reference, 'paid' => true]),
    );
    expect(fn () => $checkout->handleWebhook('kashier', $badReq, $kashierSecret))
        ->toThrow(fn (HttpException $e) => $e->getStatusCode() === 403);

    // NFR-SEC-14: unseen webhook with stale timestamp (> 5 minutes old) rejected with 403
    $staleReq = signedWebhookRequest(
        'X-Kashier-Signature',
        'sha256',
        [
            'event_id' => 'evt-ksh-stale',
            'external_reference' => $kashierPayment->external_reference,
            'order_id' => $kashierOrder->id,
            'status' => 'SUCCESS',
            'amount' => $kashierPayment->amount,
            'gateway_fee' => 2500,
        ],
        $kashierSecret,
        ['X-Webhook-Timestamp' => Carbon::now()->subMinutes(10)->timestamp],
    );
    expect(fn () => $checkout->handleWebhook('kashier', $staleReq, $kashierSecret))
        ->toThrow(fn (HttpException $e) => $e->getStatusCode() === 403);

    // First valid Kashier webhook
    $validPayload = [
        'event_id' => 'evt-ksh-001',
        'external_reference' => $kashierPayment->external_reference,
        'order_id' => $kashierOrder->id,
        'status' => 'SUCCESS',
        'amount' => $kashierPayment->amount,
        'gateway_fee' => 2500,
    ];
    $firstReq = signedWebhookRequest(
        'X-Kashier-Signature',
        'sha256',
        $validPayload,
        $kashierSecret,
        ['X-Webhook-Timestamp' => Carbon::now()->timestamp],
    );

    $paid1 = $checkout->handleWebhook('kashier', $firstReq, $kashierSecret);
    expect($paid1->status)->toBe('paid')
        ->and($paid1->gateway_fee)->toBe(2500)
        ->and($paid1->webhook_event_id)->toBe('evt-ksh-001')
        ->and($kashierOrder->fresh()->status)->toBe(OrderStatus::Paid);

    $kashierEntries = JournalEntry::query()->where('source_id', $kashierOrder->id)->get();
    expect($kashierEntries)->toHaveCount(2); // 1 sale + 1 fee, both balanced
    foreach ($kashierEntries as $entry) {
        expect($entry->isBalanced())->toBeTrue();
    }

    // Immediate replayed webhook (same event_id) -> idempotent, 0 duplicate writes
    $replayReq = signedWebhookRequest('X-Kashier-Signature', 'sha256', $validPayload, $kashierSecret);
    $paid2 = $checkout->handleWebhook('kashier', $replayReq, $kashierSecret);
    expect($paid2->id)->toBe($paid1->id)
        ->and(JournalEntry::query()->where('source_id', $kashierOrder->id)->count())->toBe(2);

    // Delayed duplicate webhook (different event_id, payment already paid) -> idempotent, 0 duplicate writes
    $delayedPayload = array_merge($validPayload, ['event_id' => 'evt-ksh-delayed-dup']);
    $delayedReq = signedWebhookRequest('X-Kashier-Signature', 'sha256', $delayedPayload, $kashierSecret);
    $paid3 = $checkout->handleWebhook('kashier', $delayedReq, $kashierSecret);
    expect($paid3->id)->toBe($paid1->id)
        ->and($paid3->webhook_event_id)->toBe('evt-ksh-001')
        ->and(JournalEntry::query()->where('source_id', $kashierOrder->id)->count())->toBe(2);

    // 2. Paymob test mode checkout + HMAC-SHA512 webhook
    $paymobCart = $cartService->resolve(null, 'v04-paymob');
    $cartService->addItem($paymobCart, $product->id, 1);
    $paymobRes = $checkout->placeOrder(CheckoutData::from([
        'name' => 'Youssef',
        'phone' => '01112345678',
        'governorate' => 'cairo',
        'address' => 'Mohandessin',
        'payment_method' => 'paymob',
    ]), $paymobCart);

    $paymobOrder = $paymobRes['order'];
    $paymobPayment = $paymobRes['payment'];
    expect($paymobPayment->gateway)->toBe('paymob')
        ->and($paymobPayment->mode)->toBe('test');

    $paymobSecret = 'paymob-test-secret';
    $paymobReq = signedWebhookRequest('X-Paymob-Signature', 'sha512', [
        'event_id' => 'evt-pmb-001',
        'merchant_order_id' => $paymobPayment->external_reference,
        'order_id' => $paymobOrder->id,
        'success' => true,
        'amount_cents' => $paymobPayment->amount,
        'gateway_fee' => 3000,
    ], $paymobSecret);

    $paymobPaid = $checkout->handleWebhook('paymob', $paymobReq, $paymobSecret);
    expect($paymobPaid->status)->toBe('paid')
        ->and($paymobPaid->gateway_fee)->toBe(3000)
        ->and($paymobOrder->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(JournalEntry::query()->where('source_id', $paymobOrder->id)->count())->toBe(2);

    // NFR-OPS-03: 0 live/real transactions across all payments
    expect(Payment::query()->where('mode', '!=', Payment::MODE_TEST)->count())->toBe(0);
});

test('FR-PAY-01 FR-PAY-09 CheckoutController and WebhookController handle requests end-to-end', function () {
    [, $staff] = checkoutStore();
    $cartService = app(CartService::class);
    $product = checkoutProduct(['price' => 50000, 'stock' => 10]);

    $session = app('session.store');
    $session->start();
    $cart = $cartService->resolve(null, $session->getId());
    $cartService->addItem($cart, $product->id, 1);

    $checkoutController = app(CheckoutController::class);

    $showReq = Request::create('/checkout', 'GET', ['governorate' => 'cairo']);
    $showReq->headers->set('Accept', 'application/json');
    $showReq->setLaravelSession($session);
    $showResp = $checkoutController->show($showReq);
    expect($showResp->getStatusCode())->toBe(200)
        ->and($showResp->getData(true)['payment_methods'])->toBe(CheckoutData::PAYMENT_METHODS);

    $leadReq = Request::create('/checkout/lead', 'POST', [
        'phone' => '+201012345678',
        'name' => 'Laila',
    ]);
    $leadResp = $checkoutController->captureLead($leadReq);
    expect($leadResp->getStatusCode())->toBe(201)
        ->and($leadResp->getData(true)['lead']['phone'])->toBe('01012345678');

    $storeReq = Request::create('/checkout', 'POST', [
        'name' => 'Laila',
        'phone' => '01012345678',
        'governorate' => 'cairo',
        'address' => 'Zamalek',
        'payment_method' => 'kashier',
    ]);
    $storeReq->setLaravelSession($session);
    $storeReq->headers->set('Accept', 'application/json');
    $storeResp = $checkoutController->store($storeReq);
    expect($storeResp->getStatusCode())->toBe(201);

    $paymentData = $storeResp->getData(true)['payment'];
    config(['services.kashier.webhook_secret' => 'ctrl-secret']);

    $webhookReq = signedWebhookRequest('X-Kashier-Signature', 'sha256', [
        'event_id' => 'evt-ctrl-001',
        'external_reference' => $paymentData['external_reference'],
        'order_id' => $paymentData['order_id'],
        'status' => 'SUCCESS',
        'amount' => $paymentData['amount'],
        'gateway_fee' => 1200,
    ], 'ctrl-secret');

    $webhookController = app(WebhookController::class);
    $webhookResp = $webhookController->handle($webhookReq, 'kashier');
    expect($webhookResp->getStatusCode())->toBe(200)
        ->and($webhookResp->getData(true)['payment_status'])->toBe('paid');
});
