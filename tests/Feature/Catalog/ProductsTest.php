<?php

use App\Core\AuditLog\ActivityLog;
use App\Core\Permissions\Role;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductNoteParser;
use App\Modules\Platform\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/** Create products inside a store without going through HTTP. */
function productsIn(Store $store, int $count = 1, array $state = []): void
{
    app(CurrentStore::class)->runAs($store, fn () => Product::factory()->count($count)->create($state));
}

test('FR-CAT-01 an owner starts a draft from a short note and lands on the review step', function () {
    [, $store] = signedInMember();

    $response = $this->post(route('products.store'), ['note' => 'add Brown leather duffel bag, 2,150 pounds, 6 pieces']);

    $product = app(CurrentStore::class)->runAs($store, fn () => Product::query()->sole());
    $response->assertRedirect(route('products.edit', $product->id));
    expect($product->name)->toBe('Brown leather duffel bag')
        ->and($product->price)->toBe(215000)
        ->and($product->stock)->toBe(6)
        ->and($product->status)->toBe(ProductStatus::Draft)
        ->and($product->source)->toBe('note');
});

test('FR-CAT-01 photos start a draft and are stored on the public disk', function () {
    Storage::fake('public');
    [, $store] = signedInMember();

    $this->post(route('products.store'), ['photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]])
        ->assertSessionHasNoErrors();

    $product = app(CurrentStore::class)->runAs($store, fn () => Product::query()->sole());
    expect($product->images)->toHaveCount(2);
    Storage::disk('public')->assertExists($product->images);
});

test('FR-CAT-01 saving and publishing from the review step', function () {
    [, $store] = signedInMember();
    productsIn($store, 1, ['name' => 'Weekend Duffel', 'price' => 0]);
    $product = app(CurrentStore::class)->runAs($store, fn () => Product::query()->sole());

    $form = [
        'name' => 'Weekend Duffel',
        'name_ar' => 'شنطة سفر ويك إند',
        'price' => '2,150',
        'stock' => 6,
        'has_options' => true,
        'colors' => [['name' => 'Brown', 'hex' => '#8B5A2B'], ['name' => 'Black', 'hex' => '#111111']],
        'keep_images' => [],
        'intent' => 'publish',
    ];

    $this->put(route('products.update', $product->id), $form)->assertRedirect(route('products.index'));

    $product->refresh();
    expect($product->status)->toBe(ProductStatus::Live)
        ->and($product->price)->toBe(215000)
        ->and($product->colors)->toHaveCount(2)
        ->and($product->published_at)->not->toBeNull()
        ->and(ActivityLog::query()->where('action', 'product.published')->exists())->toBeTrue();
});

test('FR-CAT-01 a product cannot go live without a price', function () {
    [, $store] = signedInMember();
    productsIn($store, 1, ['price' => 0]);
    $product = app(CurrentStore::class)->runAs($store, fn () => Product::query()->sole());

    $this->put(route('products.update', $product->id), ['name' => 'Belt', 'price' => '0', 'stock' => 3, 'intent' => 'publish'])
        ->assertSessionHasErrors('publish');

    expect($product->fresh()->status)->toBe(ProductStatus::Draft);
});

test('FR-CAT-01 bad amounts are rejected with a clear message', function () {
    [, $store] = signedInMember();
    productsIn($store);
    $product = app(CurrentStore::class)->runAs($store, fn () => Product::query()->sole());

    $this->put(route('products.update', $product->id), ['name' => 'Belt', 'price' => 'twelve', 'stock' => 3])
        ->assertSessionHasErrors('price');
});

test('FR-CAT-01 the list filters by status, stock and search, with counts', function () {
    [, $store] = signedInMember();
    productsIn($store, 2, ['status' => ProductStatus::Live, 'stock' => 20]);
    productsIn($store, 1, ['status' => ProductStatus::Live, 'stock' => 3, 'name' => 'Belt Cognac']);
    productsIn($store, 1, ['status' => ProductStatus::Live, 'stock' => 0]);
    productsIn($store, 1, ['status' => ProductStatus::Draft]);

    $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
        ->component('products/index')
        ->where('counts.all', 5)
        ->where('counts.live', 4)
        ->where('counts.draft', 1)
        ->where('counts.low', 1)
        ->where('counts.sold_out', 1)
        ->has('products.data', 5));

    $this->get(route('products.index', ['filter' => 'low']))->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Belt Cognac'));
    $this->get(route('products.index', ['q' => 'cognac']))->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
});

test('NFR-SEC-05 another store\'s product is a 404', function () {
    $other = Store::factory()->create();
    productsIn($other);
    $foreign = app(CurrentStore::class)->runAs($other, fn () => Product::query()->sole());

    signedInMember();

    $this->get(route('products.edit', $foreign->id))->assertNotFound();
    $this->put(route('products.update', $foreign->id), ['name' => 'x', 'price' => '1', 'stock' => 1])->assertNotFound();
    $this->delete(route('products.destroy', $foreign->id))->assertNotFound();
});

test('FR-ACC-03 staff can view products but not change them', function () {
    [, $store] = signedInMember(Role::Staff);
    productsIn($store);

    $this->get(route('products.index'))->assertOk();
    $this->get(route('products.create'))->assertForbidden();
    $this->post(route('products.store'), ['note' => 'Belt, 100 pounds'])->assertForbidden();
});

test('FR-CAT-01 CSV import creates drafts', function () {
    [, $store] = signedInMember();
    $csv = UploadedFile::fake()->createWithContent('products.csv', "name,price,stock,name_ar\nCard Holder,220,58,حافظة كروت\nMini Wallet,345,0,\n,10,1,\n");

    $this->post(route('products.import'), ['csv' => $csv])->assertRedirect(route('products.index', ['filter' => 'draft']));

    $names = app(CurrentStore::class)->runAs($store, fn () => Product::query()->orderBy('name')->pluck('price', 'name')->all());
    expect($names)->toBe(['Card Holder' => 22000, 'Mini Wallet' => 34500]);
});

test('FR-CAT-01 deleting a product removes it and its photos', function () {
    Storage::fake('public');
    [, $store] = signedInMember();
    $this->post(route('products.store'), ['photos' => [UploadedFile::fake()->image('a.jpg')]]);
    $product = app(CurrentStore::class)->runAs($store, fn () => Product::query()->sole());

    $this->delete(route('products.destroy', $product->id))->assertRedirect(route('products.index'));

    expect(app(CurrentStore::class)->runAs($store, fn () => Product::query()->count()))->toBe(0);
    Storage::disk('public')->assertMissing($product->images[0]);
});

test('FR-ONB-10 adding a product completes the checklist step and the dashboard shows stock', function () {
    [, $store] = signedInMember();
    productsIn($store, 1, ['status' => ProductStatus::Live, 'stock' => 2, 'name' => 'Brown Tote']);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('today/index')
        ->where('checklist.steps.1.key', 'add_products')
        ->where('checklist.steps.1.complete', true)
        ->where('inventory.total', 1)
        ->where('inventory.running_low.0.name', 'Brown Tote')
        ->where('attention.0.kind', 'low')
        ->where('sales', null));
});

test('the note parser reads Arabic notes and Eastern digits', function () {
    $parsed = (new ProductNoteParser)->parse('شنطة جلد بني، ٢١٥٠ جنيه، ٦ قطع');

    expect($parsed)->toBe(['name' => 'شنطة جلد بني', 'price' => 215000, 'stock' => 6]);
});

test('the note parser understands "for 1,250 pounds"', function () {
    $parsed = (new ProductNoteParser)->parse('add a leather bag for 1,250 pounds');

    expect($parsed['name'])->toBe('A leather bag')
        ->and($parsed['price'])->toBe(125000)
        ->and($parsed['stock'])->toBeNull();
});
