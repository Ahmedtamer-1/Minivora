<?php

use App\Core\Money\MoneyFormatter;
use App\Core\Money\Piasters;
use App\Core\Permissions\Role;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Services\ShopperAccountService;
use App\Modules\Builder\Models\Page;
use App\Modules\Builder\Services\PageBuilderService;
use App\Modules\Catalog\Services\ImageService;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Ledger\Services\ChartOfAccounts;
use App\Modules\Ledger\Services\StatementService;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\ReturnRequest;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Platform\Jobs\BuildInitialStoreDraft;
use App\Modules\Platform\Models\Store;
use App\Modules\Seo\Services\CustomDomainService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('FR-CAT-08 ImageService generates WebP/AVIF srcset, lazy-loaded picture markup, and responsive disk variants', function () {
    Storage::fake('public');

    $service = app(ImageService::class);

    expect($service->responsiveWidths())->toBe([320, 640, 960, 1280])
        ->and($service->supportedFormats())->toContain('webp');

    if (function_exists('imageavif')) {
        expect($service->supportedFormats())->toContain('avif');
    }

    $webpSrcset = $service->buildSrcset('/storage/products/linen-shirt.jpg', 'webp');
    expect($webpSrcset)->toBe(
        '/storage/products/linen-shirt-320w.webp 320w, '
        .'/storage/products/linen-shirt-640w.webp 640w, '
        .'/storage/products/linen-shirt-960w.webp 960w, '
        .'/storage/products/linen-shirt-1280w.webp 1280w'
    );

    $html = $service->renderPictureHtml('/storage/products/linen-shirt.jpg', 'Linen Shirt');
    expect($html)
        ->toContain('<picture>')
        ->toContain('type="image/webp"')
        ->toContain('srcset="'.$webpSrcset.'"')
        ->toContain('loading="lazy"')
        ->toContain('alt="Linen Shirt"')
        ->toContain('</picture>');

    if (function_exists('imageavif')) {
        expect($html)->toContain('type="image/avif"');
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'minivora_img_');
    if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
        $im = imagecreatetruecolor(64, 64);
        imagepng($im, $tmpFile);
        imagedestroy($im);
    } else {
        file_put_contents($tmpFile, 'fake-image-bytes');
    }

    try {
        $result = $service->processUpload($tmpFile, 'products/shirt-1');
    } finally {
        @unlink($tmpFile);
    }

    expect($result['widths'])->toBe([320, 640, 960, 1280]);
    foreach ($result['variants']['webp'] as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

test('FR-CAT-14 maintenance mode returns 503 with custom message to guests while signed-in store staff see 200', function () {
    $staff = User::factory()->create();
    $store = Store::factory()->live()->withMember($staff, Role::Staff)->create([
        'subdomain' => 'maint-shop',
        'name' => 'Maint Shop',
    ]);

    $store->enableMaintenanceMode('We are restocking our winter collection. Back at 6 PM.');

    // Guest receives 503 with custom maintenance message.
    $this->get(storeUrl('maint-shop', '/en'))
        ->assertStatus(503)
        ->assertSee('Maint Shop')
        ->assertSee('We are restocking our winter collection. Back at 6 PM.');

    // Signed-in store staff member bypasses maintenance mode and sees 200.
    $this->actingAs($staff)
        ->get(storeUrl('maint-shop', '/en'))
        ->assertOk()
        ->assertSee('Products are on their way');
});

test('FR-ACC-01 shoppers register with email/password or sign in with Google without creating a store', function () {
    $service = app(ShopperAccountService::class);
    $storesBefore = Store::query()->count();

    $emailShopper = $service->register('Nour Hassan', 'nour@example.com', 'secret-pass-123');
    expect($emailShopper->email)->toBe('nour@example.com')
        ->and($emailShopper->password)->not->toBeNull()
        ->and($emailShopper->stores()->count())->toBe(0);

    $googleShopper = $service->loginWithGoogle('salma.google@example.com', 'Salma Ali', 'google-sub-998877');
    expect($googleShopper->email)->toBe('salma.google@example.com')
        ->and($googleShopper->password)->toBeNull()
        ->and($googleShopper->email_verified_at)->not->toBeNull()
        ->and($googleShopper->stores()->count())->toBe(0);

    // Subsequent Google sign-in returns the same account.
    $returning = $service->loginWithGoogle('salma.google@example.com', 'Salma Ali', 'google-sub-998877');
    expect($returning->id)->toBe($googleShopper->id)
        ->and(Store::query()->count())->toBe($storesBefore);
});

test('FR-ACC-02 shopper account overview returns order history, saved addresses, and return requests for the current store', function () {
    $store = Store::factory()->live()->create();
    $otherStore = Store::factory()->live()->create();
    $current = app(CurrentStore::class);
    $shopperService = app(ShopperAccountService::class);

    $shopper = $shopperService->register('Mona Adel', 'mona@example.com', 'password-123');

    $current->runAs($store, function () use ($shopper): void {
        $order = Order::query()->create([
            'number' => 1001,
            'status' => OrderStatus::Delivered,
            'channel' => 'website',
            'payment_method' => 'cod',
            'customer_name' => $shopper->name,
            'phone' => '01012345678',
            'email' => $shopper->email,
            'governorate' => 'Cairo',
            'city' => 'Maadi',
            'address' => '15 Road 9, Maadi',
            'subtotal' => 45000,
            'shipping_fee' => 5000,
            'discount' => 0,
            'total' => 50000,
            'placed_at' => Carbon::now(),
        ]);

        ReturnRequest::query()->create([
            'order_id' => $order->id,
            'type' => 'return',
            'reason' => 'Size too small',
            'status' => 'pending',
            'refund_amount' => 45000,
        ]);
    });

    // Order in another store must never leak into this store's overview.
    $current->runAs($otherStore, function () use ($shopper): void {
        Order::query()->create([
            'number' => 1001,
            'status' => OrderStatus::Pending,
            'channel' => 'website',
            'payment_method' => 'cod',
            'customer_name' => $shopper->name,
            'phone' => '01012345678',
            'email' => $shopper->email,
            'governorate' => 'Alexandria',
            'city' => 'Smouha',
            'address' => '99 Other Street',
            'subtotal' => 20000,
            'shipping_fee' => 5000,
            'discount' => 0,
            'total' => 25000,
            'placed_at' => Carbon::now(),
        ]);
    });

    $overview = $current->runAs($store, fn (): array => $shopperService->accountOverview($shopper));

    expect($overview['orders'])->toHaveCount(1)
        ->and($overview['orders'][0]['number'])->toBe(1001)
        ->and($overview['orders'][0]['total'])->toBe(50000)
        ->and($overview['saved_addresses'])->toHaveCount(1)
        ->and($overview['saved_addresses'][0]['governorate'])->toBe('Cairo')
        ->and($overview['saved_addresses'][0]['address'])->toBe('15 Road 9, Maadi')
        ->and($overview['return_requests'])->toHaveCount(1)
        ->and($overview['return_requests'][0]['reason'])->toBe('Size too small');
});

test('FR-ONB-05 BuildInitialStoreDraft queues asynchronously and builds a draft homepage preview on execution', function () {
    Queue::fake();

    $store = Store::factory()->create(['name' => 'Cairo Ceramics']);
    $answers = [
        'sells' => 'Handmade Egyptian ceramic mugs and bowls',
        'location' => 'Cairo',
        'language' => 'en',
    ];

    BuildInitialStoreDraft::dispatch($store, $answers);

    Queue::assertPushed(BuildInitialStoreDraft::class, function (BuildInitialStoreDraft $job) use ($store): bool {
        return $job->store->is($store) && $job->answers['location'] === 'Cairo';
    });

    // Execute the job and verify it produces a draft homepage preview (never auto-published).
    $job = new BuildInitialStoreDraft($store, $answers);
    $job->handle(app(CurrentStore::class), app(PageBuilderService::class));

    $homeDraft = app(CurrentStore::class)->runAs($store, fn (): ?Page => Page::query()->with('sections')->where('is_home', true)->first());

    expect($homeDraft)->not->toBeNull()
        ->and($homeDraft->status)->toBe(Page::STATUS_DRAFT)
        ->and($homeDraft->is_ai_draft)->toBeTrue()
        ->and($homeDraft->published_at)->toBeNull()
        ->and($homeDraft->sections)->toHaveCount(4);
});

test('FR-LED-08 ledger reports export produces Excel UTF-8 BOM CSV and printable PDF/HTML', function () {
    signedInMember(Role::Owner);

    $csvResponse = $this->get(route('finance.reports.export', ['report' => 'pl']));
    $csvResponse->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($csvResponse->streamedContent())->toStartWith("\xEF\xBB\xBF");

    $printResponse = $this->get(route('finance.reports.export', ['report' => 'pl', 'format' => 'print']));
    $printResponse->assertOk()->assertHeader('content-type', 'text/html; charset=UTF-8');
    expect($printResponse->getContent())
        ->toContain('<!DOCTYPE html>')
        ->toContain('@media print')
        ->toContain('<table>');
});

test('FR-LED-09 FR-LED-10 ledger enforces consistent COGS valuation on shipment and COD revenue recognition on delivery', function () {
    [$owner, $store] = signedInMember(Role::Owner);
    $current = app(CurrentStore::class);

    $order = $current->runAs($store, function () use ($owner): Order {
        $products = app(ProductService::class);
        $draft = $products->startDraft();
        $product = $products->update($draft, [
            'name' => 'Cotton Abaya',
            'price' => 100000, // 1,000 EGP
            'cost' => 42000,   // 420 EGP unit cost
            'stock' => 10,
        ], []);

        return app(OrderService::class)->createManual([
            'channel' => 'website',
            'payment_method' => 'cod',
            'status' => 'pending',
            'customer_name' => 'Hoda Karim',
            'phone' => '01099887766',
            'governorate' => 'Cairo',
            'shipping_fee' => 5000,
        ], [
            ['product_id' => $product->id, 'quantity' => 2],
        ], $owner);
    });

    $statements = app(StatementService::class);
    $accounts = app(ChartOfAccounts::class);
    $balance = function (string $code) use ($current, $store, $statements, $accounts): int {
        return $current->runAs($store, function () use ($store, $statements, $accounts, $code): int {
            $balances = $statements->balances(null, Carbon::now('Africa/Cairo'));

            return $balances[$accounts->get($store->id, $code)->id] ?? 0;
        });
    };

    // Pending COD order: neither revenue nor COGS recognised yet.
    expect($balance(ChartOfAccounts::SALES))->toBe(0)
        ->and($balance(ChartOfAccounts::COGS))->toBe(0);

    // Move to Processing -> Shipped: COGS is consistently valued at snapshot unit_cost * qty (42000 * 2 = 84000),
    // while COD revenue is STILL 0 until courier delivers and collects cash.
    $current->runAs($store, function () use ($order, $owner): void {
        $orders = app(OrderService::class);
        $orders->changeStatus($order->fresh(), OrderStatus::Processing, $owner);
        $orders->changeStatus($order->fresh(), OrderStatus::Shipped, $owner);
    });

    expect($balance(ChartOfAccounts::COGS))->toBe(84000)
        ->and($balance(ChartOfAccounts::SALES))->toBe(0);

    // Deliver COD order: cash collected -> revenue recognised consistently on delivery.
    $current->runAs($store, function () use ($order, $owner): void {
        app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::Delivered, $owner);
    });

    expect($balance(ChartOfAccounts::SALES))->toBe(200000)
        ->and($balance(ChartOfAccounts::CASH))->toBe(205000)
        ->and($balance(ChartOfAccounts::COGS))->toBe(84000);
});

test('FR-LED-11 FR-LED-12 money is EGP-only, stored as integer piasters, and rounded half-up once per line', function () {
    // FR-LED-11: EGP-only currency formatting in English and Arabic.
    expect(MoneyFormatter::format(125050, 'en'))->toBe('1,250.50 EGP')
        ->and(MoneyFormatter::format(125050, 'ar'))->toBe('1,250.50 ج.م')
        ->and(MoneyFormatter::format(Piasters::of(0), 'en'))->toBe('0.00 EGP');

    // FR-LED-12: Integer piasters & half-up percentage rounding once per line via Piasters::percent().
    $line = Piasters::fromPounds('19.99'); // 1999 piasters (integer)
    expect($line->amount)->toBeInt()->toBe(1999)
        ->and($line->percent(1000)->amount)->toBe(200) // 10% of 1999 = 199.9 -> 200 (half-up)
        ->and(Piasters::of(105)->percent(1000)->amount)->toBe(11) // 10.5 -> 11 (half-up)
        ->and(Piasters::of(104)->percent(1000)->amount)->toBe(10); // 10.4 -> 10
});

test('FR-SEO-21 CustomDomainService normalizes canonical host and resolves HTTP->HTTPS and www<->non-www redirects', function () {
    $service = app(CustomDomainService::class);

    expect($service->canonicalHost('WWW.MyShop.eg', false))->toBe('myshop.eg')
        ->and($service->canonicalHost('myshop.eg', true))->toBe('www.myshop.eg');

    // HTTP -> HTTPS redirect
    expect($service->redirectUrlFor('http', 'myshop.eg', '/en/products', 'myshop.eg'))
        ->toBe('https://myshop.eg/en/products');

    // www -> non-www canonical redirect
    expect($service->redirectUrlFor('https', 'www.myshop.eg', '/ar/cart', 'myshop.eg'))
        ->toBe('https://myshop.eg/ar/cart');

    // non-www -> www canonical redirect when preferWww is true
    expect($service->redirectUrlFor('http', 'myshop.eg', '/en', 'www.myshop.eg'))
        ->toBe('https://www.myshop.eg/en');

    // Already canonical HTTPS -> no redirect
    expect($service->redirectUrlFor('https', 'myshop.eg', '/en/products', 'myshop.eg'))
        ->toBeNull();
});
