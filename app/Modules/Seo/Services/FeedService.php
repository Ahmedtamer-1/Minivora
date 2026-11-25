<?php

namespace App\Modules\Seo\Services;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Collection;

/**
 * Generates Google Merchant Center RSS 2.0 XML and Meta Commerce Catalog TSV feeds. FR-SEO-16
 */
class FeedService
{
    public function __construct(private readonly SeoMetaService $meta = new SeoMetaService) {}

    /**
     * RSS 2.0 XML feed compatible with Google Merchant Center. FR-SEO-16
     */
    public function googleMerchantRss(Store $store, string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');
        $locale = $store->default_locale ?: 'ar';
        $description = $store->profileData()->sells ?? "{$store->name} product catalog";

        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">',
            '  <channel>',
            '    <title>'.$this->xml($store->name).'</title>',
            '    <link>'.$this->xml($base).'</link>',
            '    <description>'.$this->xml($description).'</description>',
        ];

        $products = $this->liveProducts($store);

        foreach ($products as $product) {
            $slug = $product->slug ?: $this->meta->slugify($product->name) ?: (string) $product->id;
            $link = $this->meta->urlFor($base, $locale, 'products', $slug);
            $price = number_format($product->price / 100, 2, '.', '').' EGP';
            $availability = $product->stock > 0 ? 'in_stock' : 'out_of_stock';
            $desc = trim(strip_tags((string) ($product->seo_description ?? $product->description ?? $product->name)));
            $imageLink = $this->firstImageUrl($product, $base);

            $lines[] = '    <item>';
            $lines[] = '      <g:id>'.$this->xml((string) $product->id).'</g:id>';
            $lines[] = '      <g:title>'.$this->xml($product->name).'</g:title>';
            $lines[] = '      <g:description>'.$this->xml($desc).'</g:description>';
            $lines[] = '      <g:link>'.$this->xml($link).'</g:link>';
            if ($imageLink !== '') {
                $lines[] = '      <g:image_link>'.$this->xml($imageLink).'</g:image_link>';
            }
            $lines[] = '      <g:price>'.$this->xml($price).'</g:price>';
            $lines[] = '      <g:availability>'.$this->xml($availability).'</g:availability>';
            $lines[] = '      <g:condition>new</g:condition>';
            $lines[] = '    </item>';
        }

        $lines[] = '  </channel>';
        $lines[] = '</rss>';

        return implode("\n", $lines)."\n";
    }

    /**
     * Tab-separated catalog feed compatible with Meta Commerce Manager. FR-SEO-16
     */
    public function metaCatalogTsv(Store $store, string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');
        $locale = $store->default_locale ?: 'ar';

        $rows = [
            implode("\t", [
                'id',
                'title',
                'description',
                'availability',
                'condition',
                'price',
                'link',
                'image_link',
            ]),
        ];

        $products = $this->liveProducts($store);

        foreach ($products as $product) {
            $slug = $product->slug ?: $this->meta->slugify($product->name) ?: (string) $product->id;
            $link = $this->meta->urlFor($base, $locale, 'products', $slug);
            $price = number_format($product->price / 100, 2, '.', '').' EGP';
            $availability = $product->stock > 0 ? 'in stock' : 'out of stock';
            $desc = trim(strip_tags((string) ($product->seo_description ?? $product->description ?? $product->name)));
            $imageLink = $this->firstImageUrl($product, $base);

            $rows[] = implode("\t", [
                $this->tsv((string) $product->id),
                $this->tsv($product->name),
                $this->tsv($desc),
                $this->tsv($availability),
                'new',
                $this->tsv($price),
                $this->tsv($link),
                $this->tsv($imageLink),
            ]);
        }

        return implode("\n", $rows)."\n";
    }

    /**
     * @return Collection<int, Product>
     */
    private function liveProducts(Store $store)
    {
        return Product::withoutStoreScope()
            ->where('store_id', $store->id)
            ->where('status', ProductStatus::Live->value)
            ->orderBy('id')
            ->get();
    }

    private function firstImageUrl(Product $product, string $baseUrl): string
    {
        $first = $product->images[0] ?? null;

        if (! is_string($first) || trim($first) === '') {
            return '';
        }

        $trimmed = trim($first);

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        return $baseUrl.'/storage/'.ltrim($trimmed, '/');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function tsv(string $value): string
    {
        return trim(str_replace(["\t", "\r", "\n"], ' ', $value));
    }
}
