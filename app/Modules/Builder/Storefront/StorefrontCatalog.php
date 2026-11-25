<?php

namespace App\Modules\Builder\Storefront;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only catalog queries for storefront pages and sections. Only live products are shown.
 * FR-CAT-04, FR-CAT-07
 */
final class StorefrontCatalog
{
    /** @return Builder<Product> */
    public function live(): Builder
    {
        return Product::query()->where('status', ProductStatus::Live->value);
    }

    /**
     * Products for a featured-products section.
     *
     * @param  array<string, mixed>  $settings
     * @return Collection<int, Product>
     */
    public function forSection(array $settings): Collection
    {
        $limit = max(1, min(48, (int) ($settings['limit'] ?? 8)));
        $query = $this->live();

        switch ($settings['source'] ?? 'newest') {
            case 'manual':
                $ids = array_values(array_filter(array_map('intval', (array) ($settings['product_ids'] ?? []))));
                if ($ids === []) {
                    return new Collection;
                }
                $products = $query->whereIn('id', $ids)->get()->keyBy('id');

                return new Collection(array_values(array_filter(array_map(fn ($id) => $products->get($id), $ids))));
            case 'category':
                $query->where('category_id', (int) ($settings['category_id'] ?? 0));
                $query->latest('id');
                break;
            case 'best_selling':
                $query->orderByDesc('sold_count')->latest('id');
                break;
            case 'on_sale':
                $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price')->latest('id');
                break;
            default:
                $query->latest('id');
        }

        return $query->limit($limit)->get();
    }

    /**
     * @param  list<int>|array<int, mixed>  $ids
     * @return Collection<int, Category>
     */
    public function categories(array $ids = []): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $query = Category::query()->withCount(['products' => fn ($q) => $q->where('status', ProductStatus::Live->value)])->orderBy('sort_order')->orderBy('id');
        if ($ids !== []) {
            $found = $query->whereIn('id', $ids)->get()->keyBy('id');

            return new Collection(array_values(array_filter(array_map(fn ($id) => $found->get($id), $ids))));
        }

        return $query->get();
    }

    /**
     * Shop listing with filters: category, price range (EGP), availability, sort, page. FR-CAT-04
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function browse(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $query = $this->live();

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }
        if (isset($filters['min']) && $filters['min'] !== '' && is_numeric($filters['min'])) {
            $query->where('price', '>=', (int) round((float) $filters['min'] * 100));
        }
        if (isset($filters['max']) && $filters['max'] !== '' && is_numeric($filters['max'])) {
            $query->where('price', '<=', (int) round((float) $filters['max'] * 100));
        }
        if (($filters['availability'] ?? '') === 'in_stock') {
            $query->where('stock', '>', 0);
        }
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_substr((string) $filters['q'], 0, 80)).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('name_ar', 'like', $term)->orWhere('description', 'like', $term)->orWhere('sku', 'like', $term));
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'best_selling' => $query->orderByDesc('sold_count'),
            default => $query->latest('id'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    public function findProduct(string $slug): ?Product
    {
        $product = $this->live()->where('slug', $slug)->first();
        if ($product === null && ctype_digit($slug)) {
            $product = $this->live()->whereKey((int) $slug)->first();
        }

        return $product;
    }

    /** @return Collection<int, Product> Up to 4 others from the same category, best sellers first. */
    public function related(Product $product): Collection
    {
        if ($product->category_id === null) {
            return $this->live()->whereKeyNot($product->id)->orderByDesc('sold_count')->limit(4)->get();
        }

        return $this->live()->where('category_id', $product->category_id)->whereKeyNot($product->id)
            ->orderByDesc('sold_count')->limit(4)->get();
    }

    /** @return list<string> */
    public static function images(Product $product): array
    {
        return array_values(array_map(
            fn (string $path) => preg_match('#^(https?:)?//#', $path) || str_starts_with($path, '/') ? $path : Storage::disk('public')->url($path),
            array_filter($product->images ?? [], 'is_string'),
        ));
    }

    public static function categoryImage(Category $category): ?string
    {
        $path = (string) $category->image;
        if ($path === '') {
            return null;
        }

        return preg_match('#^(https?:)?//#', $path) || str_starts_with($path, '/') ? $path : Storage::disk('public')->url($path);
    }

    public static function handle(Product $product): string
    {
        return $product->slug !== null && $product->slug !== '' ? $product->slug : (string) $product->id;
    }
}
