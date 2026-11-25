<?php

namespace App\Importers\Adorna\Mappers;

use App\Modules\Analytics\Models\PageView;
use App\Modules\Analytics\Models\VisitSession;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Maps historical Adorna visits into VisitSession and PageView records.
 * Idempotent by (store_id, session_key).
 */
class VisitMapper
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, Product>  $productsBySku
     * @param  array<string, Product>  $productsBySlug
     * @param  array<string, Product>  $productsByExternalId
     * @return array{
     *     visits: array{imported: int, skipped: int},
     *     page_views: array{imported: int, skipped: int},
     * }
     */
    public function import(
        array $rows,
        array $productsBySku = [],
        array $productsBySlug = [],
        array $productsByExternalId = [],
    ): array {
        $visitsImported = 0;
        $visitsSkipped = 0;
        $viewsImported = 0;
        $viewsSkipped = 0;

        foreach ($rows as $row) {
            $sessionKey = trim((string) ($row['session_key'] ?? ''));
            if ($sessionKey === '') {
                continue;
            }

            $rawViews = is_array($row['page_views'] ?? null) && $row['page_views'] !== []
                ? $row['page_views']
                : [[
                    'path' => (string) ($row['path'] ?? '/'),
                    'product_sku' => $row['product_sku'] ?? null,
                    'product_slug' => $row['product_slug'] ?? null,
                    'product_id' => $row['product_id'] ?? null,
                    'viewed_at' => $row['viewed_at'] ?? ($row['started_at'] ?? null),
                ]];

            $existing = VisitSession::query()->where('session_key', $sessionKey)->first();
            if ($existing !== null) {
                $visitsSkipped++;
                $viewsSkipped += count($rawViews);

                continue;
            }

            $startedAt = isset($row['started_at']) ? Carbon::parse((string) $row['started_at']) : Carbon::now();
            $deviceType = $this->normalizeDeviceType((string) ($row['device_type'] ?? 'mobile'));
            $utmSource = isset($row['utm_source']) ? substr((string) $row['utm_source'], 0, 80) : null;
            $utmMedium = isset($row['utm_medium']) ? substr((string) $row['utm_medium'], 0, 80) : null;
            $utmCampaign = isset($row['utm_campaign']) ? substr((string) $row['utm_campaign'], 0, 120) : null;
            $referrer = isset($row['referrer']) ? substr((string) $row['referrer'], 0, 500) : null;

            $session = VisitSession::query()->create([
                'session_key' => substr($sessionKey, 0, 100),
                'utm_source' => $utmSource,
                'utm_medium' => $utmMedium,
                'utm_campaign' => $utmCampaign,
                'referrer' => $referrer,
                'device_type' => $deviceType,
                'started_at' => $startedAt,
            ]);
            $visitsImported++;

            foreach ($rawViews as $viewRow) {
                $product = $this->resolveProduct($viewRow, $productsBySku, $productsBySlug, $productsByExternalId);
                $viewedAt = isset($viewRow['viewed_at'])
                    ? Carbon::parse((string) $viewRow['viewed_at'])
                    : $startedAt;

                PageView::query()->create([
                    'visit_session_id' => $session->id,
                    'path' => substr((string) ($viewRow['path'] ?? '/'), 0, 500),
                    'product_id' => $product?->id,
                    'referrer' => isset($viewRow['referrer'])
                        ? substr((string) $viewRow['referrer'], 0, 500)
                        : $referrer,
                    'utm_source' => isset($viewRow['utm_source'])
                        ? substr((string) $viewRow['utm_source'], 0, 80)
                        : $utmSource,
                    'utm_medium' => isset($viewRow['utm_medium'])
                        ? substr((string) $viewRow['utm_medium'], 0, 80)
                        : $utmMedium,
                    'utm_campaign' => isset($viewRow['utm_campaign'])
                        ? substr((string) $viewRow['utm_campaign'], 0, 120)
                        : $utmCampaign,
                    'device_type' => isset($viewRow['device_type'])
                        ? $this->normalizeDeviceType((string) $viewRow['device_type'])
                        : $deviceType,
                    'viewed_at' => $viewedAt,
                ]);
                $viewsImported++;
            }
        }

        return [
            'visits' => ['imported' => $visitsImported, 'skipped' => $visitsSkipped],
            'page_views' => ['imported' => $viewsImported, 'skipped' => $viewsSkipped],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, Product>  $bySku
     * @param  array<string, Product>  $bySlug
     * @param  array<string, Product>  $byExternalId
     */
    private function resolveProduct(
        array $row,
        array $bySku,
        array $bySlug,
        array $byExternalId,
    ): ?Product {
        if (isset($row['product_sku']) && isset($bySku[(string) $row['product_sku']])) {
            return $bySku[(string) $row['product_sku']];
        }

        if (isset($row['product_slug']) && isset($bySlug[(string) $row['product_slug']])) {
            return $bySlug[(string) $row['product_slug']];
        }

        if (isset($row['product_id']) && isset($byExternalId[(string) $row['product_id']])) {
            return $byExternalId[(string) $row['product_id']];
        }

        return null;
    }

    private function normalizeDeviceType(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            'desktop', 'pc', 'web' => 'desktop',
            'tablet', 'ipad' => 'tablet',
            default => 'mobile',
        };
    }
}
