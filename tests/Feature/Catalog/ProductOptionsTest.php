<?php

use App\Core\Tenancy\CurrentStore;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Data\CheckoutData;
use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Validation\ValidationException;

function saveProduct(Product $product, array $overrides = []): void
{
    test()->put("/products/{$product->id}", array_merge([
        'name' => 'Leather Tote',
        'price' => '1000',
        'stock' => 0,
        'has_options' => true,
        'colors' => [['name' => 'Black', 'hex' => '#000000'], ['name' => 'Tan', 'hex' => '#c19a6b']],
        'sizes' => ['M', 'L'],
        'variants' => [
            ['color' => 'Black', 'size' => 'M', 'stock' => 3, 'price' => ''],
            ['color' => 'Black', 'size' => 'L', 'stock' => 0, 'price' => '1100'],
            ['color' => 'Tan', 'size' => 'M', 'stock' => 2, 'price' => null],
            ['color' => 'Tan', 'size' => 'L', 'stock' => 5, 'price' => ''],
        ],
        'keep_images' => [],
    ], $overrides))->assertSessionHasNoErrors();
}

test('FR-CAT-02 sizes and colours become variants; product stock is their total; ids stay stable', function () {
    [, $store] = signedInMember();
    $product = Product::factory()->create(['store_id' => $store->id, 'price' => 100000, 'stock' => 9]);

    saveProduct($product);
    $product->refresh()->load('variants');

    expect($product->variants)->toHaveCount(4)
        ->and($product->stock)->toBe(10)
        ->and($product->variants->first(fn ($v) => $v->label() === 'Black / L')->price)->toBe(110000)
        ->and($product->variants->first(fn ($v) => $v->label() === 'Black / M')->price)->toBe(100000);

    $ids = $product->variants->pluck('id')->sort()->values()->all();
    saveProduct($product); // saving again keeps the same rows
    expect($product->fresh()->variants->pluck('id')->sort()->values()->all())->toBe($ids);

    // The editor sends them back for editing, in display order.
    $this->get("/products/{$product->id}/edit")->assertInertia(fn ($page) => $page
        ->where('product.sizes', ['M', 'L'])
        ->where('product.variants.1.price', 110000)
        ->etc());

    // Turning options off removes them.
    saveProduct($product, ['has_options' => false, 'stock' => 4]);
    expect($product->fresh()->variants()->count())->toBe(0)->and($product->fresh()->stock)->toBe(4);
});

test('a typed category links the product to that category (matching names, or creating it)', function () {
    [, $store] = signedInMember();
    app(CurrentStore::class)->set($store);
    $bags = Category::query()->create(['name' => 'Bags', 'name_ar' => 'حقائب', 'slug' => 'bags']);
    $product = Product::factory()->create(['store_id' => $store->id]);

    saveProduct($product, ['category' => 'bags', 'has_options' => false, 'stock' => 1]);
    expect($product->fresh()->category_id)->toBe($bags->id);

    saveProduct($product, ['category' => 'حقائب', 'has_options' => false, 'stock' => 1]);
    expect($product->fresh()->category_id)->toBe($bags->id);

    saveProduct($product, ['category' => 'Wallets', 'has_options' => false, 'stock' => 1]);
    app(CurrentStore::class)->set($store);
    expect(Category::query()->where('slug', 'wallets')->value('id'))->toBe($product->fresh()->category_id);
});

test('FR-CAT-02 the cart needs an option, charges its price and respects its stock; checkout deducts and cancelling restocks it', function () {
    [, $store] = signedInMember();
    app(CurrentStore::class)->set($store);
    $product = Product::factory()->create(['store_id' => $store->id, 'price' => 100000, 'status' => ProductStatus::Live]);
    saveProduct($product);
    $product->refresh();
    $blackL = ProductVariant::query()->where('product_id', $product->id)->get()->first(fn ($v) => $v->label() === 'Black / L');
    $tanL = ProductVariant::query()->where('product_id', $product->id)->get()->first(fn ($v) => $v->label() === 'Tan / L');
    $tanL->update(['price' => 120000]);

    $carts = app(CartService::class);
    $cart = $carts->resolve(null, 'shopper-session');

    expect(fn () => $carts->addItem($cart, $product->id, 1))->toThrow(ValidationException::class);
    expect(fn () => $carts->addItem($cart, $product->id, 1, $blackL->id))->toThrow(ValidationException::class); // 0 in stock
    expect(fn () => $carts->addItem($cart, $product->id, 6, $tanL->id))->toThrow(ValidationException::class); // only 5

    $carts->addItem($cart, $product->id, 2, $tanL->id);
    $summary = $carts->summary($cart);
    expect($summary['items'][0]['unit_price'])->toBe(120000)->and($summary['items'][0]['variant'])->toBe('Tan / L');

    $result = app(CheckoutService::class)->placeOrder(CheckoutData::from([
        'name' => 'Salma', 'phone' => '01012345678', 'governorate' => 'cairo', 'address' => 'Zamalek', 'payment_method' => 'cod',
    ]), $cart);

    $item = $result['order']->items->first();
    expect($item->variant_id)->toBe($tanL->id)
        ->and($item->variant)->toBe('Tan / L')
        ->and($item->unit_price)->toBe(120000)
        ->and($tanL->fresh()->stock)->toBe(3)
        ->and($product->fresh()->stock)->toBe(8);

    app(OrderService::class)->changeStatus($result['order']->fresh('items'), OrderStatus::Cancelled);
    expect($tanL->fresh()->stock)->toBe(5)->and($product->fresh()->stock)->toBe(10);
});
