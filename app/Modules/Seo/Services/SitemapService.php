<?php

namespace App\Modules\Seo\Services;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StaticPage;
use App\Modules\Platform\Models\Store;
use App\Modules\Seo\Models\SeoSetting;

/**
 * Generates per-store XML sitemaps (AR + EN), robots.txt, and llms.txt. FR-SEO-05, FR-SEO-06, FR-SEO-15
 */
class SitemapService
{
    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    /** @var list<string> */
    private const DISALLOWED_PATHS = [
        '/cart',
        '/checkout',
        '/account',
        '/search',
        '/admin',
    ];

    public function __construct(private readonly SeoMetaService $meta = new SeoMetaService) {}

    /**
     * Build a valid XML sitemap with image extensions covering live products, categories,
     * and static pages in both /ar/ and /en/. FR-SEO-05
     */
    public function generateXml(Store $store, string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">',
        ];

        $storeLastMod = ($store->updated_at ?? $store->created_at)?->toAtomString();

        foreach (self::LOCALES as $locale) {
            $lines[] = $this->urlNode("{$base}/{$locale}", $storeLastMod);
        }

        $products = Product::withoutStoreScope()
            ->where('store_id', $store->id)
            ->where('status', ProductStatus::Live->value)
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            $slug = $product->slug ?: $this->meta->slugify($product->name) ?: (string) $product->id;
            $lastMod = ($product->updated_at ?? $product->created_at)?->toAtomString();
            $images = $this->productImages($product, $base);

            foreach (self::LOCALES as $locale) {
                $loc = $this->meta->urlFor($base, $locale, 'products', $slug);
                $title = $locale === 'ar' && $product->name_ar ? $product->name_ar : $product->name;
                $lines[] = $this->urlNode($loc, $lastMod, $images, $title);
            }
        }

        $categories = Category::withoutStoreScope()
            ->where('store_id', $store->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($categories as $category) {
            $slug = $category->slug ?: $this->meta->slugify($category->name);
            $lastMod = ($category->updated_at ?? $category->created_at)?->toAtomString();

            foreach (self::LOCALES as $locale) {
                $loc = $this->meta->urlFor($base, $locale, 'categories', $slug);
                $lines[] = $this->urlNode($loc, $lastMod);
            }
        }

        $pages = StaticPage::withoutStoreScope()
            ->where('store_id', $store->id)
            ->orderBy('slug')
            ->get();

        foreach ($pages as $page) {
            $lastMod = ($page->updated_at ?? $page->created_at)?->toAtomString();

            foreach (self::LOCALES as $locale) {
                $loc = $this->meta->urlFor($base, $locale, 'pages', $page->slug);
                $lines[] = $this->urlNode($loc, $lastMod);
            }
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    /**
     * Build robots.txt disallowing private routes and linking the store's sitemap.xml. FR-SEO-06
     */
    public function robotsTxt(string $baseUrl, ?Store $store = null): string
    {
        $base = rtrim($baseUrl, '/');
        $lines = [
            'User-agent: *',
        ];

        foreach (self::DISALLOWED_PATHS as $path) {
            $lines[] = "Disallow: {$path}";
        }

        if ($store !== null) {
            $extra = SeoSetting::withoutStoreScope()
                ->where('store_id', $store->id)
                ->value('robots_extra');

            if (is_string($extra) && trim($extra) !== '') {
                $lines[] = trim($extra);
            }
        }

        $lines[] = '';
        $lines[] = "Sitemap: {$base}/sitemap.xml";

        return implode("\n", $lines)."\n";
    }

    /**
     * Build a structured Markdown summary of the store, live products, categories, and pages
     * for AI search tools and LLM crawlers. FR-SEO-15
     */
    public function llmsTxt(Store $store, string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');
        $profile = $store->profileData();

        $lines = [
            "# {$store->name}",
            '',
        ];

        if ($profile->sells !== null && trim($profile->sells) !== '') {
            $lines[] = "> {$profile->sells}";
            $lines[] = '';
        }

        $lines[] = "- Storefront (AR): {$base}/ar";
        $lines[] = "- Storefront (EN): {$base}/en";
        if ($profile->location !== null && trim($profile->location) !== '') {
            $lines[] = "- Location: {$profile->location}";
        }
        $lines[] = "- Sitemap: {$base}/sitemap.xml";
        $lines[] = '';

        $products = Product::withoutStoreScope()
            ->where('store_id', $store->id)
            ->where('status', ProductStatus::Live->value)
            ->orderBy('id')
            ->get();

        $lines[] = '## Products';
        if ($products->isEmpty()) {
            $lines[] = '- No active products listed yet.';
        } else {
            foreach ($products as $product) {
                $slug = $product->slug ?: $this->meta->slugify($product->name) ?: (string) $product->id;
                $url = $this->meta->urlFor($base, $store->default_locale ?: 'ar', 'products', $slug);
                $price = number_format($product->price / 100, 2, '.', '');
                $summary = $product->seo_description ?? $product->description;
                $descPart = $summary !== null && trim($summary) !== ''
                    ? ': '.mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($summary)) ?? ''), 0, 120, 'UTF-8')
                    : '';
                $lines[] = "- [{$product->name}]({$url}) ({$price} EGP){$descPart}";
            }
        }
        $lines[] = '';

        $pages = StaticPage::withoutStoreScope()
            ->where('store_id', $store->id)
            ->orderBy('slug')
            ->get();

        $lines[] = '## Pages';
        if ($pages->isEmpty()) {
            $lines[] = '- No static pages published yet.';
        } else {
            foreach ($pages as $page) {
                $url = $this->meta->urlFor($base, $store->default_locale ?: 'ar', 'pages', $page->slug);
                $lines[] = "- [{$page->title}]({$url})";
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<string>  $images
     */
    private function urlNode(string $loc, ?string $lastMod = null, array $images = [], ?string $imageTitle = null): string
    {
        $parts = ['  <url>', '    <loc>'.$this->xml($loc).'</loc>'];

        if ($lastMod !== null) {
            $parts[] = '    <lastmod>'.$this->xml($lastMod).'</lastmod>';
        }

        foreach ($images as $imageUrl) {
            $parts[] = '    <image:image>';
            $parts[] = '      <image:loc>'.$this->xml($imageUrl).'</image:loc>';
            if ($imageTitle !== null && $imageTitle !== '') {
                $parts[] = '      <image:title>'.$this->xml($imageTitle).'</image:title>';
            }
            $parts[] = '    </image:image>';
        }

        $parts[] = '  </url>';

        return implode("\n", $parts);
    }

    /**
     * @return list<string>
     */
    private function productImages(Product $product, string $baseUrl): array
    {
        $urls = [];

        foreach ($product->images ?? [] as $image) {
            if (! is_string($image) || trim($image) === '') {
                continue;
            }

            $trimmed = trim($image);
            if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
                $urls[] = $trimmed;
            } else {
                $urls[] = $baseUrl.'/storage/'.ltrim($trimmed, '/');
            }
        }

        return $urls;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
