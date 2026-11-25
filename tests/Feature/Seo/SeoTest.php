<?php

use App\Core\Tenancy\CurrentStore;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Review;
use App\Modules\Catalog\Models\StaticPage;
use App\Modules\Platform\Models\Store;
use App\Modules\Seo\Http\Controllers\FeedController;
use App\Modules\Seo\Http\Controllers\LlmsTxtController;
use App\Modules\Seo\Http\Controllers\RobotsController;
use App\Modules\Seo\Http\Controllers\SeoController;
use App\Modules\Seo\Http\Controllers\SitemapController;
use App\Modules\Seo\Models\SeoSetting;
use App\Modules\Seo\Services\FeedService;
use App\Modules\Seo\Services\JsonLdService;
use App\Modules\Seo\Services\RedirectService;
use App\Modules\Seo\Services\SeoAuditService;
use App\Modules\Seo\Services\SeoMetaService;
use App\Modules\Seo\Services\SitemapService;
use Illuminate\Http\Request;

test('FR-SEO-01 generates readable Arabic and English slugs and localized URLs', function () {
    $meta = new SeoMetaService;

    expect($meta->slugify('Classic Egyptian Cotton Shirt!'))
        ->toBe('classic-egyptian-cotton-shirt');

    expect($meta->slugify('قميص قطن مصري فاخر 100%'))
        ->toBe('قميص-قطن-مصري-فاخر-100');

    // Diacritics (tashkeel) and tatweel are stripped cleanly.
    expect($meta->slugify('قَمِيصٌ قُطْنـيٌ'))
        ->toBe('قميص-قطني');

    expect($meta->urlFor('https://nile.minivora.test/', 'ar', 'products', 'قميص-قطن'))
        ->toBe('https://nile.minivora.test/ar/products/قميص-قطن');

    expect($meta->urlFor('https://nile.minivora.test', 'en', 'categories', 'summer-wear'))
        ->toBe('https://nile.minivora.test/en/categories/summer-wear');
});

test('FR-SEO-02 FR-SEO-06 FR-SEO-08 builds meta tags with length limits, Open Graph, Twitter cards, and private noindex', function () {
    $meta = new SeoMetaService;

    $longTitle = str_repeat('Handmade Cairo Leather Bag ', 4);
    $longDescription = str_repeat('Ethically crafted Egyptian leather goods delivered across Cairo and Alexandria. ', 4);

    $publicTags = $meta->metaTags([
        'title' => $longTitle,
        'description' => $longDescription,
        'url' => 'https://nile.minivora.test/en/products/cairo-bag?utm_source=ig&sort=price',
        'image' => 'https://nile.minivora.test/storage/products/bag.webp',
        'hreflang' => $meta->hreflangLinks('https://nile.minivora.test', 'products', 'شنطة-القاهرة', 'cairo-bag'),
        'og_type' => 'product',
    ]);

    expect(mb_strlen($publicTags['title'], 'UTF-8'))->toBeLessThanOrEqual(60)
        ->and(mb_strlen($publicTags['description'], 'UTF-8'))->toBeLessThanOrEqual(155)
        ->and($publicTags['canonical'])->toBe('https://nile.minivora.test/en/products/cairo-bag')
        ->and($publicTags['robots'])->toBe('index, follow')
        ->and($publicTags['og']['og:title'])->toBe($publicTags['title'])
        ->and($publicTags['og']['og:description'])->toBe($publicTags['description'])
        ->and($publicTags['og']['og:image'])->toBe('https://nile.minivora.test/storage/products/bag.webp')
        ->and($publicTags['og']['og:url'])->toBe('https://nile.minivora.test/en/products/cairo-bag')
        ->and($publicTags['twitter']['twitter:card'])->toBe('summary_large_image')
        ->and($publicTags['twitter']['twitter:title'])->toBe($publicTags['title'])
        ->and($publicTags['twitter']['twitter:image'])->toBe('https://nile.minivora.test/storage/products/bag.webp');

    $privateCart = $meta->metaTags([
        'title' => 'Your Cart',
        'url' => 'https://nile.minivora.test/ar/cart',
    ]);

    $privateFlag = $meta->metaTags([
        'title' => 'Order Checkout',
        'url' => 'https://nile.minivora.test/en/custom-step',
        'is_private' => true,
    ]);

    expect($privateCart['robots'])->toBe('noindex, nofollow')
        ->and($privateFlag['robots'])->toBe('noindex, nofollow');
});

test('FR-SEO-03 strips filter, sort, utm, fbclid, and gclid parameters from canonical URLs', function () {
    $meta = new SeoMetaService;

    $dirty = 'https://nile.minivora.test/en/categories/shirts?filter=blue&sort=price_asc&utm_source=facebook&utm_medium=cpc&fbclid=abc123&gclid=xyz789#top';

    expect($meta->canonicalUrl($dirty))
        ->toBe('https://nile.minivora.test/en/categories/shirts');
});

test('FR-SEO-04 generates ar, en, and x-default hreflang links', function () {
    $meta = new SeoMetaService;

    $links = $meta->hreflangLinks('https://nile.minivora.test', 'products', 'قميص-كتان', 'linen-shirt');

    expect($links)->toBe([
        'ar' => 'https://nile.minivora.test/ar/products/قميص-كتان',
        'en' => 'https://nile.minivora.test/en/products/linen-shirt',
        'x-default' => 'https://nile.minivora.test/ar/products/قميص-كتان',
    ]);
});

test('FR-SEO-05 FR-SEO-09 generates valid bilingual XML sitemap with live products, images, categories, and static pages', function () {
    $store = Store::factory()->create(['subdomain' => 'nile-shop', 'name' => 'Nile Shop']);
    $current = app(CurrentStore::class);

    $current->runAs($store, function () {
        Product::factory()->create([
            'name' => 'Linen Shirt',
            'name_ar' => 'قميص كتان',
            'slug' => 'linen-shirt',
            'status' => ProductStatus::Live,
            'images' => ['products/linen-1.webp'],
        ]);

        Product::factory()->create([
            'name' => 'Draft Jacket',
            'slug' => 'draft-jacket',
            'status' => ProductStatus::Draft,
        ]);

        Category::query()->create([
            'name' => 'Summer',
            'name_ar' => 'الصيف',
            'slug' => 'summer',
        ]);

        StaticPage::query()->create([
            'slug' => 'about',
            'title' => 'About Us',
            'title_ar' => 'من نحن',
        ]);
    });

    $service = app(SitemapService::class);
    $xmlString = $service->generateXml($store, 'https://nile-shop.minivora.test');

    $xml = simplexml_load_string($xmlString);
    expect($xml)->not->toBeFalse()
        ->and($xmlString)->toContain('http://www.sitemaps.org/schemas/sitemap/0.9')
        ->and($xmlString)->toContain('http://www.google.com/schemas/sitemap-image/1.1')
        ->and($xmlString)->toContain('https://nile-shop.minivora.test/ar/products/linen-shirt')
        ->and($xmlString)->toContain('https://nile-shop.minivora.test/en/products/linen-shirt')
        ->and($xmlString)->toContain('https://nile-shop.minivora.test/storage/products/linen-1.webp')
        ->and($xmlString)->toContain('<lastmod>')
        ->and($xmlString)->toContain('https://nile-shop.minivora.test/ar/categories/summer')
        ->and($xmlString)->toContain('https://nile-shop.minivora.test/en/categories/summer')
        ->and($xmlString)->toContain('https://nile-shop.minivora.test/ar/pages/about')
        ->and($xmlString)->toContain('https://nile-shop.minivora.test/en/pages/about')
        ->and($xmlString)->not->toContain('draft-jacket');

    // Controller returns application/xml.
    $response = $current->runAs($store, function () {
        $controller = app(SitemapController::class);

        return $controller->index(Request::create('https://nile-shop.minivora.test/sitemap.xml', 'GET'));
    });

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('application/xml')
        ->and($response->getContent())->toContain('<urlset');
});

test('FR-SEO-06 generates robots.txt linking sitemap and disallowing private paths', function () {
    $store = Store::factory()->create(['subdomain' => 'cairo-crafts']);
    $current = app(CurrentStore::class);

    $current->runAs($store, function () {
        SeoSetting::query()->create([
            'robots_extra' => 'Disallow: /internal-preview',
        ]);
    });

    $response = $current->runAs($store, function () {
        $controller = app(RobotsController::class);

        return $controller->index(Request::create('https://cairo-crafts.minivora.test/robots.txt', 'GET'));
    });

    $body = (string) $response->getContent();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($body)->toContain('User-agent: *')
        ->and($body)->toContain('Disallow: /cart')
        ->and($body)->toContain('Disallow: /checkout')
        ->and($body)->toContain('Disallow: /account')
        ->and($body)->toContain('Disallow: /search')
        ->and($body)->toContain('Disallow: /admin')
        ->and($body)->toContain('Disallow: /internal-preview')
        ->and($body)->toContain('Sitemap: https://cairo-crafts.minivora.test/sitemap.xml');
});

test('FR-SEO-15 generates llms.txt structured Markdown summary for AI search tools', function () {
    $store = Store::factory()->create([
        'name' => 'Oasis Home',
        'subdomain' => 'oasis-home',
        'default_locale' => 'en',
        'profile' => ['sells' => 'Handwoven Egyptian textiles', 'location' => 'Cairo', 'language' => 'en'],
    ]);
    $current = app(CurrentStore::class);

    $current->runAs($store, function () {
        Product::factory()->create([
            'name' => 'Cotton Throw Blanket',
            'slug' => 'cotton-throw-blanket',
            'price' => 85000,
            'status' => ProductStatus::Live,
            'description' => 'Soft woven throw blanket.',
        ]);

        StaticPage::query()->create([
            'slug' => 'shipping',
            'title' => 'Shipping Policy',
        ]);
    });

    $response = $current->runAs($store, function () {
        $controller = app(LlmsTxtController::class);

        return $controller->index(Request::create('https://oasis-home.minivora.test/llms.txt', 'GET'));
    });

    $body = (string) $response->getContent();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($body)->toContain('# Oasis Home')
        ->and($body)->toContain('Handwoven Egyptian textiles')
        ->and($body)->toContain('## Products')
        ->and($body)->toContain('[Cotton Throw Blanket](https://oasis-home.minivora.test/en/products/cotton-throw-blanket) (850.00 EGP)')
        ->and($body)->toContain('## Pages')
        ->and($body)->toContain('[Shipping Policy](https://oasis-home.minivora.test/en/pages/shipping)');
});

test('FR-SEO-07 FR-SEO-10 builds JSON-LD for Organization, WebSite, Product with EGP Offer and reviews, Breadcrumbs, and FAQPage', function () {
    $store = Store::factory()->create(['name' => 'Delta Atelier', 'subdomain' => 'delta']);
    $current = app(CurrentStore::class);

    /** @var Product $product */
    $product = $current->runAs($store, fn () => Product::factory()->create([
        'name' => 'Nubian Ceramic Mug',
        'slug' => 'nubian-ceramic-mug',
        'sku' => 'NCM-01',
        'price' => 24950,
        'stock' => 12,
        'status' => ProductStatus::Live,
        'description' => 'Hand-painted ceramic mug.',
        'images' => ['products/mug.webp'],
    ]));

    /** @var list<Review> $reviews */
    $reviews = $current->runAs($store, fn () => [
        Review::query()->create([
            'product_id' => $product->id,
            'customer_name' => 'Salma',
            'rating' => 5,
            'body' => 'Beautiful glaze and finish.',
            'approved' => true,
        ]),
        Review::query()->create([
            'product_id' => $product->id,
            'customer_name' => 'Omar',
            'rating' => 4,
            'body' => 'Fast delivery to Giza.',
            'approved' => true,
        ]),
    ]);

    $jsonLd = new JsonLdService;
    $baseUrl = 'https://delta.minivora.test';

    $org = $jsonLd->organization($store, $baseUrl);
    expect($org['@type'])->toBe('Organization')
        ->and($org['name'])->toBe('Delta Atelier')
        ->and($org['url'])->toBe($baseUrl);

    $site = $jsonLd->website($store, $baseUrl);
    expect($site['@type'])->toBe('WebSite')
        ->and($site['potentialAction']['@type'])->toBe('SearchAction')
        ->and($site['potentialAction']['target'])->toContain('{search_term_string}');

    $prodSchema = $jsonLd->product($product, $store, "{$baseUrl}/en/products/nubian-ceramic-mug", $reviews);
    expect($prodSchema['@type'])->toBe('Product')
        ->and($prodSchema['offers']['priceCurrency'])->toBe('EGP')
        ->and($prodSchema['offers']['price'])->toBe('249.50')
        ->and($prodSchema['offers']['availability'])->toBe('https://schema.org/InStock')
        ->and($prodSchema['offers']['shippingDetails']['@type'])->toBe('OfferShippingDetails')
        ->and($prodSchema['offers']['hasMerchantReturnPolicy']['@type'])->toBe('MerchantReturnPolicy')
        ->and($prodSchema['aggregateRating']['@type'])->toBe('AggregateRating')
        ->and($prodSchema['aggregateRating']['ratingValue'])->toBe(4.5)
        ->and($prodSchema['aggregateRating']['reviewCount'])->toBe(2)
        ->and($prodSchema['review'])->toHaveCount(2);

    $prodNoReviews = $jsonLd->product($product, $store, "{$baseUrl}/en/products/nubian-ceramic-mug");
    expect($prodNoReviews)->not->toHaveKey('aggregateRating')
        ->and($prodNoReviews)->not->toHaveKey('review');

    $breadcrumbs = $jsonLd->breadcrumbs([
        ['name' => 'Home', 'url' => "{$baseUrl}/en"],
        ['name' => 'Ceramics', 'url' => "{$baseUrl}/en/categories/ceramics"],
        ['name' => 'Nubian Ceramic Mug', 'url' => "{$baseUrl}/en/products/nubian-ceramic-mug"],
    ]);
    expect($breadcrumbs['@type'])->toBe('BreadcrumbList')
        ->and($breadcrumbs['itemListElement'])->toHaveCount(3)
        ->and($breadcrumbs['itemListElement'][2]['position'])->toBe(3);

    $faq = $jsonLd->faqPage([
        ['question' => 'Do you ship nationwide?', 'answer' => 'Yes, across all 27 Egyptian governorates.'],
    ]);
    expect($faq['@type'])->toBe('FAQPage')
        ->and($faq['mainEntity'][0]['@type'])->toBe('Question')
        ->and($faq['mainEntity'][0]['acceptedAnswer']['text'])->toContain('27 Egyptian governorates');
});

test('FR-SEO-16 generates Google Merchant RSS 2.0 XML and Meta Catalog TSV feeds', function () {
    $store = Store::factory()->create(['name' => 'Alexandria Goods', 'subdomain' => 'alex-goods', 'default_locale' => 'en']);
    $current = app(CurrentStore::class);

    $current->runAs($store, function () {
        Product::factory()->create([
            'name' => 'Sea Salt Soap',
            'slug' => 'sea-salt-soap',
            'description' => 'Natural Mediterranean sea salt soap.',
            'price' => 12500,
            'stock' => 20,
            'status' => ProductStatus::Live,
            'images' => ['products/soap.webp'],
        ]);

        Product::factory()->create([
            'name' => 'Hidden Draft Soap',
            'slug' => 'hidden-draft-soap',
            'status' => ProductStatus::Draft,
        ]);
    });

    $feeds = app(FeedService::class);
    $rss = $feeds->googleMerchantRss($store, 'https://alex-goods.minivora.test');
    $xml = simplexml_load_string($rss);

    expect($xml)->not->toBeFalse()
        ->and($rss)->toContain('xmlns:g="http://base.google.com/ns/1.0"')
        ->and($rss)->toContain('<g:title>Sea Salt Soap</g:title>')
        ->and($rss)->toContain('<g:price>125.00 EGP</g:price>')
        ->and($rss)->toContain('<g:availability>in_stock</g:availability>')
        ->and($rss)->toContain('<g:link>https://alex-goods.minivora.test/en/products/sea-salt-soap</g:link>')
        ->and($rss)->not->toContain('Hidden Draft Soap');

    $tsv = $feeds->metaCatalogTsv($store, 'https://alex-goods.minivora.test');
    $lines = array_values(array_filter(explode("\n", trim($tsv))));

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toBe("id\ttitle\tdescription\tavailability\tcondition\tprice\tlink\timage_link")
        ->and($lines[1])->toContain("Sea Salt Soap\tNatural Mediterranean sea salt soap.\tin stock\tnew\t125.00 EGP");

    // Controller content types.
    [$googleResponse, $metaResponse] = $current->runAs($store, function () {
        $controller = app(FeedController::class);

        return [
            $controller->google(Request::create('https://alex-goods.minivora.test/feeds/google.xml', 'GET')),
            $controller->meta(Request::create('https://alex-goods.minivora.test/feeds/meta.tsv', 'GET')),
        ];
    });

    expect($googleResponse->headers->get('Content-Type'))->toContain('application/xml')
        ->and($metaResponse->headers->get('Content-Type'))->toContain('text/tab-separated-values');
});

test('FR-SEO-12 FR-SEO-23 FR-SEO-24 manages automatic and manual redirects, tracks hits, and aggregates 404 logs', function () {
    $store = Store::factory()->create();
    $current = app(CurrentStore::class);
    $redirects = app(RedirectService::class);

    $current->runAs($store, function () use ($redirects) {
        $auto = $redirects->recordSlugChange('/en/products/old-mug', '/en/products/new-mug');
        expect($auto->status_code)->toBe(301)
            ->and($auto->is_automatic)->toBeTrue()
            ->and($auto->hits)->toBe(0);

        // Chained rename collapses old-mug directly to final-mug.
        $redirects->recordSlugChange('/en/products/new-mug', '/en/products/final-mug');
        $resolved = $redirects->resolve('/en/products/old-mug');

        expect($resolved)->not->toBeNull()
            ->and($resolved->to_path)->toBe('/en/products/final-mug')
            ->and($resolved->hits)->toBe(1);

        // Manual redirect via controller + listing + destroy.
        $controller = app(SeoController::class);
        $created = $controller->storeRedirect(Request::create('/seo/redirects', 'POST', [
            'from_path' => '/promo-2025',
            'to_path' => '/en/categories/offers',
            'status_code' => 302,
        ]));

        expect($created->getStatusCode())->toBe(201);
        $createdId = $created->getData(true)['redirect']['id'];

        $list = $controller->redirects()->getData(true)['redirects'];
        expect($list)->toHaveCount(3);

        $deleted = $controller->destroyRedirect($createdId);
        expect($deleted->getData(true)['deleted'])->toBeTrue();

        // 404 logging & top aggregation.
        $redirects->logNotFound('/missing-page', 'https://google.com');
        $redirects->logNotFound('/missing-page', 'https://instagram.com');
        $redirects->logNotFound('/missing-page');
        $redirects->logNotFound('/other-missing');

        $top = $controller->notFoundLogs(Request::create('/seo/not-found', 'GET', ['limit' => 10]))
            ->getData(true)['logs'];

        expect($top)->toHaveCount(2)
            ->and($top[0]['path'])->toBe('/missing-page')
            ->and($top[0]['hits'])->toBe(3)
            ->and($top[1]['path'])->toBe('/other-missing')
            ->and($top[1]['hits'])->toBe(1);
    });
});

test('FR-SEO-13 FR-SEO-19 audits on-page SEO checks and computes 0..100 score', function () {
    $auditor = new SeoAuditService;

    $longContent = implode(' ', array_fill(0, 160, 'egyptian'));

    $perfect = $auditor->audit([
        'title' => 'Handmade Egyptian Cotton Linen Shirt | Nile',
        'description' => 'Shop breathable handmade Egyptian cotton linen shirts crafted in Cairo with nationwide delivery across Egypt and 14-day returns.',
        'h1_count' => 1,
        'images' => [
            ['src' => '/storage/a.webp', 'alt' => 'Egyptian cotton linen shirt front view'],
        ],
        'content' => $longContent,
        'json_ld' => ['@type' => 'Product'],
    ]);

    expect($perfect['score'])->toBe(100)
        ->and($perfect['issues'])->toBeEmpty();

    $broken = $auditor->audit(
        [
            'title' => 'Short',
            'description' => 'Too short.',
            'h1_count' => 2,
            'images' => [
                ['src' => '/storage/a.webp', 'alt' => ''],
            ],
            'content' => 'Only five words of content.',
            'json_ld' => [],
        ],
        otherTitles: ['short'],
        otherDescriptions: ['too short.'],
    );

    $codes = array_column($broken['issues'], 'code');

    expect($broken['score'])->toBeGreaterThanOrEqual(0)
        ->and($broken['score'])->toBeLessThan(50)
        ->and($codes)->toContain('title_length')
        ->and($codes)->toContain('duplicate_title')
        ->and($codes)->toContain('description_length')
        ->and($codes)->toContain('duplicate_description')
        ->and($codes)->toContain('multiple_h1')
        ->and($codes)->toContain('missing_image_alt')
        ->and($codes)->toContain('thin_content')
        ->and($codes)->toContain('missing_json_ld');

    // Controller audit endpoint.
    $controller = app(SeoController::class);
    $response = $controller->audit(Request::create('/seo/audit', 'POST', [
        'title' => 'Handmade Egyptian Cotton Linen Shirt | Nile',
        'description' => 'Shop breathable handmade Egyptian cotton linen shirts crafted in Cairo with nationwide delivery across Egypt and 14-day returns.',
        'h1_count' => 1,
        'images' => [['src' => '/storage/a.webp', 'alt' => 'Linen shirt']],
        'word_count' => 200,
        'json_ld' => true,
    ]));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['score'])->toBe(100);
});

test('V-06 end-to-end SEO engine verification across sitemap, feeds, JSON-LD, redirects, and tenant isolation', function () {
    $storeA = Store::factory()->create(['name' => 'Store Alpha', 'subdomain' => 'alpha-seo']);
    $storeB = Store::factory()->create(['name' => 'Store Beta', 'subdomain' => 'beta-seo']);
    $current = app(CurrentStore::class);

    $current->runAs($storeA, function () {
        Product::factory()->create([
            'name' => 'Alpha Scarf',
            'slug' => 'alpha-scarf',
            'price' => 35000,
            'stock' => 5,
            'status' => ProductStatus::Live,
            'images' => ['products/alpha.webp'],
        ]);
        app(RedirectService::class)->addManual('/old-alpha', '/en/products/alpha-scarf');
    });

    $current->runAs($storeB, function () {
        Product::factory()->create([
            'name' => 'Beta Perfume',
            'slug' => 'beta-perfume',
            'price' => 99000,
            'stock' => 3,
            'status' => ProductStatus::Live,
        ]);
        app(RedirectService::class)->addManual('/old-beta', '/en/products/beta-perfume');
    });

    $sitemapA = app(SitemapService::class)->generateXml($storeA, 'https://alpha-seo.minivora.test');
    $rssA = app(FeedService::class)->googleMerchantRss($storeA, 'https://alpha-seo.minivora.test');
    $tsvA = app(FeedService::class)->metaCatalogTsv($storeA, 'https://alpha-seo.minivora.test');
    $llmsA = app(SitemapService::class)->llmsTxt($storeA, 'https://alpha-seo.minivora.test');

    expect($sitemapA)->toContain('alpha-scarf')->not->toContain('beta-perfume')
        ->and($rssA)->toContain('Alpha Scarf')->not->toContain('Beta Perfume')
        ->and($tsvA)->toContain('Alpha Scarf')->not->toContain('Beta Perfume')
        ->and($llmsA)->toContain('Alpha Scarf')->not->toContain('Beta Perfume');

    $current->runAs($storeA, function () {
        expect(app(RedirectService::class)->resolve('/old-alpha'))->not->toBeNull()
            ->and(app(RedirectService::class)->resolve('/old-beta'))->toBeNull();
    });
});
