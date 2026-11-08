<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;

/**
 * Stock read-outs for the dashboard and (later) the AI COO: what is running low, what sold out,
 * what is still in drafts. Other modules call this, never the Product model.
 */
class InventoryService
{
    /**
     * @return array{
     *     total: int, live: int, drafts: int,
     *     running_low: list<array{id: int, name: string, stock: int, status: string}>,
     *     low_count: int, sold_out_count: int,
     *     best_seller: array<string, mixed>|null,
     * }
     */
    public function summary(): array
    {
        // Lowest stock first so the "Running low" card always has something useful to show.
        $lowest = Product::query()->orderBy('stock')->orderBy('name')->limit(4)->get();

        $best = Product::query()->where('sold_count', '>', 0)->orderByDesc('sold_count')->first();

        return [
            'total' => Product::query()->count(),
            'live' => Product::query()->where('status', ProductStatus::Live)->count(),
            'drafts' => Product::query()->where('status', ProductStatus::Draft)->count(),
            'running_low' => $lowest->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name !== '' ? $p->name : ($p->name_ar ?? ''),
                'stock' => $p->stock,
                'status' => $p->status->value,
            ])->all(),
            'low_count' => Product::query()->runningLow()->count(),
            'sold_out_count' => Product::query()->soldOut()->where('status', ProductStatus::Live)->count(),
            'best_seller' => $best === null ? null : [
                ...$best->toCard(),
                'sold' => $best->sold_count,
            ],
        ];
    }

    /**
     * Things the owner should act on, most urgent first (stand-in for the AI "Today" feed, FR-PRO-04).
     *
     * @return list<array{key: string, kind: string, product_id: int|null, name: string|null, count: int}>
     */
    public function attention(int $limit = 3): array
    {
        $items = [];

        foreach (Product::query()->soldOut()->where('status', ProductStatus::Live)->orderBy('name')->limit($limit)->get() as $p) {
            $items[] = ['key' => "sold_out:{$p->id}", 'kind' => 'sold_out', 'product_id' => $p->id, 'name' => $p->name, 'count' => 0];
        }

        foreach (Product::query()->runningLow()->where('status', ProductStatus::Live)->orderBy('stock')->limit($limit)->get() as $p) {
            $items[] = ['key' => "low:{$p->id}:{$p->stock}", 'kind' => 'low', 'product_id' => $p->id, 'name' => $p->name, 'count' => $p->stock];
        }

        $drafts = Product::query()->where('status', ProductStatus::Draft)->count();
        if ($drafts > 0) {
            $items[] = ['key' => "drafts:{$drafts}", 'kind' => 'drafts', 'product_id' => null, 'name' => null, 'count' => $drafts];
        }

        return array_slice($items, 0, $limit);
    }
}
