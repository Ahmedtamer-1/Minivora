<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
     * Stock on hand at cost, for the Ledger's inventory check and opening balance. Products with no
     * cost recorded are counted separately so the owner knows the figure is incomplete.
     *
     * @return array{value: int, units: int, missing_cost: int}
     */
    public function stockValue(): array
    {
        $row = Product::query()->where('stock', '>', 0)
            ->selectRaw('COALESCE(SUM(CASE WHEN cost IS NOT NULL THEN stock * cost ELSE 0 END), 0) as value')
            ->selectRaw('COALESCE(SUM(stock), 0) as units')
            ->selectRaw('SUM(CASE WHEN cost IS NULL THEN 1 ELSE 0 END) as missing_cost')
            ->first();

        return ['value' => (int) ($row->value ?? 0), 'units' => (int) ($row->units ?? 0), 'missing_cost' => (int) ($row->missing_cost ?? 0)];
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

    /**
     * Products a staff member can add to a manual order: live or draft, best sellers first.
     *
     * @return list<array{id: int, name: string, name_ar: string|null, price: int, cost: int|null, stock: int, cover: string|null}>
     */
    public function pickable(string $search = '', int $limit = 40): array
    {
        $query = Product::query()->where('name', '!=', '')->orderByDesc('sold_count')->orderBy('name');
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('name_ar', 'like', $like));
        }

        return $query->limit($limit)->get()->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'name_ar' => $p->name_ar,
            'price' => $p->price,
            'cost' => $p->cost,
            'stock' => $p->stock,
            'cover' => $p->imageUrls()[0] ?? null,
        ])->values()->all();
    }

    /**
     * Snapshot of the given products for order lines, keyed by id.
     *
     * @param  list<int>  $ids
     * @return array<int, array{id: int, name: string, price: int, cost: int|null, stock: int}>
     */
    public function snapshot(array $ids): array
    {
        return Product::query()->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (Product $p) => [$p->id => [
                'id' => $p->id,
                'name' => $p->name !== '' ? $p->name : (string) $p->name_ar,
                'price' => $p->price,
                'cost' => $p->cost,
                'stock' => $p->stock,
            ]])->all();
    }

    /**
     * Take sold items off the shelf and count them as sold. Refuses if any line has too little stock.
     *
     * @param  array<int, int>  $quantities  product id => quantity
     */
    public function deduct(array $quantities): void
    {
        DB::transaction(function () use ($quantities) {
            $products = Product::query()->whereIn('id', array_keys($quantities))->lockForUpdate()->get()->keyBy('id');
            foreach ($quantities as $id => $qty) {
                $product = $products->get($id);
                if ($product === null) {
                    continue;
                }
                if ($product->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => __('products.not_enough_stock', ['name' => $product->name, 'count' => $product->stock]),
                    ]);
                }
                $product->stock -= $qty;
                $product->sold_count += $qty;
                $product->save();
            }
        });
    }

    /**
     * Put items back after a cancellation or return. FR-ORD-03
     *
     * @param  array<int, int>  $quantities  product id => quantity
     */
    public function restock(array $quantities): void
    {
        DB::transaction(function () use ($quantities) {
            foreach (Product::query()->whereIn('id', array_keys($quantities))->lockForUpdate()->get() as $product) {
                $qty = $quantities[$product->id] ?? 0;
                $product->stock += $qty;
                $product->sold_count = max(0, $product->sold_count - $qty);
                $product->save();
            }
        });
    }
}
