<?php

use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Events\StockBelowReorder;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ReviewController;
use App\Modules\Catalog\Http\Controllers\StaticPageController;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ContactMessage;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Review;
use App\Modules\Catalog\Models\StaticPage;
use App\Modules\Catalog\Models\Wishlist;
use App\Modules\Catalog\Services\InventoryService;
use App\Modules\Catalog\Services\SearchService;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Platform\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/** Build a JSON request for invoking controllers directly without modifying routes/*.php. */
function jsonRequest(string $method, array $payload = [], array $query = []): Request
{
    $request = Request::create('/test', $method, $method === 'GET' ? $query : $payload);
    $request->headers->set('Accept', 'application/json');

    return $request;
}

/** Create a fresh store and run a callback inside its tenancy context. */
function inFreshStore(callable $fn): mixed
{
    $owner = User::factory()->create();
    $store = Store::factory()->withMember($owner)->create();

    return app(CurrentStore::class)->runAs($store, fn () => $fn($store, $owner));
}

test('FR-CAT-02 variants sync parent product stock and adjustStock updates variant and parent', function () {
    Event::fake([StockBelowReorder::class]);

    inFreshStore(function () {
        $product = Product::factory()->create([
            'name' => 'Leather Jacket',
            'stock' => 0,
            'reorder_threshold' => 3,
            'has_options' => true,
        ]);

        $small = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'LJ-S-BLK',
            'options' => ['size' => 'S', 'color' => 'Black'],
            'price' => 350000,
            'cost' => 200000,
            'stock' => 10,
        ]);

        $large = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'LJ-L-BLK',
            'options' => ['size' => 'L', 'color' => 'Black'],
            'price' => 350000,
            'stock' => 6,
        ]);

        $inventory = app(InventoryService::class);
        $total = $inventory->syncProductStock($product);

        expect($total)->toBe(16)
            ->and($product->fresh()->stock)->toBe(16)
            ->and($product->variants)->toHaveCount(2);

        $remaining = $inventory->adjustStock($product, -4, $small);
        expect($remaining)->toBe(6)
            ->and($small->fresh()->stock)->toBe(6)
            ->and($product->fresh()->stock)->toBe(12);

        Event::assertNotDispatched(StockBelowReorder::class);
    });
});

test('FR-CAT-17 adjustStock dispatches StockBelowReorder when stock reaches or drops below threshold', function () {
    Event::fake([StockBelowReorder::class]);

    inFreshStore(function () {
        $product = Product::factory()->create([
            'name' => 'Canvas Tote',
            'stock' => 8,
            'reorder_threshold' => 4,
        ]);

        $inventory = app(InventoryService::class);

        // Above threshold: no event.
        $inventory->adjustStock($product, -3);
        expect($product->fresh()->stock)->toBe(5);
        Event::assertNotDispatched(StockBelowReorder::class);

        // At threshold (4 <= 4): event dispatched.
        $inventory->adjustStock($product, -1);
        expect($product->fresh()->stock)->toBe(4);
        Event::assertDispatched(StockBelowReorder::class, function (StockBelowReorder $event) use ($product) {
            return $event->product->id === $product->id
                && $event->variant === null
                && $event->stock === 4;
        });

        // Variant dropping below threshold also dispatches with the variant attached.
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'CT-RED',
            'options' => ['color' => 'Red'],
            'price' => 50000,
            'stock' => 5,
        ]);

        $inventory->adjustStock($product, -3, $variant);
        Event::assertDispatched(StockBelowReorder::class, function (StockBelowReorder $event) use ($product, $variant) {
            return $event->product->id === $product->id
                && $event->variant?->id === $variant->id
                && $event->stock === 2;
        });
    });
});

test('FR-CAT-03 categories can be created, listed, updated, reordered, and deleted', function () {
    inFreshStore(function () {
        $controller = app(CategoryController::class);

        $r1 = $controller->store(jsonRequest('POST', ['name' => 'Bags', 'name_ar' => 'شنط']));
        $r2 = $controller->store(jsonRequest('POST', ['name' => 'Wallets', 'name_ar' => 'محافظ']));
        $r3 = $controller->store(jsonRequest('POST', ['name' => 'Belts', 'name_ar' => 'أحزمة']));

        expect($r1->getStatusCode())->toBe(201);

        $bagsId = $r1->getData(true)['category']['id'];
        $walletsId = $r2->getData(true)['category']['id'];
        $beltsId = $r3->getData(true)['category']['id'];

        // Duplicate slug within same store is rejected.
        expect(fn () => $controller->store(jsonRequest('POST', ['name' => 'Bags'])))
            ->toThrow(ValidationException::class);

        // Reorder: Belts, Bags, Wallets.
        $reordered = $controller->reorder(jsonRequest('POST', ['order' => [$beltsId, $bagsId, $walletsId]]));
        $ids = array_column($reordered->getData(true)['categories'], 'id');
        expect($ids)->toBe([$beltsId, $bagsId, $walletsId]);

        // Update category.
        $updated = $controller->update(jsonRequest('PUT', ['name' => 'Leather Bags', 'slug' => 'leather-bags']), $bagsId);
        expect($updated->getData(true)['category']['slug'])->toBe('leather-bags');

        // Link a product, list with count, then delete category and verify product detaches.
        $product = Product::factory()->create(['category_id' => $bagsId]);
        expect($product->categoryRecord->id)->toBe($bagsId);

        $listed = $controller->index()->getData(true)['categories'];
        $bagsRow = collect($listed)->firstWhere('id', $bagsId);
        expect($bagsRow['products_count'])->toBe(1);

        $controller->destroy(jsonRequest('DELETE'), $bagsId);
        expect(Category::query()->find($bagsId))->toBeNull()
            ->and($product->fresh()->category_id)->toBeNull();
    });
});

test('FR-CAT-04 shopQuery filters by category, price range, stock and sorts active products', function () {
    inFreshStore(function () {
        $bags = Category::query()->create(['name' => 'Bags', 'slug' => 'bags']);
        $shoes = Category::query()->create(['name' => 'Shoes', 'slug' => 'shoes']);

        $cheap = Product::factory()->create([
            'name' => 'Mini Bag',
            'category_id' => $bags->id,
            'price' => 50000,
            'stock' => 5,
            'sold_count' => 2,
            'status' => ProductStatus::Live,
            'published_at' => now()->subDays(3),
        ]);
        $expensive = Product::factory()->create([
            'name' => 'Travel Duffel',
            'category_id' => $bags->id,
            'price' => 250000,
            'stock' => 10,
            'sold_count' => 40,
            'status' => ProductStatus::Live,
            'published_at' => now()->subDay(),
        ]);
        $soldOut = Product::factory()->create([
            'name' => 'Sold Out Bag',
            'category_id' => $bags->id,
            'price' => 120000,
            'stock' => 0,
            'sold_count' => 15,
            'status' => ProductStatus::Live,
            'published_at' => now(),
        ]);
        Product::factory()->create([
            'name' => 'Draft Bag',
            'category_id' => $bags->id,
            'price' => 100000,
            'stock' => 5,
            'status' => ProductStatus::Draft,
        ]);
        Product::factory()->create([
            'name' => 'Leather Loafers',
            'category_id' => $shoes->id,
            'price' => 180000,
            'stock' => 4,
            'sold_count' => 10,
            'status' => ProductStatus::Live,
        ]);

        $search = app(SearchService::class);

        // Filter by category slug + in_stock + sort price_asc.
        $page = $search->shopQuery([
            'category' => 'bags',
            'in_stock' => true,
            'sort' => 'price_asc',
        ]);
        expect($page->total())->toBe(2)
            ->and(collect($page->items())->pluck('id')->all())->toBe([$cheap->id, $expensive->id]);

        // Filter by price range + sort price_desc.
        $range = $search->shopQuery([
            'min_price' => 100000,
            'max_price' => 200000,
            'sort' => 'price_desc',
        ]);
        expect($range->total())->toBe(2)
            ->and(collect($range->items())->pluck('name')->all())->toBe(['Leather Loafers', 'Sold Out Bag']);

        // Sort by best_selling within category_id.
        $best = $search->shopQuery([
            'category_id' => $bags->id,
            'sort' => 'best_selling',
        ]);
        expect(collect($best->items())->pluck('id')->all())->toBe([$expensive->id, $soldOut->id, $cheap->id]);
    });
});

test('FR-CAT-05 Arabic normalization and live search suggestions match normalized forms', function () {
    $search = app(SearchService::class);

    expect($search->normalizeArabic('أَحمَد إِبرَاهِيم آيَة'))->toBe('احمد ابراهيم ايه')
        ->and($search->normalizeArabic('مَدْرَسَة مُوسَى'))->toBe('مدرسه موسي')
        ->and($search->normalizeArabic('Leather BAG'))->toBe('leather bag');

    inFreshStore(function () use ($search) {
        $live = Product::factory()->create([
            'name' => 'Classic Bag',
            'name_ar' => 'حَقيبَة جِلْد أَنيقَة',
            'description' => 'مصنوعة من جلد طبيعي فاخر',
            'status' => ProductStatus::Live,
        ]);
        Product::factory()->create([
            'name' => 'Hidden Draft',
            'name_ar' => 'حقيبة مسودة',
            'status' => ProductStatus::Draft,
        ]);

        // Query without diacritics, with ه instead of ة and ا instead of أ.
        $results = $search->suggest('حقيبه');
        expect($results)->toHaveCount(1)
            ->and($results->first()->id)->toBe($live->id);

        $byAlef = $search->suggest('انيقه');
        expect($byAlef)->toHaveCount(1)
            ->and($byAlef->first()->id)->toBe($live->id);

        expect($search->suggest('   '))->toHaveCount(0);
    });
});

test('FR-CAT-07 relatedProducts returns up to 4 active products in the same category ordered by sold_count desc', function () {
    inFreshStore(function () {
        $cat = Category::query()->create(['name' => 'Accessories', 'slug' => 'accessories']);
        $otherCat = Category::query()->create(['name' => 'Shoes', 'slug' => 'shoes']);

        $target = Product::factory()->create(['category_id' => $cat->id, 'status' => ProductStatus::Live, 'sold_count' => 99]);

        foreach ([5, 30, 12, 50, 20] as $sold) {
            Product::factory()->create(['category_id' => $cat->id, 'status' => ProductStatus::Live, 'sold_count' => $sold]);
        }

        // Draft in same category and live in other category must be excluded.
        Product::factory()->create(['category_id' => $cat->id, 'status' => ProductStatus::Draft, 'sold_count' => 500]);
        Product::factory()->create(['category_id' => $otherCat->id, 'status' => ProductStatus::Live, 'sold_count' => 500]);

        $related = app(SearchService::class)->relatedProducts($target);

        expect($related)->toHaveCount(4)
            ->and($related->pluck('sold_count')->all())->toBe([50, 30, 20, 12])
            ->and($related->pluck('id')->all())->not->toContain($target->id);
    });
});

test('FR-CAT-10 and FR-CAT-18 reviews require a delivered order and remain hidden until approved', function () {
    inFreshStore(function () {
        $product = Product::factory()->create(['status' => ProductStatus::Live]);
        $otherProduct = Product::factory()->create(['status' => ProductStatus::Live]);

        $pendingOrder = Order::query()->create([
            'number' => 1001,
            'status' => OrderStatus::Pending,
            'customer_name' => 'Nour',
            'email' => 'nour@example.com',
            'subtotal' => 10000,
            'total' => 10000,
        ]);
        $pendingOrder->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $deliveredOtherOrder = Order::query()->create([
            'number' => 1002,
            'status' => OrderStatus::Delivered,
            'customer_name' => 'Nour',
            'email' => 'nour@example.com',
            'subtotal' => 10000,
            'total' => 10000,
        ]);
        $deliveredOtherOrder->items()->create([
            'product_id' => $otherProduct->id,
            'name' => $otherProduct->name,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $deliveredOrder = Order::query()->create([
            'number' => 1003,
            'status' => OrderStatus::Delivered,
            'customer_name' => 'Salma',
            'email' => 'salma@example.com',
            'subtotal' => 10000,
            'total' => 10000,
        ]);
        $deliveredOrder->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $controller = app(ReviewController::class);

        // Non-delivered order rejected.
        expect(fn () => $controller->store(jsonRequest('POST', [
            'order_id' => $pendingOrder->id,
            'customer_name' => 'Nour',
            'rating' => 5,
        ]), $product->id))->toThrow(ValidationException::class);

        // Delivered order for a different product rejected.
        expect(fn () => $controller->store(jsonRequest('POST', [
            'order_id' => $deliveredOtherOrder->id,
            'customer_name' => 'Nour',
            'rating' => 5,
        ]), $product->id))->toThrow(ValidationException::class);

        // Delivered order containing this product succeeds, starts unapproved.
        $created = $controller->store(jsonRequest('POST', [
            'order_id' => $deliveredOrder->id,
            'customer_name' => 'Salma',
            'rating' => 5,
            'body' => 'Great quality leather!',
        ]), $product->id);

        expect($created->getStatusCode())->toBe(201);
        $reviewId = $created->getData(true)['review']['id'];

        expect($product->reviews()->count())->toBe(1)
            ->and($product->approvedReviews()->count())->toBe(0);

        // Merchant approves review -> appears in approvedReviews().
        $approved = $controller->approve(jsonRequest('POST'), $reviewId);
        expect($approved->getData(true)['review']['approved'])->toBeTrue()
            ->and($approved->getData(true)['review']['approved_at'])->not->toBeNull()
            ->and($product->approvedReviews()->count())->toBe(1);

        // Merchant deletes review.
        $controller->destroy(jsonRequest('DELETE'), $reviewId);
        expect(Review::query()->find($reviewId))->toBeNull();
    });
});

test('FR-CAT-11 static pages can be upserted for allowed slugs and reject unknown slugs', function () {
    inFreshStore(function () {
        $controller = app(StaticPageController::class);

        $saved = $controller->upsert(jsonRequest('PUT', [
            'title' => 'About Us',
            'title_ar' => 'من نحن',
            'body' => 'Handcrafted in Cairo.',
            'body_ar' => 'صناعة يدوية في القاهرة.',
        ]), 'about');

        expect($saved->getData(true)['page']['slug'])->toBe('about')
            ->and($saved->getData(true)['page']['title_ar'])->toBe('من نحن');

        // Upserting same slug updates in place.
        $controller->upsert(jsonRequest('PUT', [
            'title' => 'About Minivora',
            'body' => 'Updated story.',
        ]), 'about');

        expect(StaticPage::query()->count())->toBe(1)
            ->and(StaticPage::query()->sole()->title)->toBe('About Minivora');

        // Invalid slug rejected.
        expect(fn () => $controller->upsert(jsonRequest('PUT', ['title' => 'Nope']), 'secret-page'))
            ->toThrow(ValidationException::class);

        $index = $controller->index()->getData(true);
        expect($index['pages'])->toHaveCount(1)
            ->and($index['allowed_slugs'])->toBe(StaticPage::ALLOWED_SLUGS);
    });
});

test('FR-CAT-13 contact message inbox lists messages with unread count and marks them read', function () {
    inFreshStore(function () {
        $m1 = ContactMessage::query()->create([
            'name' => 'Omar',
            'email' => 'omar@example.com',
            'phone' => '01000000001',
            'message' => 'Do you ship to Alexandria?',
        ]);
        ContactMessage::query()->create([
            'name' => 'Mona',
            'email' => 'mona@example.com',
            'message' => 'Custom sizing question',
        ]);

        $controller = app(StaticPageController::class);

        $inbox = $controller->inbox(jsonRequest('GET'))->getData(true);
        expect($inbox['unread_count'])->toBe(2)
            ->and($inbox['messages']['total'])->toBe(2);

        $marked = $controller->markRead(jsonRequest('POST'), $m1->id)->getData(true);
        expect($marked['message']['read_at'])->not->toBeNull();

        $inboxAfter = $controller->inbox(jsonRequest('GET'))->getData(true);
        expect($inboxAfter['unread_count'])->toBe(1);
    });
});

test('wishlists support both authenticated users and guest sessions within store scope', function () {
    inFreshStore(function (Store $store, User $user) {
        $product = Product::factory()->create(['status' => ProductStatus::Live]);

        $userWish = Wishlist::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
        $guestWish = Wishlist::query()->create([
            'session_id' => 'guest-session-abc',
            'product_id' => $product->id,
        ]);

        expect($userWish->product->id)->toBe($product->id)
            ->and($userWish->user->id)->toBe($user->id)
            ->and($guestWish->session_id)->toBe('guest-session-abc')
            ->and(Wishlist::query()->count())->toBe(2);
    });
});
