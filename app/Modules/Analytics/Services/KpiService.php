<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Models\PageView;
use App\Modules\Analytics\Models\VisitSession;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Storefront KPI aggregation: visitors, orders, revenue (piasters), conversion rate, AOV,
 * and returning buyer rate. FR-ANA-02
 */
class KpiService
{
    /**
     * @return array{
     *     period: array{from: string, to: string},
     *     visitors: int,
     *     page_views: int,
     *     orders: int,
     *     revenue: int,
     *     conversion_rate: float,
     *     aov: int,
     *     returning_buyer_rate: float,
     * }
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $sessionIdsFromSessions = VisitSession::query()
            ->whereBetween('started_at', [$from, $to])
            ->pluck('id');

        $sessionIdsFromViews = PageView::query()
            ->whereBetween('viewed_at', [$from, $to])
            ->whereNotNull('visit_session_id')
            ->distinct()
            ->pluck('visit_session_id');

        $visitors = $sessionIdsFromSessions->merge($sessionIdsFromViews)->unique()->count();

        $pageViews = PageView::query()
            ->whereBetween('viewed_at', [$from, $to])
            ->count();

        $periodOrders = Order::query()
            ->countingAsSale()
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('placed_at', [$from, $to])
                    ->orWhere(function ($fallback) use ($from, $to): void {
                        $fallback->whereNull('placed_at')
                            ->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->get(['id', 'customer_id', 'phone', 'email', 'total', 'placed_at', 'created_at']);

        $orderCount = $periodOrders->count();
        $revenue = (int) $periodOrders->sum('total');
        $conversionRate = $visitors > 0 ? round(($orderCount / $visitors) * 100, 2) : 0.0;
        $aov = $orderCount > 0 ? (int) round($revenue / $orderCount) : 0;

        $returningBuyerRate = $this->returningBuyerRate($periodOrders, $to);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'visitors' => $visitors,
            'page_views' => $pageViews,
            'orders' => $orderCount,
            'revenue' => $revenue,
            'conversion_rate' => $conversionRate,
            'aov' => $aov,
            'returning_buyer_rate' => $returningBuyerRate,
        ];
    }

    /**
     * Percentage of distinct buyers in the period who have placed more than one sale order up to $to.
     *
     * @param  Collection<int, Order>  $periodOrders
     */
    private function returningBuyerRate(Collection $periodOrders, Carbon $to): float
    {
        if ($periodOrders->isEmpty()) {
            return 0.0;
        }

        $periodBuyerKeys = $periodOrders
            ->map(fn (Order $order): string => $this->buyerKey($order))
            ->unique()
            ->values();

        if ($periodBuyerKeys->isEmpty()) {
            return 0.0;
        }

        $allOrdersUpToEnd = Order::query()
            ->countingAsSale()
            ->where(function ($query) use ($to): void {
                $query->where('placed_at', '<=', $to)
                    ->orWhere(function ($fallback) use ($to): void {
                        $fallback->whereNull('placed_at')
                            ->where('created_at', '<=', $to);
                    });
            })
            ->get(['id', 'customer_id', 'phone', 'email']);

        $countsByBuyer = [];
        foreach ($allOrdersUpToEnd as $order) {
            $key = $this->buyerKey($order);
            $countsByBuyer[$key] = ($countsByBuyer[$key] ?? 0) + 1;
        }

        $returningCount = $periodBuyerKeys
            ->filter(fn (string $key): bool => ($countsByBuyer[$key] ?? 0) > 1)
            ->count();

        return round(($returningCount / $periodBuyerKeys->count()) * 100, 2);
    }

    private function buyerKey(Order $order): string
    {
        if ($order->customer_id !== null) {
            return 'customer:'.$order->customer_id;
        }

        if ($order->phone !== null && trim($order->phone) !== '') {
            return 'phone:'.preg_replace('/\D+/', '', $order->phone);
        }

        if ($order->email !== null && trim($order->email) !== '') {
            return 'email:'.strtolower(trim($order->email));
        }

        return 'order:'.$order->id;
    }
}
