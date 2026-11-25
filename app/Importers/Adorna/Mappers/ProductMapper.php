<?php

namespace App\Importers\Adorna\Mappers;

use App\Importers\Adorna\AdornaMoney;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Maps Adorna categories, products, and product variants into the current store.
 * Idempotent by (store_id, slug) for categories and (store_id, sku|slug) for products/variants.
 * Syncs each parent product's stock to the exact sum of its variants' stock.
 */
class ProductMapper
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     imported: int,
     *     skipped: int,
     *     by_slug: array<string, Category>,
     *     by_external_id: array<string, Category>,
     * }
     */
    public function importCategories(array $rows): array
    {
        $imported = 0;
        $skipped = 0;
        $bySlug = [];
        $byExternalId = [];

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? $row['name_en'] ?? ''));
            $slug = trim((string) ($row['slug'] ?? ''));

            if ($slug === '') {
                $slug = Str::slug($name !== '' ? $name : 'category-'.($index + 1));
            }

            $existing = Category::query()->where('slug', $slug)->first();

            $attributes = [
                'name' => $name !== '' ? $name : $slug,
                'name_ar' => isset($row['name_ar']) ? (string) $row['name_ar'] : null,
                'slug' => $slug,
                'image' => isset($row['image']) ? (string) $row['image'] : null,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ];

            if ($existing !== null) {
                $existing->fill($attributes)->save();
                $category = $existing;
                $skipped++;
            } else {
                $category = Category::query()->create($attributes);
                $imported++;
            }

            $bySlug[$slug] = $category;
            if (isset($row['id']) || isset($row['external_id'])) {
                $byExternalId[(string) ($row['external_id'] ?? $row['id'])] = $category;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'by_slug' => $bySlug,
            'by_external_id' => $byExternalId,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $standaloneVariants
     * @param  array<string, Category>  $categoriesBySlug
     * @param  array<string, Category>  $categoriesByExternalId
     * @return array{
     *     products: array{imported: int, skipped: int},
     *     variants: array{imported: int, skipped: int},
     *     by_sku: array<string, Product>,
     *     by_slug: array<string, Product>,
     *     by_external_id: array<string, Product>,
     * }
     */
    public function importProducts(
        array $rows,
        array $standaloneVariants = [],
        array $categoriesBySlug = [],
        array $categoriesByExternalId = [],
    ): array {
        $productsImported = 0;
        $productsSkipped = 0;
        $variantsImported = 0;
        $variantsSkipped = 0;

        $bySku = [];
        $bySlug = [];
        $byExternalId = [];
        $productsWithVariants = [];

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? $row['name_en'] ?? ''));
            $sku = isset($row['sku']) && trim((string) $row['sku']) !== ''
                ? trim((string) $row['sku'])
                : null;
            $slug = isset($row['slug']) && trim((string) $row['slug']) !== ''
                ? trim((string) $row['slug'])
                : Str::slug($name !== '' ? $name : ($sku ?? 'product-'.($index + 1)));

            $existing = null;
            if ($sku !== null) {
                $existing = Product::query()->where('sku', $sku)->first();
            }
            if ($existing === null && $slug !== '') {
                $existing = Product::query()->where('slug', $slug)->first();
            }

            $category = $this->resolveCategory($row, $categoriesBySlug, $categoriesByExternalId);
            $status = $this->mapStatus((string) ($row['status'] ?? 'live'));

            $attributes = [
                'name' => $name !== '' ? $name : $slug,
                'name_ar' => isset($row['name_ar']) ? (string) $row['name_ar'] : null,
                'slug' => $slug,
                'sku' => $sku,
                'description' => isset($row['description']) ? (string) $row['description'] : null,
                'category' => $category?->name ?? (isset($row['category']) ? (string) $row['category'] : null),
                'category_id' => $category?->id,
                'price' => (int) AdornaMoney::extract($row, 'price', 0),
                'compare_at_price' => AdornaMoney::extract($row, 'compare_at_price', null),
                'cost' => AdornaMoney::extract($row, 'cost', null),
                'stock' => max(0, (int) ($row['stock'] ?? 0)),
                'reorder_threshold' => max(0, (int) ($row['reorder_threshold'] ?? Product::LOW_STOCK)),
                'status' => $status,
                'has_options' => (bool) ($row['has_options'] ?? ! empty($row['variants'])),
                'colors' => is_array($row['colors'] ?? null) ? $row['colors'] : null,
                'images' => is_array($row['images'] ?? null) ? $row['images'] : null,
                'video_url' => isset($row['video_url']) ? (string) $row['video_url'] : null,
                'seo_title' => isset($row['seo_title']) ? (string) $row['seo_title'] : null,
                'seo_description' => isset($row['seo_description']) ? (string) $row['seo_description'] : null,
                'source' => 'adorna',
            ];

            if ($existing !== null) {
                $existing->fill($attributes);
                if ($status === ProductStatus::Live && $existing->published_at === null) {
                    $existing->published_at = isset($row['published_at'])
                        ? Carbon::parse((string) $row['published_at'])
                        : Carbon::now();
                }
                $existing->save();
                $product = $existing;
                $productsSkipped++;
            } else {
                $product = new Product($attributes);
                if ($status === ProductStatus::Live) {
                    $product->published_at = isset($row['published_at'])
                        ? Carbon::parse((string) $row['published_at'])
                        : Carbon::now();
                }
                $product->save();
                $productsImported++;
            }

            if ($sku !== null) {
                $bySku[$sku] = $product;
            }
            if ($slug !== '') {
                $bySlug[$slug] = $product;
            }
            if (isset($row['id']) || isset($row['external_id'])) {
                $byExternalId[(string) ($row['external_id'] ?? $row['id'])] = $product;
            }

            if (is_array($row['variants'] ?? null) && $row['variants'] !== []) {
                foreach ($row['variants'] as $variantRow) {
                    $created = $this->upsertVariant($product, $variantRow);
                    if ($created) {
                        $variantsImported++;
                    } else {
                        $variantsSkipped++;
                    }
                }
                $productsWithVariants[$product->id] = $product;
            }
        }

        foreach ($standaloneVariants as $variantRow) {
            $product = $this->resolveProductForVariant($variantRow, $bySku, $bySlug, $byExternalId);
            if ($product === null) {
                continue;
            }

            $created = $this->upsertVariant($product, $variantRow);
            if ($created) {
                $variantsImported++;
            } else {
                $variantsSkipped++;
            }
            $productsWithVariants[$product->id] = $product;
        }

        foreach ($productsWithVariants as $product) {
            $variantStock = (int) ProductVariant::query()
                ->where('product_id', $product->id)
                ->sum('stock');

            $product->has_options = true;
            $product->stock = $variantStock;
            $product->save();
        }

        return [
            'products' => ['imported' => $productsImported, 'skipped' => $productsSkipped],
            'variants' => ['imported' => $variantsImported, 'skipped' => $variantsSkipped],
            'by_sku' => $bySku,
            'by_slug' => $bySlug,
            'by_external_id' => $byExternalId,
        ];
    }

    /**
     * @param  array<string, mixed>  $variantRow
     * @return bool True if newly created, false if updated (skipped duplicate).
     */
    private function upsertVariant(Product $product, array $variantRow): bool
    {
        $sku = isset($variantRow['sku']) && trim((string) $variantRow['sku']) !== ''
            ? trim((string) $variantRow['sku'])
            : null;
        $options = is_array($variantRow['options'] ?? null) ? $variantRow['options'] : [];

        $existing = null;
        if ($sku !== null) {
            $existing = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('sku', $sku)
                ->first();
        }

        if ($existing === null && $options !== []) {
            $existing = ProductVariant::query()
                ->where('product_id', $product->id)
                ->get()
                ->first(fn (ProductVariant $v): bool => $v->options == $options);
        }

        $attributes = [
            'product_id' => $product->id,
            'sku' => $sku,
            'options' => $options,
            'price' => (int) AdornaMoney::extract($variantRow, 'price', $product->price),
            'cost' => AdornaMoney::extract($variantRow, 'cost', $product->cost),
            'stock' => max(0, (int) ($variantRow['stock'] ?? 0)),
            'images' => is_array($variantRow['images'] ?? null) ? $variantRow['images'] : null,
        ];

        if ($existing !== null) {
            $existing->fill($attributes)->save();

            return false;
        }

        ProductVariant::query()->create($attributes);

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, Category>  $bySlug
     * @param  array<string, Category>  $byExternalId
     */
    private function resolveCategory(array $row, array $bySlug, array $byExternalId): ?Category
    {
        if (isset($row['category_slug']) && isset($bySlug[(string) $row['category_slug']])) {
            return $bySlug[(string) $row['category_slug']];
        }

        if (isset($row['category_id']) && isset($byExternalId[(string) $row['category_id']])) {
            return $byExternalId[(string) $row['category_id']];
        }

        if (isset($row['category']) && is_string($row['category'])) {
            $slug = Str::slug($row['category']);
            if (isset($bySlug[$slug])) {
                return $bySlug[$slug];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $variantRow
     * @param  array<string, Product>  $bySku
     * @param  array<string, Product>  $bySlug
     * @param  array<string, Product>  $byExternalId
     */
    private function resolveProductForVariant(
        array $variantRow,
        array $bySku,
        array $bySlug,
        array $byExternalId,
    ): ?Product {
        if (isset($variantRow['product_sku']) && isset($bySku[(string) $variantRow['product_sku']])) {
            return $bySku[(string) $variantRow['product_sku']];
        }

        if (isset($variantRow['product_slug']) && isset($bySlug[(string) $variantRow['product_slug']])) {
            return $bySlug[(string) $variantRow['product_slug']];
        }

        if (isset($variantRow['product_id']) && isset($byExternalId[(string) $variantRow['product_id']])) {
            return $byExternalId[(string) $variantRow['product_id']];
        }

        if (isset($variantRow['product_external_id']) && isset($byExternalId[(string) $variantRow['product_external_id']])) {
            return $byExternalId[(string) $variantRow['product_external_id']];
        }

        return null;
    }

    private function mapStatus(string $raw): ProductStatus
    {
        return match (strtolower(trim($raw))) {
            'live', 'active', 'published', '1', 'true' => ProductStatus::Live,
            default => ProductStatus::Draft,
        };
    }
}
