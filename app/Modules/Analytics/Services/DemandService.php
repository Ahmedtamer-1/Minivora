<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Models\PageView;
use App\Modules\Cart\Models\CartEvent;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Product demand ranking combining page views, cart adds, and units sold, plus sold-out demand flag.
 * FR-ANA-04
 */
class DemandService
{
    /**
     * @return list<array{
     *     product_id: int,
     *     name: string,
     *     stock: int,
     *     price: int,
     *     views: int,
     *     cart_adds: int,
     *     units_sold: int,
     *     demand_score: int,
     *     sold_out_wanted: bool,
     * }>
     */
    public function rankProducts(int $limit = 20): array
    {
        $products = Product::query()->get(['id', 'name', 'stock', 'price']);

        if ($products->isEmpty()) {
            return [];
        }

        $viewsByProduct = PageView::query()
            ->whereNotNull('product_id')
            ->select('product_id', DB::raw('COUNT(*) as total'))
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $cartAddsByProduct = CartEvent::query()
            ->where('event_type', CartEvent::TYPE_ADD)
            ->select('product_id', DB::raw('COUNT(*) as total'))
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $excludedStatuses = [
            OrderStatus::Cancelled->value,
            OrderStatus::Refunded->value,
            OrderStatus::Returned->value,
        ];

        $soldByProduct = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('order_items.product_id')
            ->whereNotIn('orders.status', $excludedStatuses)
            ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as total'))
            ->groupBy('order_items.product_id')
            ->pluck('total', 'product_id');

        $ranked = $products->map(function (Product $product) use ($viewsByProduct, $cartAddsByProduct, $soldByProduct): array {
            $views = (int) ($viewsByProduct[$product->id] ?? 0);
            $cartAdds = (int) ($cartAddsByProduct[$product->id] ?? 0);
            $unitsSold = (int) ($soldByProduct[$product->id] ?? 0);
            $demandScore = $views + ($cartAdds * 3) + ($unitsSold * 5);

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'stock' => $product->stock,
                'price' => $product->price,
                'views' => $views,
                'cart_adds' => $cartAdds,
                'units_sold' => $unitsSold,
                'demand_score' => $demandScore,
                'sold_out_wanted' => $product->stock === 0 && ($views > 0 || $cartAdds > 0),
            ];
        })->sort(function (array $a, array $b): int {
            return [$b['demand_score'], $b['units_sold'], $b['cart_adds'], $b['views'], $a['product_id']]
                <=> [$a['demand_score'], $a['units_sold'], $a['cart_adds'], $a['views'], $b['product_id']];
        })->take(max(1, $limit))->values()->all();

        return $ranked;
    }
}
