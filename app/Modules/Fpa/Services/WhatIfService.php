<?php

namespace App\Modules\Fpa\Services;

use App\Modules\Accounts\Models\User;
use App\Modules\Fpa\Models\Scenario;

/**
 * What-if scenario simulator on price, discount, order volume, ad spend, and shipping cost. FR-FPA-03
 * All money values are integer piasters.
 */
class WhatIfService
{
    /**
     * Simulate baseline vs. projected revenue, costs, net profit, and exact piaster profit delta.
     *
     * @param  array{
     *   orders?: int,
     *   avg_order_value?: int,
     *   cogs_per_order?: int,
     *   shipping_per_order?: int,
     *   ad_spend?: int,
     *   fixed_expenses?: int
     * }  $baseline
     * @param  array{
     *   price_change_pct?: float|int,
     *   discount_pct?: float|int,
     *   volume_change_pct?: float|int,
     *   ad_spend_change_pct?: float|int,
     *   shipping_change_pct?: float|int
     * }  $assumptions
     * @return array{
     *   assumptions: array{price_change_pct: float, discount_pct: float, volume_change_pct: float, ad_spend_change_pct: float, shipping_change_pct: float},
     *   baseline: array{orders: int, avg_order_value: int, cogs_per_order: int, shipping_per_order: int, revenue: int, cogs: int, shipping: int, ad_spend: int, fixed_expenses: int, net_profit: int},
     *   projected: array{orders: int, avg_order_value: int, cogs_per_order: int, shipping_per_order: int, revenue: int, cogs: int, shipping: int, ad_spend: int, fixed_expenses: int, net_profit: int},
     *   profit_delta: int
     * }
     */
    public function simulate(array $baseline, array $assumptions): array
    {
        $baseOrders = max(0, (int) ($baseline['orders'] ?? 0));
        $baseAov = max(0, (int) ($baseline['avg_order_value'] ?? 0));
        $baseCogsPerOrder = max(0, (int) ($baseline['cogs_per_order'] ?? 0));
        $baseShippingPerOrder = max(0, (int) ($baseline['shipping_per_order'] ?? 0));
        $baseAdSpend = max(0, (int) ($baseline['ad_spend'] ?? 0));
        $baseFixed = max(0, (int) ($baseline['fixed_expenses'] ?? 0));

        $baseRevenue = $baseOrders * $baseAov;
        $baseCogs = $baseOrders * $baseCogsPerOrder;
        $baseShipping = $baseOrders * $baseShippingPerOrder;
        $baseNetProfit = $baseRevenue - $baseCogs - $baseShipping - $baseAdSpend - $baseFixed;

        $priceChangePct = (float) ($assumptions['price_change_pct'] ?? 0.0);
        $discountPct = (float) ($assumptions['discount_pct'] ?? 0.0);
        $volumeChangePct = (float) ($assumptions['volume_change_pct'] ?? 0.0);
        $adSpendChangePct = (float) ($assumptions['ad_spend_change_pct'] ?? 0.0);
        $shippingChangePct = (float) ($assumptions['shipping_change_pct'] ?? 0.0);

        $projOrders = max(0, (int) round($baseOrders * (1 + $volumeChangePct / 100)));
        $projAov = max(0, (int) round($baseAov * (1 + $priceChangePct / 100) * (1 - $discountPct / 100)));
        $projShippingPerOrder = max(0, (int) round($baseShippingPerOrder * (1 + $shippingChangePct / 100)));

        $projRevenue = $projOrders * $projAov;
        $projCogs = $projOrders * $baseCogsPerOrder;
        $projShipping = $projOrders * $projShippingPerOrder;
        $projAdSpend = max(0, (int) round($baseAdSpend * (1 + $adSpendChangePct / 100)));
        $projFixed = $baseFixed;
        $projNetProfit = $projRevenue - $projCogs - $projShipping - $projAdSpend - $projFixed;

        return [
            'assumptions' => [
                'price_change_pct' => $priceChangePct,
                'discount_pct' => $discountPct,
                'volume_change_pct' => $volumeChangePct,
                'ad_spend_change_pct' => $adSpendChangePct,
                'shipping_change_pct' => $shippingChangePct,
            ],
            'baseline' => [
                'orders' => $baseOrders,
                'avg_order_value' => $baseAov,
                'cogs_per_order' => $baseCogsPerOrder,
                'shipping_per_order' => $baseShippingPerOrder,
                'revenue' => $baseRevenue,
                'cogs' => $baseCogs,
                'shipping' => $baseShipping,
                'ad_spend' => $baseAdSpend,
                'fixed_expenses' => $baseFixed,
                'net_profit' => $baseNetProfit,
            ],
            'projected' => [
                'orders' => $projOrders,
                'avg_order_value' => $projAov,
                'cogs_per_order' => $baseCogsPerOrder,
                'shipping_per_order' => $projShippingPerOrder,
                'revenue' => $projRevenue,
                'cogs' => $projCogs,
                'shipping' => $projShipping,
                'ad_spend' => $projAdSpend,
                'fixed_expenses' => $projFixed,
                'net_profit' => $projNetProfit,
            ],
            'profit_delta' => $projNetProfit - $baseNetProfit,
        ];
    }

    /**
     * Run a what-if simulation and persist the scenario for the current store.
     *
     * @param  array<string, int>  $baseline
     * @param  array<string, float|int>  $assumptions
     */
    public function saveScenario(string $name, array $baseline, array $assumptions, ?User $user = null): Scenario
    {
        $simulation = $this->simulate($baseline, $assumptions);

        return Scenario::query()->create([
            'name' => $name,
            'assumptions' => $simulation['assumptions'],
            'baseline' => $simulation['baseline'],
            'projected' => $simulation['projected'] + ['profit_delta' => $simulation['profit_delta']],
            'created_by' => $user?->id,
        ]);
    }
}
