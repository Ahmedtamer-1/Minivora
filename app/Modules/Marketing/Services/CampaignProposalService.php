<?php

namespace App\Modules\Marketing\Services;

use App\Core\Tenancy\CurrentStore;
use App\Modules\Catalog\Models\Product;
use App\Modules\Marketing\Models\Campaign;
use Illuminate\Support\Str;

/**
 * Deterministic campaign proposal builder: computes budget & duration in code from marketing
 * budget and product margin, generates UTM link, and saves awaiting explicit owner approval.
 * FR-MI-07, FR-MI-08
 */
class CampaignProposalService
{
    public function __construct(private readonly CurrentStore $currentStore) {}

    /**
     * @param  array<string, mixed>  $copy
     */
    public function propose(
        Product $product,
        int $monthlyMarketingBudgetPiasters,
        array $copy,
        string $audience = 'cart_abandoners',
    ): Campaign {
        $monthlyBudget = max(0, $monthlyMarketingBudgetPiasters);
        $unitCost = (int) ($product->cost ?? 0);
        $unitMargin = max(0, $product->price - $unitCost);
        $marginPct = $product->price > 0 ? round(($unitMargin / $product->price) * 100, 2) : 0.0;

        $allocationRate = match (true) {
            $marginPct >= 30.0 => 0.25,
            $marginPct >= 15.0 => 0.15,
            default => 0.10,
        };

        $totalBudget = (int) round($monthlyBudget * $allocationRate);
        if ($monthlyBudget >= 10_000) {
            $totalBudget = max(10_000, min($monthlyBudget, $totalBudget));
        }

        $durationDays = match (true) {
            $totalBudget >= 500_000 => 21,
            $totalBudget >= 100_000 => 14,
            default => 7,
        };

        $dailyBudget = $durationDays > 0 ? (int) floor($totalBudget / $durationDays) : 0;

        $slug = Str::slug($product->slug ?: $product->name ?: 'product-'.$product->id);
        $utmCampaign = substr('minivora_'.$slug.'_'.Str::slug($audience, '_'), 0, 120);

        $store = $this->currentStore->get();
        $basePath = '/ar/products/'.($product->slug ?: $product->id);
        $baseUrl = $store !== null ? $store->url($basePath) : $basePath;
        $utmUrl = $baseUrl.'?'.http_build_query([
            'utm_source' => 'meta',
            'utm_medium' => 'paid_social',
            'utm_campaign' => $utmCampaign,
        ]);

        return Campaign::query()->create([
            'external_id' => null,
            'channel' => 'meta',
            'name' => sprintf('%s — %s', $product->name, str_replace('_', ' ', $audience)),
            'utm_campaign' => $utmCampaign,
            'status' => 'pending_approval',
            'is_proposal' => true,
            'approved_at' => null,
            'proposal_payload' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'audience' => $audience,
                'copy' => $copy,
                'monthly_marketing_budget' => $monthlyBudget,
                'unit_margin' => $unitMargin,
                'margin_pct' => $marginPct,
                'total_budget' => $totalBudget,
                'daily_budget' => $dailyBudget,
                'duration_days' => $durationDays,
                'utm_url' => $utmUrl,
            ],
        ]);
    }
}
