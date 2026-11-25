<?php

namespace App\Modules\Seo\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Review;
use App\Modules\Platform\Models\Store;
use App\Modules\Seo\Models\SeoSetting;

/**
 * Generates schema.org JSON-LD structured data for Organization, WebSite, Product,
 * BreadcrumbList, and FAQPage. FR-SEO-07, FR-SEO-10
 */
class JsonLdService
{
    /**
     * @return array<string, mixed>
     */
    public function organization(Store $store, string $baseUrl): array
    {
        $url = rtrim($baseUrl, '/');
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $store->name,
            'url' => $url,
        ];

        $sells = $store->profileData()->sells;
        if ($sells !== null && trim($sells) !== '') {
            $data['description'] = trim($sells);
        }

        $ogImage = SeoSetting::withoutStoreScope()
            ->where('store_id', $store->id)
            ->value('og_image');

        if (is_string($ogImage) && trim($ogImage) !== '') {
            $data['logo'] = trim($ogImage);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function website(Store $store, string $baseUrl): array
    {
        $url = rtrim($baseUrl, '/');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $store->name,
            'url' => $url,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => "{$url}/search?q={search_term_string}",
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * Product + Offer (EGP decimal price, availability, shippingDetails, hasMerchantReturnPolicy),
     * plus AggregateRating and Review entries when reviews exist. FR-SEO-07, FR-SEO-10
     *
     * @param  list<Review|array<string, mixed>>  $reviews
     * @return array<string, mixed>
     */
    public function product(Product $product, Store $store, string $url, array $reviews = []): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => trim((string) ($product->seo_description ?? $product->description ?? '')),
            'brand' => [
                '@type' => 'Brand',
                'name' => $store->name,
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $url,
                'priceCurrency' => 'EGP',
                'price' => number_format($product->price / 100, 2, '.', ''),
                'availability' => $product->stock > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'shippingDetails' => [
                    '@type' => 'OfferShippingDetails',
                    'shippingDestination' => [
                        '@type' => 'DefinedRegion',
                        'addressCountry' => 'EG',
                    ],
                ],
                'hasMerchantReturnPolicy' => [
                    '@type' => 'MerchantReturnPolicy',
                    'applicableCountry' => 'EG',
                    'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                    'merchantReturnDays' => 14,
                    'returnMethod' => 'https://schema.org/ReturnByMail',
                ],
            ],
        ];

        if ($product->sku !== null && $product->sku !== '') {
            $data['sku'] = $product->sku;
        }

        $images = $this->normalizeImages($product, $url);
        if ($images !== []) {
            $data['image'] = $images;
        }

        if ($reviews !== []) {
            $normalizedReviews = [];
            $ratingSum = 0.0;

            foreach ($reviews as $review) {
                if ($review instanceof Review) {
                    $rating = (int) $review->rating;
                    $author = $review->customer_name;
                    $body = (string) ($review->body ?? '');
                    $datePublished = $review->created_at?->toDateString();
                } else {
                    $rating = (int) ($review['rating'] ?? 0);
                    $author = (string) ($review['customer_name'] ?? $review['author'] ?? 'Customer');
                    $body = (string) ($review['body'] ?? $review['reviewBody'] ?? '');
                    $datePublished = isset($review['created_at']) ? (string) $review['created_at'] : null;
                }

                $ratingSum += $rating;

                $entry = [
                    '@type' => 'Review',
                    'author' => [
                        '@type' => 'Person',
                        'name' => $author,
                    ],
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => $rating,
                        'bestRating' => 5,
                        'worstRating' => 1,
                    ],
                ];

                if ($body !== '') {
                    $entry['reviewBody'] = $body;
                }

                if ($datePublished !== null) {
                    $entry['datePublished'] = $datePublished;
                }

                $normalizedReviews[] = $entry;
            }

            $count = count($normalizedReviews);
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($ratingSum / $count, 2),
                'reviewCount' => $count,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
            $data['review'] = $normalizedReviews;
        }

        return $data;
    }

    /**
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $items): array
    {
        $elements = [];

        foreach (array_values($items) as $index => $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @param  list<array{question: string, answer: string}>  $faqs
     * @return array<string, mixed>
     */
    public function faqPage(array $faqs): array
    {
        $entities = [];

        foreach ($faqs as $faq) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeImages(Product $product, string $productUrl): array
    {
        $parsed = parse_url($productUrl);
        $origin = isset($parsed['scheme'], $parsed['host'])
            ? $parsed['scheme'].'://'.$parsed['host'].(isset($parsed['port']) ? ':'.$parsed['port'] : '')
            : '';

        $urls = [];
        foreach ($product->images ?? [] as $image) {
            if (! is_string($image) || trim($image) === '') {
                continue;
            }

            $trimmed = trim($image);
            if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
                $urls[] = $trimmed;
            } elseif ($origin !== '') {
                $urls[] = $origin.'/storage/'.ltrim($trimmed, '/');
            } else {
                $urls[] = '/storage/'.ltrim($trimmed, '/');
            }
        }

        return $urls;
    }
}
