<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Analytics\Models\VisitSession;
use App\Modules\Checkout\Models\Payment;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\CampaignMetric;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Store-verified campaign evaluation and deterministic SRS verdict engine (Pause / Fix / Scale / Keep).
 * FR-MI-04, FR-MI-05, FR-MI-06
 * All monetary figures are integer piasters.
 */
class CampaignVerdictService
{
    public const DEFAULT_PAUSE_SPEND_LIMIT = 50_000; // 500 EGP in piasters

    public const DEFAULT_TARGET_ROAS = 2.5;

    /**
     * Evaluate a single campaign against store-verified orders and sessions matched by UTM.
     *
     * @param  array{pause_spend_limit?: int, target_roas?: float}  $options
     * @return array<string, mixed>
     */
    public function evaluate(Campaign $campaign, array $options = []): array
    {
        $campaign->loadMissing('metrics');

        /** @var Collection<int, CampaignMetric> $metrics */
        $metrics = $campaign->metrics;

        $spend = (int) $metrics->sum('spend');
        $impressions = (int) $metrics->sum('impressions');
        $reach = (int) $metrics->sum('reach');
        $clicks = (int) $metrics->sum('clicks');
        $metaPurchases = (int) $metrics->sum('meta_purchases');
        $metaPurchaseValue = (int) $metrics->sum('meta_purchase_value');

        $explicitFrequencies = $metrics
            ->pluck('frequency')
            ->filter(fn ($value): bool => (float) $value > 0.0);

        if ($explicitFrequencies->isNotEmpty()) {
            $frequency = round((float) $explicitFrequencies->avg(), 2);
        } elseif ($reach > 0) {
            $frequency = round($impressions / $reach, 2);
        } else {
            $frequency = 0.0;
        }

        $utm = $campaign->utm_campaign !== null ? strtolower(trim($campaign->utm_campaign)) : '';
        $sessions = $utm !== ''
            ? VisitSession::query()->whereRaw('LOWER(utm_campaign) = ?', [$utm])->count()
            : 0;

        $matchedOrders = $this->matchedOrders($utm);
        $storeVerifiedOrders = $matchedOrders->count();
        $revenue = (int) $matchedOrders->sum('total');
        $shipping = (int) $matchedOrders->sum('shipping_fee');
        $productCost = (int) $matchedOrders->sum(
            fn (Order $order): int => (int) $order->items->sum(
                fn (OrderItem $item): int => (int) ($item->unit_cost ?? 0) * $item->quantity
            )
        );

        $orderIds = $matchedOrders->pluck('id')->all();
        $fees = $orderIds !== []
            ? (int) Payment::query()
                ->whereIn('order_id', $orderIds)
                ->get(['gateway_fee', 'cod_fee'])
                ->sum(fn (Payment $payment): int => $payment->gateway_fee + $payment->cod_fee)
            : 0;

        $profit = $revenue - $productCost - $shipping - $fees - $spend;
        $roas = $spend > 0 ? round($revenue / $spend, 2) : 0.0;
        $cpo = $storeVerifiedOrders > 0 ? (int) round($spend / $storeVerifiedOrders) : null;
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;
        $cpc = $clicks > 0 ? (int) round($spend / $clicks) : null;
        $cpm = $impressions > 0 ? (int) round(($spend / $impressions) * 1000) : null;

        $pauseSpendLimit = (int) ($options['pause_spend_limit'] ?? self::DEFAULT_PAUSE_SPEND_LIMIT);
        $targetRoas = (float) ($options['target_roas'] ?? self::DEFAULT_TARGET_ROAS);

        [$verdict, $rule] = $this->verdictFor(
            spend: $spend,
            storeVerifiedOrders: $storeVerifiedOrders,
            impressions: $impressions,
            ctr: $ctr,
            profit: $profit,
            roas: $roas,
            frequency: $frequency,
            pauseSpendLimit: $pauseSpendLimit,
            targetRoas: $targetRoas,
        );

        return [
            'campaign_id' => $campaign->id,
            'name' => $campaign->name,
            'channel' => $campaign->channel,
            'utm_campaign' => $campaign->utm_campaign,
            'status' => $campaign->status,
            'sessions' => $sessions,
            'spend' => $spend,
            'impressions' => $impressions,
            'reach' => $reach,
            'clicks' => $clicks,
            'frequency' => $frequency,
            'meta_purchases' => $metaPurchases,
            'meta_purchase_value' => $metaPurchaseValue,
            'store_verified_orders' => $storeVerifiedOrders,
            'revenue' => $revenue,
            'product_cost' => $productCost,
            'shipping' => $shipping,
            'fees' => $fees,
            'profit' => $profit,
            'roas' => $roas,
            'cpo' => $cpo,
            'ctr' => $ctr,
            'cpc' => $cpc,
            'cpm' => $cpm,
            'verdict' => $verdict,
            'rule' => $rule,
        ];
    }

    /**
     * Rank all non-proposal campaigns by net profit descending, then ROAS descending.
     *
     * @param  array{pause_spend_limit?: int, target_roas?: float}  $options
     * @return list<array<string, mixed>>
     */
    public function rankCampaigns(array $options = []): array
    {
        $campaigns = Campaign::query()
            ->where('is_proposal', false)
            ->with('metrics')
            ->get();

        return $campaigns
            ->map(fn (Campaign $campaign): array => $this->evaluate($campaign, $options))
            ->sort(function (array $a, array $b): int {
                return [$b['profit'], $b['roas'], $b['revenue'], $a['campaign_id']]
                    <=> [$a['profit'], $a['roas'], $a['revenue'], $b['campaign_id']];
            })
            ->values()
            ->all();
    }

    /**
     * Exact ordered SRS verdict rules (FR-MI-05, FR-MI-06):
     * 1. Pause: spend > pause_spend_limit && store_verified_orders === 0
     * 2. Fix:   CTR < 1.0% over >= 1000 impressions
     * 3. Scale: profit > 0 && roas >= target_roas && frequency < 3.0
     * 4. Keep:  otherwise
     *
     * @return array{0: string, 1: string}
     */
    private function verdictFor(
        int $spend,
        int $storeVerifiedOrders,
        int $impressions,
        float $ctr,
        int $profit,
        float $roas,
        float $frequency,
        int $pauseSpendLimit,
        float $targetRoas,
    ): array {
        if ($spend > $pauseSpendLimit && $storeVerifiedOrders === 0) {
            return ['Pause', 'spend_without_orders'];
        }

        if ($impressions >= 1000 && $ctr < 1.0) {
            return ['Fix', 'low_ctr'];
        }

        if ($profit > 0 && $roas >= $targetRoas && $frequency < 3.0) {
            return ['Scale', 'profitable_high_roas'];
        }

        return ['Keep', 'maintain'];
    }

    /** @return Collection<int, Order> */
    private function matchedOrders(string $normalizedUtm): Collection
    {
        if ($normalizedUtm === '') {
            return collect();
        }

        $candidates = [
            $normalizedUtm,
            'utm:'.$normalizedUtm,
            'utm_campaign:'.$normalizedUtm,
        ];

        return Order::query()
            ->countingAsSale()
            ->with('items')
            ->get()
            ->filter(function (Order $order) use ($candidates): bool {
                $tags = $order->tags ?? [];
                if (! is_array($tags) || $tags === []) {
                    return false;
                }

                foreach ($tags as $tag) {
                    if (is_string($tag) && in_array(strtolower(trim($tag)), $candidates, true)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }
}
