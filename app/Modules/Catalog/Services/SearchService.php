<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Storefront search with Arabic normalization, live suggestions, catalog filtering/sorting,
 * and related products. FR-CAT-04, FR-CAT-05, FR-CAT-07
 */
class SearchService
{
    /**
     * Normalize Arabic text for forgiving search matching:
     * - Alef forms (أ إ آ ٱ) -> ا
     * - Alef maqsura (ى) -> ي
     * - Ta marbuta (ة) -> ه
     * - Strips Arabic diacritics (tashkeel) and tatweel
     * - Lowercases for case-insensitive Latin matching
     *
     * FR-CAT-05
     */
    public function normalizeArabic(string $text): string
    {
        // Strip Arabic diacritics (harakat, tanween, shadda, sukun, superscript alef) and tatweel.
        $normalized = (string) preg_replace('/[\x{0610}-\x{061A}\x{0640}\x{064B}-\x{065F}\x{0670}]/u', '', $text);

        $normalized = str_replace(
            ['أ', 'إ', 'آ', 'ٱ', 'ى', 'ة'],
            ['ا', 'ا', 'ا', 'ا', 'ي', 'ه'],
            $normalized,
        );

        return mb_strtolower($normalized, 'UTF-8');
    }

    /**
     * Live search suggestions over active (live) products, matching Arabic-normalized forms
     * in name, name_ar, and description. FR-CAT-05
     *
     * @return Collection<int, Product>
     */
    public function suggest(string $query, int $limit = 8): Collection
    {
        $needle = trim($this->normalizeArabic($query));
        if ($needle === '') {
            return new Collection;
        }

        $candidates = Product::query()
            ->where('status', ProductStatus::Live)
            ->orderByDesc('sold_count')
            ->orderByDesc('id')
            ->get();

        $matched = $candidates->filter(function (Product $product) use ($needle): bool {
            foreach ([$product->name, $product->name_ar, $product->description] as $field) {
                if ($field !== null && $field !== '' && str_contains($this->normalizeArabic($field), $needle)) {
                    return true;
                }
            }

            return false;
        })->take($limit)->values();

        return new Collection($matched->all());
    }

    /**
     * Filter, sort, and paginate active storefront products. FR-CAT-04
     *
     * Supported filters:
     * - category_id: int
     * - category: string (matches product.category or category slug)
     * - min_price: int (piasters)
     * - max_price: int (piasters)
     * - in_stock: bool
     * - sort: newest | price_asc | price_desc | best_selling
     * - per_page: int (default 12)
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function shopQuery(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()->where('status', ProductStatus::Live);

        if (isset($filters['category_id']) && $filters['category_id'] !== '') {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (isset($filters['category']) && trim((string) $filters['category']) !== '') {
            $category = trim((string) $filters['category']);
            $query->where(function (Builder $q) use ($category) {
                $q->where('category', $category)
                    ->orWhereHas('categoryRecord', fn (Builder $cq) => $cq->where('slug', $category));
            });
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $query->where('price', '>=', (int) $filters['min_price']);
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $query->where('price', '<=', (int) $filters['max_price']);
        }

        if (! empty($filters['in_stock'])) {
            $query->where('stock', '>', 0);
        }

        $sort = (string) ($filters['sort'] ?? 'newest');
        match ($sort) {
            'price_asc' => $query->orderBy('price')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            'best_selling' => $query->orderByDesc('sold_count')->orderByDesc('id'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };

        $perPage = max(1, min(60, (int) ($filters['per_page'] ?? 12)));

        return $query->paginate($perPage);
    }

    /**
     * Up to $limit other active products in the same category ordered by sold_count desc. FR-CAT-07
     *
     * @return Collection<int, Product>
     */
    public function relatedProducts(Product $product, int $limit = 4): Collection
    {
        if ($product->category_id === null && ($product->category === null || $product->category === '')) {
            return new Collection;
        }

        $query = Product::query()
            ->where('status', ProductStatus::Live)
            ->where('id', '!=', $product->id);

        if ($product->category_id !== null) {
            $query->where('category_id', $product->category_id);
        } else {
            $query->where('category', $product->category);
        }

        return $query->orderByDesc('sold_count')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
