<?php

namespace App\Modules\Marketing\AiFunctions;

use App\Ai\Registry\AiFunction;
use App\Core\Permissions\Permission;
use App\Modules\Marketing\Models\AdSpend;
use App\Modules\Marketing\Services\AbandonedCartService;
use App\Modules\Marketing\Services\CampaignVerdictService;
use Illuminate\Support\Carbon;

/**
 * Read-only marketing intelligence functions callable by the AI executive team. FR-MI-01
 */
class MarketingFunctions
{
    public function __construct(
        private readonly CampaignVerdictService $verdicts,
        private readonly AbandonedCartService $abandonedCarts,
    ) {}

    /** @return array<string, mixed> */
    #[AiFunction('marketing.campaign_performance', 'Store-verified campaign attribution, profit, ROAS, CTR, frequency, and deterministic Pause/Fix/Scale/Keep verdicts.', Permission::MarketingManage, agents: ['cmo', 'cfo', 'ceo'])]
    public function campaignPerformance(): array
    {
        return [
            'campaigns' => $this->verdicts->rankCampaigns(),
            'source' => 'marketing',
        ];
    }

    /** @return array<string, mixed> */
    #[AiFunction('marketing.abandoned_carts', 'Open abandoned checkouts awaiting recovery and their total value in piasters.', Permission::MarketingManage, agents: ['cmo', 'ceo'])]
    public function abandonedCarts(): array
    {
        $open = $this->abandonedCarts->listOpen();

        return [
            'open_count' => $open->count(),
            'total_subtotal' => (int) $open->sum('subtotal'),
            'source' => 'marketing',
        ];
    }

    /** @return array<string, mixed> */
    #[AiFunction('marketing.ad_spend_summary', 'Total ad spend in piasters grouped by channel for a date range (YYYY-MM-DD).', Permission::MarketingManage, agents: ['cmo', 'cfo', 'ceo'])]
    public function adSpendSummary(string $period_start, string $period_end): array
    {
        $from = Carbon::parse($period_start)->toDateString();
        $to = Carbon::parse($period_end)->toDateString();

        $entries = AdSpend::query()
            ->whereBetween('spend_date', [$from, $to])
            ->get(['channel', 'amount']);

        $byChannel = [];
        foreach ($entries as $entry) {
            $byChannel[$entry->channel] = ($byChannel[$entry->channel] ?? 0) + $entry->amount;
        }

        return [
            'period' => ['from' => $from, 'to' => $to],
            'total_spend' => (int) $entries->sum('amount'),
            'by_channel' => $byChannel,
            'source' => 'marketing',
        ];
    }
}
