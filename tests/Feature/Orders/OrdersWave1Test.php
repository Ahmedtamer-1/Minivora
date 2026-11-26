<?php

use App\Core\Tenancy\CurrentStore;
use App\Modules\Catalog\Models\Product;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Services\ChartOfAccounts;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Http\Controllers\ReturnController;
use App\Modules\Orders\Http\Controllers\ShippingZoneController;
use App\Modules\Orders\Http\Controllers\TrackOrderController;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Refund;
use App\Modules\Orders\Models\ReturnRequest;
use App\Modules\Orders\Models\ShippingZone;
use App\Modules\Orders\Services\InvoiceService;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\ReturnService;
use App\Modules\Orders\Services\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

test('FR-ORD-01 partial refund posts a balanced journal entry without changing order status', function () {
    [$owner, $store] = signedInMember();

    app(CurrentStore::class)->runAs($store, function () use ($owner) {
        $product = Product::factory()->live()->create(['name' => 'Cotton Jacket', 'price' => 100000, 'cost' => 40000, 'stock' => 10]);
        $orders = app(OrderService::class);
        $returns = app(ReturnService::class);

        $order = $orders->createManual([
            'customer_name' => 'Nadia Hassan',
            'phone' => '01005556677',
            'email' => 'nadia@example.com',
            'governorate' => 'cairo',
            'channel' => 'website',
            'payment_method' => 'instapay',
            'status' => 'paid',
        ], [['product_id' => $product->id, 'quantity' => 2]], $owner);

        $orders->changeStatus($order, OrderStatus::Processing, $owner);
        $orders->changeStatus($order, OrderStatus::Shipped, $owner);
        $orders->changeStatus($order, OrderStatus::Delivered, $owner);

        $order = $order->fresh();
        expect($order->status)->toBe(OrderStatus::Delivered);

        $refund = $returns->issuePartialRefund($order, 50000, 'Minor stitching defect', $owner);

        expect($refund)->toBeInstanceOf(Refund::class)
            ->and($refund->is_partial)->toBeTrue()
            ->and($refund->amount)->toBe(50000)
            ->and($refund->journal_entry_id)->not->toBeNull()
            ->and($order->fresh()->status)->toBe(OrderStatus::Delivered);

        $entry = JournalEntry::query()->with('lines.account')->findOrFail($refund->journal_entry_id);
        expect($entry->total_debit)->toBe(50000)
            ->and($entry->total_credit)->toBe(50000)
            ->and($entry->total_debit)->toBe($entry->total_credit);

        $debitLine = $entry->lines->firstWhere('debit', '>', 0);
        $creditLine = $entry->lines->firstWhere('credit', '>', 0);
        expect($debitLine->account->code)->toBe(ChartOfAccounts::RETURNS)
            ->and($debitLine->debit)->toBe(50000)
            ->and($creditLine->account->code)->toBe(ChartOfAccounts::BANK)
            ->and($creditLine->credit)->toBe(50000);
    });
});

test('FR-ORD-05 public order tracking matches by order number + phone or email, hides internal notes, and rejects mismatch', function () {
    [$owner, $store] = signedInMember();

    app(CurrentStore::class)->runAs($store, function () use ($owner) {
        $product = Product::factory()->live()->create(['name' => 'Silk Scarf', 'price' => 45000, 'stock' => 5]);
        $orders = app(OrderService::class);
        $controller = app(TrackOrderController::class);

        $order = $orders->createManual([
            'customer_name' => 'Salma Tarek',
            'phone' => '+20 101 234 5678',
            'email' => 'Salma@Example.com',
            'governorate' => 'cairo',
            'channel' => 'website',
            'payment_method' => 'cod',
            'notes' => 'SECRET INTERNAL NOTE: VIP customer, fragile box',
        ], [['product_id' => $product->id, 'quantity' => 1]], $owner);

        $orders->updateFulfilment($order, 'Bosta', 'https://bosta.co/track/ABC999');
        $orders->changeStatus($order, OrderStatus::Processing, $owner);
        $orders->changeStatus($order, OrderStatus::Shipped, $owner);

        // Match by phone (formatted with +20 and spaces)
        $byPhone = $controller->lookup(Request::create('/track', 'POST', [
            'order_number' => '#'.$order->number,
            'contact' => '0101 234 5678',
        ]));

        expect($byPhone->getStatusCode())->toBe(200);
        $payload = $byPhone->getData(true)['order'];
        expect($payload['number'])->toBe($order->number)
            ->and($payload['status'])->toBe('shipped')
            ->and($payload['courier_name'])->toBe('Bosta')
            ->and($payload['tracking_url'])->toBe('https://bosta.co/track/ABC999')
            ->and($payload['items'])->toHaveCount(1)
            ->and($payload['history'])->toHaveCount(3)
            ->and(array_key_exists('notes', $payload))->toBeFalse()
            ->and($byPhone->getContent())->not->toContain('SECRET INTERNAL NOTE');

        // Match by email (case-insensitive)
        $byEmail = $controller->lookup(Request::create('/track', 'POST', [
            'order_number' => (string) $order->number,
            'contact' => 'salma@example.com',
        ]));
        expect($byEmail->getStatusCode())->toBe(200)
            ->and($byEmail->getData(true)['order']['number'])->toBe($order->number);

        // Reject wrong phone / email with 404
        $wrongPhone = $controller->lookup(Request::create('/track', 'POST', [
            'order_number' => (string) $order->number,
            'contact' => '01099999999',
        ]));
        expect($wrongPhone->getStatusCode())->toBe(404);

        $wrongEmail = $controller->lookup(Request::create('/track', 'POST', [
            'order_number' => (string) $order->number,
            'contact' => 'someoneelse@example.com',
        ]));
        expect($wrongEmail->getStatusCode())->toBe(404);
    });
});

test('FR-ORD-06 shipping zones CRUD per governorate with rate, delivery days, display order, and delivery estimate', function () {
    [, $store] = signedInMember();

    app(CurrentStore::class)->runAs($store, function () {
        $shipping = app(ShippingService::class);
        $controller = app(ShippingZoneController::class);

        // Create two zones via controller and service
        $created = $controller->store(Request::create('/shipping-zones', 'POST', [
            'governorate' => 'alexandria',
            'rate' => 7500,
            'delivery_days' => 3,
            'sort_order' => 2,
        ]));
        expect($created->getStatusCode())->toBe(201);
        $alexId = $created->getData(true)['zone']['id'];

        $cairo = $shipping->upsertZone('cairo', 5000, 1, 1);

        // List ordered by sort_order, governorate
        $zones = $shipping->listZones();
        expect($zones->pluck('governorate')->all())->toBe(['cairo', 'alexandria'])
            ->and($shipping->rateForGovernorate('cairo'))->toBe(5000)
            ->and($shipping->rateForGovernorate('alexandria'))->toBe(7500)
            ->and($shipping->rateForGovernorate('unknown_gov'))->toBeNull();

        // Update zone via controller
        $updated = $controller->update(Request::create("/shipping-zones/{$alexId}", 'PUT', [
            'rate' => 8000,
            'delivery_days' => 4,
        ]), $alexId);
        expect($updated->getStatusCode())->toBe(200)
            ->and($shipping->rateForGovernorate('alexandria'))->toBe(8000);

        // Delivery estimate calculation
        $from = Carbon::parse('2026-11-25');
        $estimate = $shipping->deliveryEstimate('alexandria', $from);
        expect($estimate)->toBe([
            'min_date' => '2026-11-29',
            'max_date' => '2026-11-30',
            'delivery_days' => 4,
        ]);
        expect($shipping->deliveryEstimate('unknown_gov', $from))->toBeNull();

        // Delete zone via controller
        $deleted = $controller->destroy($cairo->id);
        expect($deleted->getStatusCode())->toBe(200)
            ->and(ShippingZone::query()->where('governorate', 'cairo')->exists())->toBeFalse()
            ->and($shipping->rateForGovernorate('cairo'))->toBe(6000); // falls back to default
    });
});

test('FR-ORD-08 shopper return request on delivered order approved by owner restocks items and posts refund to ledger', function () {
    [$owner, $store] = signedInMember();

    app(CurrentStore::class)->runAs($store, function () use ($owner) {
        $product = Product::factory()->live()->create(['name' => 'Leather Bag', 'price' => 120000, 'cost' => 50000, 'stock' => 6]);
        $orders = app(OrderService::class);
        $returns = app(ReturnService::class);
        $controller = app(ReturnController::class);

        $order = $orders->createManual([
            'customer_name' => 'Omar Khaled',
            'phone' => '01223334455',
            'governorate' => 'cairo',
            'channel' => 'website',
            'payment_method' => 'cod',
        ], [['product_id' => $product->id, 'quantity' => 2]], $owner);

        expect($product->fresh()->stock)->toBe(4);

        // Cannot request return before delivered
        expect(fn () => $returns->requestReturn($order, 'return', 'Wrong size'))
            ->toThrow(ValidationException::class);

        $orders->changeStatus($order, OrderStatus::Processing, $owner);
        $orders->changeStatus($order, OrderStatus::Shipped, $owner);
        $orders->changeStatus($order, OrderStatus::Delivered, $owner);
        $order = $order->fresh();

        // Submit return request via controller
        $storeReq = Request::create("/orders/{$order->id}/returns", 'POST', [
            'type' => 'return',
            'reason' => 'Damaged strap on arrival',
        ]);
        $created = $controller->store($storeReq, $order->id);
        expect($created->getStatusCode())->toBe(201);
        $returnRequestId = $created->getData(true)['return_request']['id'];

        // Approve return request via controller
        $approveReq = Request::create("/returns/{$returnRequestId}/approve", 'POST');
        $approveReq->setUserResolver(fn () => $owner);
        $approved = $controller->approve($approveReq, $returnRequestId);

        expect($approved->getStatusCode())->toBe(200);

        $rr = ReturnRequest::query()->with('refund')->findOrFail($returnRequestId);
        expect($rr->status)->toBe('approved')
            ->and($rr->reviewed_by)->toBe($owner->id)
            ->and($rr->reviewed_at)->not->toBeNull()
            ->and($rr->refund)->not->toBeNull()
            ->and($rr->refund->amount)->toBe($order->total)
            ->and($rr->refund->is_partial)->toBeFalse()
            ->and($rr->refund->journal_entry_id)->not->toBeNull();

        // Items restocked and order transitioned to returned
        expect($product->fresh()->stock)->toBe(6)
            ->and($order->fresh()->stock_deducted)->toBeFalse()
            ->and($order->fresh()->status)->toBe(OrderStatus::Returned);

        // Balanced refund journal entry posted exactly once (no double post from OrderPosting)
        $refundEntries = JournalEntry::query()->where('source_key', "order:{$order->id}:refund")->get();
        expect($refundEntries)->toHaveCount(1)
            ->and($refundEntries->first()->total_debit)->toBe($order->total)
            ->and($refundEntries->first()->total_credit)->toBe($order->total);
    });
});

test('FR-ORD-08 owner can reject a pending return request without restocking or posting a refund', function () {
    [$owner, $store] = signedInMember();

    app(CurrentStore::class)->runAs($store, function () use ($owner) {
        $product = Product::factory()->live()->create(['name' => 'Watch', 'price' => 90000, 'stock' => 5]);
        $orders = app(OrderService::class);
        $returns = app(ReturnService::class);

        $order = $orders->createManual([
            'customer_name' => 'Hoda Samir',
            'phone' => '01112223344',
            'governorate' => 'giza',
            'channel' => 'whatsapp',
            'payment_method' => 'cod',
        ], [['product_id' => $product->id, 'quantity' => 1]], $owner);

        $orders->changeStatus($order, OrderStatus::Processing, $owner);
        $orders->changeStatus($order, OrderStatus::Shipped, $owner);
        $orders->changeStatus($order, OrderStatus::Delivered, $owner);

        $rr = $returns->requestReturn($order->fresh(), 'return', 'Changed my mind');
        $rejected = $returns->reject($rr, $owner);

        expect($rejected->status)->toBe('rejected')
            ->and($rejected->reviewed_by)->toBe($owner->id)
            ->and($product->fresh()->stock)->toBe(4)
            ->and($order->fresh()->status)->toBe(OrderStatus::Delivered)
            ->and(Refund::query()->count())->toBe(0);
    });
});

test('FR-ORD-09 invoice and packing slip data and HTML include order details and exclude internal notes', function () {
    [$owner, $store] = signedInMember();

    app(CurrentStore::class)->runAs($store, function () use ($owner, $store) {
        $product = Product::factory()->live()->create(['name' => 'Wool Coat', 'price' => 150000, 'stock' => 5]);
        $orders = app(OrderService::class);
        $invoices = app(InvoiceService::class);

        $order = $orders->createManual([
            'customer_name' => 'Youssef Adel',
            'phone' => '01007778899',
            'email' => 'youssef@example.com',
            'governorate' => 'cairo',
            'city' => 'Maadi',
            'address' => '9 Road 9',
            'channel' => 'website',
            'payment_method' => 'card',
            'discount' => 10000,
            'notes' => 'DO NOT PRINT: customer asked for extra wrap',
        ], [['product_id' => $product->id, 'quantity' => 2]], $owner);

        $orders->updateFulfilment($order, 'Aramex', 'https://aramex.com/track/555');
        $order = $order->fresh('items');

        $invoiceData = $invoices->invoiceData($order);
        expect($invoiceData['invoice_number'])->toBe('INV-'.$order->number)
            ->and($invoiceData['store']['name'])->toBe($store->name)
            ->and($invoiceData['totals']['subtotal'])->toBe(300000)
            ->and($invoiceData['totals']['discount'])->toBe(10000)
            ->and($invoiceData['totals']['total'])->toBe($order->total)
            ->and(array_key_exists('notes', $invoiceData))->toBeFalse();

        $invoiceHtml = $invoices->renderInvoiceHtml($order);
        expect($invoiceHtml)->toContain('INV-'.$order->number)
            ->and($invoiceHtml)->toContain(e($store->name)) // HTML-escaped: names like "O'Kon" appear as O&#039;Kon
            ->and($invoiceHtml)->toContain('Wool Coat')
            ->and($invoiceHtml)->toContain('EGP')
            ->and($invoiceHtml)->not->toContain('DO NOT PRINT');

        $slipData = $invoices->packingSlipData($order);
        expect($slipData['order_number'])->toBe($order->number)
            ->and($slipData['courier_name'])->toBe('Aramex')
            ->and($slipData['total_items'])->toBe(2)
            ->and(array_key_exists('notes', $slipData))->toBeFalse();

        $slipHtml = $invoices->renderPackingSlipHtml($order);
        expect($slipHtml)->toContain('#'.$order->number)
            ->and($slipHtml)->toContain('Aramex')
            ->and($slipHtml)->toContain('Wool Coat')
            ->and($slipHtml)->not->toContain('DO NOT PRINT');
    });
});

test('FR-ORD-12 orders export produces UTF-8 BOM CSV with Arabic names intact', function () {
    [, $store] = signedInMember();
    $product = app(CurrentStore::class)->runAs($store, fn () => Product::factory()->live()->create(['name' => 'عباية كتان', 'price' => 95000, 'stock' => 10]));

    $this->post(route('orders.store'), [
        'customer_name' => 'أحمد محمود',
        'phone' => '01001112233',
        'governorate' => 'cairo',
        'channel' => 'whatsapp',
        'payment_method' => 'cod',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertSessionHasNoErrors();

    $response = $this->get(route('orders.export'));
    $response->assertOk();

    $csv = $response->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('أحمد محمود')
        ->and($csv)->toContain('عباية كتان');
});
