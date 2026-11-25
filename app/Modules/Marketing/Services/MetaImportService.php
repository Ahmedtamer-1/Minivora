<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\CampaignMetric;
use App\Modules\Marketing\Models\Pixel;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Read-only Meta Ads connection (`ads_read`) and daily campaign metric importer.
 * FR-MI-02, FR-MI-03
 */
class MetaImportService
{
    public const REQUIRED_SCOPE = 'ads_read';

    public function __construct(private readonly PixelService $pixels) {}

    /**
     * Connect a Meta ad account in read-only mode (`ads_read`).
     */
    public function connectAdAccount(string $adAccountId): Pixel
    {
        $normalized = trim($adAccountId);
        if ($normalized === '') {
            throw new InvalidArgumentException('Meta ad account ID cannot be empty.');
        }

        if (! str_starts_with($normalized, 'act_')) {
            $normalized = 'act_'.$normalized;
        }

        return $this->pixels->update([
            'meta_ad_account_id' => $normalized,
        ]);
    }

    /**
     * Import daily performance rows for a Meta campaign (idempotent per metric_date).
     * Money values (`spend`, `meta_purchase_value`) are integer piasters.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function importDailyMetrics(string $externalId, string $name, ?string $utmCampaign, array $rows): Campaign
    {
        $campaign = Campaign::query()->updateOrCreate(
            [
                'external_id' => trim($externalId),
                'channel' => 'meta',
            ],
            [
                'name' => trim($name),
                'utm_campaign' => $utmCampaign !== null && trim($utmCampaign) !== '' ? trim($utmCampaign) : null,
                'is_proposal' => false,
            ],
        );

        foreach ($rows as $row) {
            $date = Carbon::parse((string) $row['metric_date'])->toDateString();
            $impressions = max(0, (int) ($row['impressions'] ?? 0));
            $reach = max(0, (int) ($row['reach'] ?? 0));
            $clicks = max(0, (int) ($row['clicks'] ?? 0));
            $frequency = isset($row['frequency'])
                ? (float) $row['frequency']
                : ($reach > 0 ? round($impressions / $reach, 2) : 0.0);

            CampaignMetric::query()->updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'metric_date' => $date,
                ],
                [
                    'spend' => max(0, (int) ($row['spend'] ?? 0)),
                    'impressions' => $impressions,
                    'reach' => $reach,
                    'clicks' => $clicks,
                    'frequency' => $frequency,
                    'meta_purchases' => max(0, (int) ($row['meta_purchases'] ?? 0)),
                    'meta_purchase_value' => max(0, (int) ($row['meta_purchase_value'] ?? 0)),
                ],
            );
        }

        return $campaign->load('metrics');
    }
}
