<?php

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\StoreUrl;
use App\Modules\Builder\Models\Page;
use App\Modules\Builder\Models\Section;
use App\Modules\Builder\Services\CssSanitizer;
use App\Modules\Builder\Services\HtmlSanitizer;
use App\Modules\Builder\Services\PageBuilderService;
use App\Modules\Builder\Services\SectionRegistry;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\Route;

/** A published store with a home page; returns [user, store, page]. */
function liveBuilderStore(): array
{
    [$user, $store] = signedInMember();
    $store->update(['published_at' => now()]);
    app(CurrentStore::class)->set($store);
    $page = app(PageBuilderService::class)->createPage(['title' => 'Home', 'is_home' => true, 'slug' => 'home']);

    return [$user, $store, $page];
}

test('FR-BLD-01 every section type has a storefront view that renders with defaults', function () {
    [, $store] = liveBuilderStore();
    $registry = app(SectionRegistry::class);

    foreach ($registry->types() as $type) {
        expect(view()->exists('storefront.sections.'.str_replace('_', '-', $type)))->toBeTrue("missing view for {$type}");
    }

    $page = Page::query()->where('is_home', true)->first();
    $sections = collect($registry->types())
        ->filter(fn ($t) => $registry->group($t) === 'template')
        ->map(fn ($t) => ['type' => $t, 'settings' => $registry->defaults($t)])->values()->all();
    app(PageBuilderService::class)->syncSections($page, $sections);

    $this->get('/builder/preview/'.$page->id.'?locale=en')->assertOk()->assertSee('mv-section--hero', false)->assertSee('mv-section--custom-blocks', false);
    $this->get('/builder/preview/'.$page->id.'?locale=ar')->assertOk()->assertSee('dir="rtl"', false);
});

test('FR-BLD-06 FR-BLD-07 shoppers see only the published version, drafts stay private', function () {
    [$user, $store, $page] = liveBuilderStore();
    $builder = app(PageBuilderService::class);

    $builder->syncSections($page, [['type' => 'hero', 'settings' => ['headline' => 'First launch']]]);
    $this->get(storeUrl($store->subdomain, '/en'))->assertOk()->assertDontSee('First launch');

    $builder->publishPage($page, $user);
    $this->get(storeUrl($store->subdomain, '/en'))->assertSee('First launch');

    $hero = Section::query()->where('page_id', $page->id)->first();
    $builder->syncSections($page, [['id' => $hero->id, 'type' => 'hero', 'settings' => ['headline' => 'Secret draft']]]);
    $this->get(storeUrl($store->subdomain, '/en'))->assertSee('First launch')->assertDontSee('Secret draft');
    $this->get('/builder/preview/'.$page->id)->assertSee('Secret draft');
});

test('FR-BLD-02 the editor saves a whole page, keeps ids, deletes removed sections and rejects misplaced types', function () {
    [, , $page] = liveBuilderStore();

    $res = $this->putJson("/builder/pages/{$page->id}/sections", ['sections' => [
        ['type' => 'marquee', 'settings' => ['blocks' => [['id' => 'a', 'type' => 'item', 'settings' => ['text' => 'Drop']]]]],
        ['type' => 'text_block', 'is_visible' => false, 'settings' => ['heading' => 'Hi']],
    ]])->assertOk();

    $ids = collect($res->json('sections'))->pluck('id');
    expect($ids)->toHaveCount(2);

    $this->putJson("/builder/pages/{$page->id}/sections", ['sections' => [
        ['id' => $ids[1], 'type' => 'text_block', 'settings' => ['heading' => 'Moved up']],
    ]])->assertOk()->assertJsonPath('sections.0.id', $ids[1]);

    expect(Section::query()->where('page_id', $page->id)->count())->toBe(1);

    // Header-only sections cannot go on a normal page.
    $this->putJson("/builder/pages/{$page->id}/sections", ['sections' => [['type' => 'header']]])->assertUnprocessable();
});

test('FR-BLD-02 live preview renders one section from unsaved settings with scoped custom CSS', function () {
    liveBuilderStore();

    $html = $this->postJson('/builder/render-section', ['locale' => 'en', 'section' => [
        'id' => 42, 'type' => 'custom_blocks', 'settings' => [
            'custom_css' => '& .mv-btn { color: red } </style><script>x</script>',
            'blocks' => [['id' => 'h', 'type' => 'heading', 'settings' => ['text' => 'Hello blocks']], ['id' => 'x', 'type' => 'html', 'settings' => ['code' => '<b onclick="steal()">bold</b><script>alert(1)</script>']]],
        ],
    ]])->assertOk()->json('html');

    expect($html)->toContain('Hello blocks')
        ->toContain('#mv-s-42 .mv-btn { color: red }')
        ->toContain('<b>bold</b>')
        ->not->toContain('<script>')
        ->not->toContain('onclick')
        ->not->toContain('</style><script>');
});

test('storefront shop, category, product and custom pages render live products only', function () {
    [$user, $store, $home] = liveBuilderStore();
    $live = Product::factory()->live()->create(['store_id' => $store->id, 'name' => 'Leather Bag', 'slug' => 'leather-bag', 'price' => 125000]);
    Product::factory()->create(['store_id' => $store->id, 'name' => 'Hidden Draft Item', 'slug' => 'hidden']);

    $this->get(storeUrl($store->subdomain, '/en/shop'))->assertOk()->assertSee('Leather Bag')->assertDontSee('Hidden Draft Item');
    $this->get(storeUrl($store->subdomain, '/en/products/leather-bag'))->assertOk()->assertSee('1,250.00 EGP')->assertSee('data-product-form', false);
    $this->get(storeUrl($store->subdomain, '/en/products/hidden'))->assertNotFound();
    $this->get(storeUrl($store->subdomain, '/en/search?q=leather'))->assertOk()->assertSee('Leather Bag');

    $about = app(PageBuilderService::class)->createPage(['title' => 'About', 'slug' => 'about']);
    app(PageBuilderService::class)->syncSections($about, [['type' => 'text_block', 'settings' => ['heading' => 'Our atelier']]]);
    $this->get(storeUrl($store->subdomain, '/en/pages/about'))->assertNotFound();
    app(PageBuilderService::class)->publishPage($about, $user);
    $this->get(storeUrl($store->subdomain, '/ar/pages/about'))->assertOk()->assertSee('Our atelier');
});

test('the header and footer groups are created once and render on every page', function () {
    [, $store, $page] = liveBuilderStore();
    $groups = app(PageBuilderService::class)->ensureGroupPages();
    app(PageBuilderService::class)->ensureGroupPages();

    expect(Page::query()->where('slug', '__header')->count())->toBe(1)
        ->and($groups['header']->sections()->pluck('type')->all())->toBe(['announcement_bar', 'header']);

    $this->get(storeUrl($store->subdomain, '/en'))->assertSee('mv-section--header', false)->assertSee('mv-section--footer', false);
    $this->get(storeUrl($store->subdomain, '/en/pages/__header'))->assertNotFound();
});

test('path mode serves stores at /s/<store> without wildcard subdomains', function () {
    config(['minivora.storefront_mode' => 'path', 'app.url' => 'https://host.example']);

    expect(StoreUrl::base('adorna'))->toBe('https://host.example/s/adorna')
        ->and(StoreUrl::prefix('adorna'))->toBe('/s/adorna');

    // Re-register the storefront routes the way bootstrap/app.php does, now in path mode.
    Route::middleware('web')->group(base_path('routes/storefront.php'));
    app('router')->getRoutes()->refreshNameLookups();

    $store = Store::factory()->create(['subdomain' => 'adorna', 'published_at' => now()]);
    $this->get('/s/adorna')->assertRedirect('/s/adorna/'.$store->default_locale);
    $this->get('/s/adorna/en')->assertOk()->assertSee('href="/s/adorna/en/shop"', false);
    $this->get('/s/adorna/en/shop')->assertOk();
});

test('sanitizers strip script vectors but keep safe markup', function () {
    $clean = HtmlSanitizer::clean('<div class="x" onmouseover="a()"><a href=" javascript:alert(1)">x</a><iframe src="https://evil.test"></iframe><iframe src="https://www.youtube.com/embed/abc"></iframe><svg><set attributeName="href" to="javascript:1"/></svg><img src="/a.png" onerror="b()"></div>');

    expect($clean)->not->toContain('onmouseover')->not->toContain('javascript:')->not->toContain('evil.test')->not->toContain('onerror')->not->toContain('<set')
        ->toContain('youtube.com/embed/abc')->toContain('class="x"')->toContain('src="/a.png"');

    expect(HtmlSanitizer::rich("Line one\nLine <b>two</b><script>x</script>"))->toContain('Line one<br>')->toContain('<b>two</b>')->not->toContain('script');
    expect(CssSanitizer::scope('& h2{color:red}</style>', '#s'))->toBe('#s h2{color:red}/style>');
});

test('inline-edit markers appear in the editor preview only, never on the live store', function () {
    [$user, $store, $page] = liveBuilderStore();
    app(PageBuilderService::class)->syncSections($page, [['type' => 'hero', 'settings' => ['headline' => 'Hello', 'image' => '/x.jpg']]]);
    app(PageBuilderService::class)->publishPage($page, $user);

    $this->get('/builder/preview/'.$page->id)->assertSee('data-mv-field="headline"', false)->assertSee('data-mv-image="image"', false);
    $this->get(storeUrl($store->subdomain, '/en'))->assertSee('Hello')->assertDontSee('data-mv-field', false)->assertDontSee('data-mv-image', false);
});

test('the Preview button shows the draft like a shopper would: no editing tools, hidden sections hidden', function () {
    [, , $page] = liveBuilderStore();
    app(PageBuilderService::class)->syncSections($page, [
        ['type' => 'hero', 'settings' => ['headline' => 'Draft headline']],
        ['type' => 'text_block', 'is_visible' => false, 'settings' => ['heading' => 'Hidden one']],
    ]);

    $this->get('/builder/preview/'.$page->id.'?share=1&locale=en')
        ->assertOk()
        ->assertSee('Draft headline')
        ->assertSee('mv-preview-bar', false)
        ->assertSee('/builder?page='.$page->id, false)
        ->assertDontSee('Hidden one')
        ->assertDontSee('data-mv-field', false)
        ->assertDontSee('data-mv-section', false);

    // The editor frame still has its tools and shows hidden sections faded.
    $this->get('/builder/preview/'.$page->id)->assertSee('Hidden one')->assertSee('data-mv-section', false);
});

test('several sections render in one request for in-place preview updates', function () {
    liveBuilderStore();

    $htmls = $this->postJson('/builder/render-section', ['locale' => 'en', 'sections' => [
        ['id' => 7, 'type' => 'text_block', 'settings' => ['heading' => 'One']],
        ['id' => 8, 'type' => 'spacer', 'settings' => []],
    ]])->assertOk()->json('htmls');

    expect(array_map('strval', array_keys($htmls)))->toBe(['7', '8'])->and($htmls['7'])->toContain('One')->toContain('data-mv-section="7"');
});
