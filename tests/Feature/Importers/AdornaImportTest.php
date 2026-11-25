<?php

use App\Core\Tenancy\CurrentStore;
use App\Importers\Adorna\AdornaImporter;
use App\Importers\Adorna\AdornaMoney;
use App\Importers\Adorna\Jobs\RunNightlyAdornaImport;
use App\Modules\Accounts\Models\User;
use App\Modules\Analytics\Models\PageView;
use App\Modules\Analytics\Models\VisitSession;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Marketing\Models\AdSpend;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\ShippingZone;
use App\Modules\Platform\Models\Store;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Realistic 2-month Adorna EG export payload (2026-09 and 2026-10).
 *
 * Stock math:
 * - Product 1 (Silver Ring, 2 variants): 12 + 8 = 20 units (parent stock synced from variants)
 * - Product 2 (Gold Bracelet, simple): 15 units
 * - Total stock = 35 units
 *
 * Sales math (piasters, countingAsSale excludes cancelled orders):
 * - 2026-09:
 *   - Order #5001 (delivered): subtotal 376.50 + shipping 50.00 - discount 25.00 = 401.50 EGP -> 40150 piasters
 *   - Order #5002 (delivered): subtotal 250.00 + shipping 65.00 - discount 0.00  = 315.00 EGP -> 31500 piasters
 *   - Order #5003 (cancelled): 125.50 + 50.00 = 175.50 EGP -> excluded from monthly_sales
 *   - 2026-09 total sales = 40150 + 31500 = 71650 piasters
 * - 2026-10:
 *   - Order #5004 (delivered): subtotal 499.995 (half-up -> 50000) + shipping 50.00 - discount 10.25 = 53975 piasters
 *   - Order #5005 (processing): subtotal 125.50 + shipping 55.00 - discount 0.00 = 18050 piasters
 *   - 2026-10 total sales = 53975 + 18050 = 72025 piasters
 * - Total order_count = 5 (all imported orders including cancelled)
 *
 * @return array<string, mixed>
 */
function sampleAdornaDataset(): array
{
    return [
        'categories' => [
            [
                'id' => 1,
                'name' => 'Rings',
                'name_ar' => 'خواتم',
                'slug' => 'rings',
                'sort_order' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Bracelets',
                'name_ar' => 'أساور',
                'slug' => 'bracelets',
                'sort_order' => 2,
            ],
        ],
        'products' => [
            [
                'id' => 101,
                'name' => 'Adorna Silver Ring',
                'name_ar' => 'خاتم فضة أدورنا',
                'slug' => 'adorna-silver-ring',
                'sku' => 'ADR-RING-01',
                'category_id' => 1,
                'price' => '125.50',
                'cost' => '45.25',
                'stock' => 999, // intentionally stale; must be synced to variants sum (12 + 8 = 20)
                'status' => 'active',
                'variants' => [
                    [
                        'sku' => 'ADR-RING-01-S',
                        'options' => ['size' => 'S'],
                        'price' => '125.50',
                        'cost' => '45.25',
                        'stock' => 12,
                    ],
                    [
                        'sku' => 'ADR-RING-01-M',
                        'options' => ['size' => 'M'],
                        'price' => '125.50',
                        'cost' => '45.25',
                        'stock' => 8,
                    ],
                ],
            ],
            [
                'id' => 102,
                'name' => 'Adorna Gold Bracelet',
                'name_ar' => 'أسورة ذهب أدورنا',
                'slug' => 'adorna-gold-bracelet',
                'sku' => 'ADR-BRAC-01',
                'category_slug' => 'bracelets',
                'price' => 250.00,
                'cost' => 110.00,
                'stock' => 15,
                'status' => 'live',
            ],
        ],
        'shipping_zones' => [
            ['governorate' => 'Cairo', 'rate' => '50.00', 'delivery_days' => 2, 'sort_order' => 1],
            ['governorate' => 'Giza', 'rate' => 55, 'delivery_days' => 2, 'sort_order' => 2],
            ['governorate' => 'Alexandria', 'rate' => '65.00', 'delivery_days' => 3, 'sort_order' => 3],
        ],
        'customers' => [
            [
                'id' => 201,
                'name' => 'Salma Hassan',
                'phone' => '+20 101-234-5678',
                'email' => 'salma@example.com',
                'governorate' => 'Cairo',
                'city' => 'Maadi',
            ],
            [
                'id' => 202,
                'name' => 'Nour El-Din',
                'phone' => '00201198765432',
                'email' => 'nour@example.com',
                'governorate' => 'Alexandria',
                'city' => 'Smouha',
            ],
            [
                'id' => 203,
                'name' => 'Salma Duplicate Phone',
                'phone' => '01012345678', // duplicates customer 201 after EgyptianPhone normalization
                'governorate' => 'Cairo',
            ],
            [
                'id' => 204,
                'name' => 'Legacy Boutique Landline',
                'phone' => '02-2345-6789', // non-mobile fallback
                'governorate' => 'Giza',
            ],
        ],
        'orders' => [
            [
                'number' => 5001,
                'customer_name' => 'Salma Hassan',
                'phone' => '+201012345678',
                'governorate' => 'Cairo',
                'status' => 'delivered',
                'payment_method' => 'cod',
                'channel' => 'website',
                'subtotal' => '376.50',
                'shipping_fee' => '50.00',
                'discount' => '25.00',
                'total' => '401.50',
                'placed_at' => '2026-09-10 14:30:00',
                'items' => [
                    [
                        'product_sku' => 'ADR-RING-01',
                        'name' => 'Adorna Silver Ring',
                        'variant' => 'Size S',
                        'unit_price' => '125.50',
                        'unit_cost' => '45.25',
                        'quantity' => 3,
                        'line_total' => '376.50',
                    ],
                ],
            ],
            [
                'number' => 5002,
                'customer_name' => 'Nour El-Din',
                'phone' => '+201198765432',
                'governorate' => 'Alexandria',
                'status' => 'delivered',
                'payment_method' => 'instapay',
                'channel' => 'instagram',
                'subtotal' => '250.00',
                'shipping_fee' => '65.00',
                'discount' => '0.00',
                'total' => '315.00',
                'placed_at' => '2026-09-22 18:15:00',
                'items' => [
                    [
                        'product_sku' => 'ADR-BRAC-01',
                        'name' => 'Adorna Gold Bracelet',
                        'unit_price' => '250.00',
                        'unit_cost' => '110.00',
                        'quantity' => 1,
                    ],
                ],
            ],
            [
                'number' => 5003,
                'customer_name' => 'Salma Hassan',
                'phone' => '+201012345678',
                'governorate' => 'Cairo',
                'status' => 'cancelled',
                'payment_method' => 'cod',
                'channel' => 'website',
                'subtotal' => '125.50',
                'shipping_fee' => '50.00',
                'discount' => '0.00',
                'total' => '175.50',
                'placed_at' => '2026-09-28 09:00:00',
                'items' => [
                    [
                        'product_sku' => 'ADR-RING-01',
                        'name' => 'Adorna Silver Ring',
                        'unit_price' => '125.50',
                        'unit_cost' => '45.25',
                        'quantity' => 1,
                    ],
                ],
            ],
            [
                'number' => 5004,
                'customer_name' => 'Salma Hassan',
                'phone' => '+201012345678',
                'governorate' => 'Cairo',
                'status' => 'delivered',
                'payment_method' => 'card',
                'channel' => 'website',
                'subtotal' => '499.995', // half-up rounds to 50000 piasters
                'shipping_fee' => '50.00',
                'discount' => '10.25',
                'total' => '539.75',
                'placed_at' => '2026-10-05 12:00:00',
                'items' => [
                    [
                        'product_sku' => 'ADR-BRAC-01',
                        'name' => 'Adorna Gold Bracelet',
                        'unit_price' => '250.00',
                        'unit_cost' => '110.00',
                        'quantity' => 2,
                    ],
                ],
            ],
            [
                'number' => 5005,
                'customer_name' => 'Legacy Boutique Landline',
                'phone' => '02-2345-6789',
                'governorate' => 'Giza',
                'status' => 'processing',
                'payment_method' => 'cod',
                'channel' => 'whatsapp',
                'subtotal' => '125.50',
                'shipping_fee' => '55.00',
                'discount' => '0.00',
                'total' => '180.50',
                'placed_at' => '2026-10-19 20:45:00',
                'items' => [
                    [
                        'product_sku' => 'ADR-RING-01',
                        'name' => 'Adorna Silver Ring',
                        'variant' => 'Size M',
                        'unit_price' => '125.50',
                        'unit_cost' => '45.25',
                        'quantity' => 1,
                    ],
                ],
            ],
        ],
        'ad_spends' => [
            [
                'channel' => 'meta',
                'campaign_name' => 'September Rings Launch',
                'spend_date' => '2026-09-10',
                'amount' => '1500.75',
            ],
            [
                'channel' => 'tiktok',
                'campaign_name' => 'October Retargeting',
                'spend_date' => '2026-10-05',
                'amount' => '890.25',
            ],
        ],
        'visits' => [
            [
                'session_key' => 'adorna-sess-001',
                'utm_source' => 'instagram',
                'utm_medium' => 'paid_social',
                'utm_campaign' => 'sep-rings',
                'referrer' => 'https://l.instagram.com/',
                'device_type' => 'mobile',
                'started_at' => '2026-09-10 14:15:00',
                'page_views' => [
                    ['path' => '/', 'viewed_at' => '2026-09-10 14:15:00'],
                    [
                        'path' => '/products/adorna-silver-ring',
                        'product_sku' => 'ADR-RING-01',
                        'viewed_at' => '2026-09-10 14:17:00',
                    ],
                ],
            ],
            [
                'session_key' => 'adorna-sess-002',
                'utm_source' => 'google',
                'utm_medium' => 'organic',
                'utm_campaign' => null,
                'referrer' => 'https://www.google.com/',
                'device_type' => 'desktop',
                'started_at' => '2026-10-05 11:50:00',
                'path' => '/products/adorna-gold-bracelet',
                'product_sku' => 'ADR-BRAC-01',
            ],
        ],
    ];
}

test('FR-ONB-09 AdornaImporter converts EGP to integer piasters half-up and imports 100% idempotently', function () {
    expect(AdornaMoney::fromEgp('125.50'))->toBe(12550)
        ->and(AdornaMoney::fromEgp('125.505'))->toBe(12551)
        ->and(AdornaMoney::fromEgp('125.504'))->toBe(12550)
        ->and(AdornaMoney::fromEgp(99))->toBe(9900);

    $owner = User::factory()->create();
    $store = Store::factory()->withMember($owner)->create();
    $otherStore = Store::factory()->withMember($owner)->create();

    $importer = app(AdornaImporter::class);
    $dataset = sampleAdornaDataset();

    // First import run
    $first = $importer->import($store, $dataset);

    expect($first['categories'])->toBe(['imported' => 2, 'skipped' => 0])
        ->and($first['products'])->toBe(['imported' => 2, 'skipped' => 0])
        ->and($first['variants'])->toBe(['imported' => 2, 'skipped' => 0])
        ->and($first['shipping_zones'])->toBe(['imported' => 3, 'skipped' => 0])
        ->and($first['customers'])->toBe(['imported' => 3, 'skipped' => 1]) // 4th row deduplicated by phone
        ->and($first['orders'])->toBe(['imported' => 5, 'skipped' => 0])
        ->and($first['order_items'])->toBe(['imported' => 5, 'skipped' => 0])
        ->and($first['ad_spends'])->toBe(['imported' => 2, 'skipped' => 0])
        ->and($first['visits'])->toBe(['imported' => 2, 'skipped' => 0])
        ->and($first['page_views'])->toBe(['imported' => 3, 'skipped' => 0]);

    // Second import run — must be 100% idempotent (zero new duplicates)
    $second = $importer->import($store, $dataset);

    expect($second['categories']['imported'])->toBe(0)
        ->and($second['products']['imported'])->toBe(0)
        ->and($second['variants']['imported'])->toBe(0)
        ->and($second['shipping_zones']['imported'])->toBe(0)
        ->and($second['customers']['imported'])->toBe(0)
        ->and($second['orders']['imported'])->toBe(0)
        ->and($second['orders']['skipped'])->toBe(5)
        ->and($second['order_items']['imported'])->toBe(0)
        ->and($second['ad_spends']['imported'])->toBe(0)
        ->and($second['visits']['imported'])->toBe(0)
        ->and($second['page_views']['imported'])->toBe(0);

    // Verify exact DB state scoped to $store
    app(CurrentStore::class)->runAs($store, function () {
        expect(Category::query()->count())->toBe(2)
            ->and(Product::query()->count())->toBe(2)
            ->and(ProductVariant::query()->count())->toBe(2)
            ->and(ShippingZone::query()->count())->toBe(3)
            ->and(Customer::query()->count())->toBe(3)
            ->and(Order::query()->count())->toBe(5)
            ->and(OrderItem::query()->count())->toBe(5)
            ->and(AdSpend::query()->count())->toBe(2)
            ->and(VisitSession::query()->count())->toBe(2)
            ->and(PageView::query()->count())->toBe(3);

        // Variant stock synced to parent product
        $ring = Product::query()->where('sku', 'ADR-RING-01')->firstOrFail();
        expect($ring->price)->toBe(12550)
            ->and($ring->cost)->toBe(4525)
            ->and($ring->has_options)->toBeTrue()
            ->and($ring->stock)->toBe(20);

        // Phone normalization (+20 101-234-5678 -> 01012345678) & legacy landline fallback
        $salma = Customer::query()->where('phone', '01012345678')->firstOrFail();
        expect($salma->orders_count)->toBe(2) // #5001 and #5004; #5003 is cancelled
            ->and($salma->total_spent)->toBe(40150 + 53975);

        expect(Customer::query()->where('phone', '0223456789')->exists())->toBeTrue();

        // Order money stored as integer piasters and status preserved
        $order5004 = Order::query()->where('number', 5004)->firstOrFail();
        expect($order5004->subtotal)->toBe(50000)
            ->and($order5004->shipping_fee)->toBe(5000)
            ->and($order5004->discount)->toBe(1025)
            ->and($order5004->total)->toBe(53975)
            ->and($order5004->status)->toBe(OrderStatus::Delivered);

        // Ad spend stored in integer piasters
        $metaSpend = AdSpend::query()->where('channel', 'meta')->firstOrFail();
        expect($metaSpend->amount)->toBe(150075);

        // Visits carry UTM attribution and referrer
        $session = VisitSession::query()->where('session_key', 'adorna-sess-001')->firstOrFail();
        expect($session->utm_source)->toBe('instagram')
            ->and($session->utm_campaign)->toBe('sep-rings')
            ->and($session->pageViews()->count())->toBe(2);
    });

    // Verify complete tenant isolation — otherStore remains empty
    app(CurrentStore::class)->runAs($otherStore, function () {
        expect(Product::query()->count())->toBe(0)
            ->and(Order::query()->count())->toBe(0)
            ->and(Customer::query()->count())->toBe(0)
            ->and(VisitSession::query()->count())->toBe(0);
    });
});

test('V-07 AdornaImporter::reconcile verifies piaster-exact parity across order counts, total stock, and 2-month sales', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->withMember($owner)->create();

    $importer = app(AdornaImporter::class);
    $importer->import($store, sampleAdornaDataset());

    $result = $importer->reconcile($store, [
        'order_count' => 5,
        'total_stock' => 35,
        'monthly_sales' => [
            '2026-09' => 71650,
            '2026-10' => 72025,
        ],
    ]);

    expect($result['matched'])->toBeTrue()
        ->and($result['diffs'])->toBe([])
        ->and($result['actual'])->toBe([
            'order_count' => 5,
            'total_stock' => 35,
            'monthly_sales' => [
                '2026-09' => 71650,
                '2026-10' => 72025,
            ],
        ]);

    // Even a 1-piaster difference must fail V-07 reconciliation
    $mismatch = $importer->reconcile($store, [
        'order_count' => 5,
        'total_stock' => 35,
        'monthly_sales' => [
            '2026-09' => 71650,
            '2026-10' => 72024, // 1 piaster off
        ],
    ]);

    expect($mismatch['matched'])->toBeFalse()
        ->and($mismatch['diffs'])->toHaveKey('monthly_sales')
        ->and($mismatch['diffs']['monthly_sales']['2026-10'])->toBe([
            'expected' => 72024,
            'actual' => 72025,
            'delta' => 1,
        ]);
});

test('NFR-REL-08 RunNightlyAdornaImport attaches WithoutOverlapping middleware keyed by store_id and imports data', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->withMember($owner)->create();

    $job = new RunNightlyAdornaImport($store->id, sampleAdornaDataset());

    expect($job)->toBeInstanceOf(ShouldQueue::class);

    $middleware = $job->middleware();
    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->key)->toBe("adorna-import-store-{$store->id}");

    // Execute handle() and confirm store data is imported
    $job->handle(app(AdornaImporter::class));

    $reconcile = app(AdornaImporter::class)->reconcile($store, [
        'order_count' => 5,
        'total_stock' => 35,
        'monthly_sales' => [
            '2026-09' => 71650,
            '2026-10' => 72025,
        ],
    ]);

    expect($reconcile['matched'])->toBeTrue();
});
