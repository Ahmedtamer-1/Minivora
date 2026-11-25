<?php

namespace App\Modules\Analytics\AiFunctions;

use App\Ai\Registry\AiFunction;
use App\Core\Permissions\Permission;
use App\Modules\Analytics\Services\DemandService;
use App\Modules\Analytics\Services\KpiService;
use Illuminate\Support\Carbon;

/**
 * Read-only analytics functions callable by the AI executive team. FR-ANA-02, FR-ANA-04, FR-MI-01
 */
class AnalyticsFunctions
{
    public function __construct(
        private readonly KpiService $kpis,
        private readonly DemandService $demand,
    ) {}

    /** @return array<string, mixed> */
    #[AiFunction('analytics.kpis', 'Storefront visitors, orders, revenue (piasters), conversion rate, AOV, and returning buyer rate for a date range (YYYY-MM-DD).', Permission::OrdersView, agents: ['cmo', 'ceo'])]
    public function kpis(string $period_start, string $period_end): array
    {
        return $this->kpis->summary(
            Carbon::parse($period_start)->startOfDay(),
            Carbon::parse($period_end)->endOfDay(),
        ) + ['source' => 'analytics'];
    }

    /** @return array<string, mixed> */
    #[AiFunction('analytics.demand', 'Top products ranked by page views, cart adds, and units sold, flagging sold-out items with active demand.', Permission::ProductsView, agents: ['cmo', 'coo', 'ceo'])]
    public function demand(int $limit = 10): array
    {
        $items = $this->demand->rankProducts(max(1, min(50, $limit)));

        return [
            'products' => $items,
            'sold_out_wanted_count' => count(array_filter($items, fn (array $row): bool => $row['sold_out_wanted'])),
            'source' => 'analytics',
        ];
    }
}
