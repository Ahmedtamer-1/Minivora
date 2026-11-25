<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Order risk evaluation: prior refused/returned COD, 24h velocity by phone or IP,
 * and outlier order total against 30-day store AOV. FR-PAY-12
 */
class RiskService
{
    public const DEFAULT_MIN_REFUSED_COD = 1;

    public const DEFAULT_MAX_ORDERS_24H = 3;

    public const DEFAULT_AOV_MULTIPLIER = 5;

    /**
     * Evaluate risk rules for an order in the current store.
     *
     * @param  array{
     *     min_refused_cod?: int,
     *     max_orders_24h?: int,
     *     aov_multiplier?: int
     * }  $thresholds
     * @return array{flagged: bool, reasons: list<string>}
     */
    public function evaluate(
        string $normalizedPhone,
        int $orderTotal,
        ?string $ip = null,
        array $thresholds = [],
        ?int $excludeOrderId = null,
    ): array {
        $minRefusedCod = max(1, (int) ($thresholds['min_refused_cod'] ?? self::DEFAULT_MIN_REFUSED_COD));
        $maxOrders24h = max(1, (int) ($thresholds['max_orders_24h'] ?? self::DEFAULT_MAX_ORDERS_24H));
        $aovMultiplier = max(1, (int) ($thresholds['aov_multiplier'] ?? self::DEFAULT_AOV_MULTIPLIER));

        $reasons = [];

        // (a) >= 1 cancelled or returned COD order for this phone in the same store
        $refusedCodCount = Order::query()
            ->where('phone', $normalizedPhone)
            ->where('payment_method', 'cod')
            ->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Returned])
            ->when($excludeOrderId !== null, fn ($q) => $q->where('id', '!=', $excludeOrderId))
            ->count();

        if ($refusedCodCount >= $minRefusedCod) {
            $reasons[] = 'prior_refused_cod';
        }

        // (b) > 3 orders from same phone or same IP in last 24 hours
        $since24h = Carbon::now()->subHours(24);

        $phoneOrders24h = Order::query()
            ->where('phone', $normalizedPhone)
            ->where('placed_at', '>=', $since24h)
            ->count();

        if ($phoneOrders24h > $maxOrders24h) {
            $reasons[] = 'high_velocity_phone';
        }

        if ($ip !== null && trim($ip) !== '') {
            $ipMarker = '[ip:'.trim($ip).']';
            $ipOrders24h = Order::query()
                ->where('placed_at', '>=', $since24h)
                ->where('notes', 'like', '%'.$ipMarker.'%')
                ->count();

            if ($ipOrders24h > $maxOrders24h) {
                $reasons[] = 'high_velocity_ip';
            }
        }

        // (c) orderTotal > 5x 30-day store AOV (when AOV > 0)
        $since30d = Carbon::now()->subDays(30);
        $aovQuery = Order::query()
            ->countingAsSale()
            ->where('placed_at', '>=', $since30d)
            ->when($excludeOrderId !== null, fn ($q) => $q->where('id', '!=', $excludeOrderId));

        $stats = $aovQuery->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as sum_total')->first();
        $count = (int) ($stats->cnt ?? 0);
        $sumTotal = (int) ($stats->sum_total ?? 0);
        $aov = $count > 0 ? intdiv($sumTotal, $count) : 0;

        if ($aov > 0 && $orderTotal > ($aov * $aovMultiplier)) {
            $reasons[] = 'high_order_value';
        }

        return [
            'flagged' => $reasons !== [],
            'reasons' => $reasons,
        ];
    }
}
