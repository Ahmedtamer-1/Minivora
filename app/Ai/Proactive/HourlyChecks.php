<?php

namespace App\Ai\Proactive;

use App\Ai\Models\Insight;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;

/**
 * Hourly & event-triggered proactive checks: low stock below reorder point, payment failures,
 * flagged phones, 404 spikes, and low ROAS. FR-PRO-01, FR-PRO-02, FR-PRO-03, FR-AI-09
 */
class HourlyChecks
{
    public const DEFAULT_REORDER_POINT = 5;

    public function __construct(
        private readonly CurrentStore $currentStore,
    ) {}

    /**
     * Run hourly stock checks and any incoming event-triggered anomalies for a store.
     *
     * @param  list<array<string, mixed>>  $events
     * @return list<Insight>
     */
    public function run(Store $store, array $events = [], int $reorderPoint = self::DEFAULT_REORDER_POINT): array
    {
        return $this->currentStore->runAs($store, function () use ($store, $events, $reorderPoint): array {
            $created = [];

            $lowProducts = Product::query()
                ->where('status', 'live')
                ->where('stock', '<=', $reorderPoint)
                ->orderBy('stock')
                ->get(['id', 'name', 'price', 'stock']);

            if ($lowProducts->isNotEmpty()) {
                $estimatedImpact = (int) $lowProducts->sum(fn (Product $p) => max(1, $reorderPoint - (int) $p->stock) * (int) $p->price);
                $items = $lowProducts->map(fn (Product $p) => [
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'stock' => (int) $p->stock,
                    'reorder_point' => $reorderPoint,
                ])->values()->all();

                $created[] = Insight::query()->create([
                    'store_id' => $store->id,
                    'agent' => 'coo',
                    'cadence' => 'hourly',
                    'type' => 'low_stock',
                    'urgency' => $lowProducts->contains(fn (Product $p) => (int) $p->stock === 0) ? 85 : 65,
                    'estimated_impact' => $estimatedImpact,
                    'title' => sprintf('%d product(s) below reorder point (%d)', $lowProducts->count(), $reorderPoint),
                    'summary' => 'Live products are running low on stock and risk lost sales if not restocked soon.',
                    'evidence' => [
                        'reorder_point' => $reorderPoint,
                        'count' => $lowProducts->count(),
                        'products' => $items,
                    ],
                    'recommended_action' => [
                        'label' => 'Review & restock low inventory',
                        'href' => '/products?filter=low',
                        'function' => null,
                    ],
                    'status' => 'open',
                    'is_urgent' => false,
                ]);
            }

            foreach ($events as $event) {
                $insight = $this->handleEvent($store, $event);
                if ($insight !== null) {
                    $created[] = $insight;
                }
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function handleEvent(Store $store, array $event): ?Insight
    {
        $type = (string) ($event['type'] ?? '');

        return match ($type) {
            'payment_failure' => Insight::query()->create([
                'store_id' => $store->id,
                'agent' => 'cto',
                'cadence' => 'event',
                'type' => 'payment_failure',
                'urgency' => 95,
                'estimated_impact' => (int) ($event['estimated_impact'] ?? 250_000),
                'title' => 'Payment gateway failure detected',
                'summary' => sprintf(
                    'Gateway %s failed %d time(s) recently; check payment settings immediately.',
                    (string) ($event['gateway'] ?? 'unknown'),
                    (int) ($event['failures'] ?? 1),
                ),
                'evidence' => [
                    'gateway' => (string) ($event['gateway'] ?? 'unknown'),
                    'failures' => (int) ($event['failures'] ?? 1),
                    'error' => (string) ($event['error'] ?? 'gateway_error'),
                ],
                'recommended_action' => [
                    'label' => 'Inspect payment gateway settings',
                    'href' => '/settings',
                    'function' => null,
                ],
                'status' => 'open',
                'is_urgent' => true,
            ]),
            'flagged_phone' => Insight::query()->create([
                'store_id' => $store->id,
                'agent' => 'risk',
                'cadence' => 'event',
                'type' => 'flagged_phone',
                'urgency' => 92,
                'estimated_impact' => (int) ($event['estimated_impact'] ?? 150_000),
                'title' => 'Suspected fraud: flagged phone number ordered',
                'summary' => sprintf(
                    'Order #%s came from a phone number with %d prior COD refusals.',
                    (string) ($event['order_number'] ?? 'N/A'),
                    (int) ($event['refusals'] ?? 1),
                ),
                'evidence' => [
                    'order_number' => (string) ($event['order_number'] ?? 'N/A'),
                    'refusals' => (int) ($event['refusals'] ?? 1),
                ],
                'recommended_action' => [
                    'label' => 'Verify order before shipping',
                    'href' => '/orders',
                    'function' => null,
                ],
                'status' => 'open',
                'is_urgent' => true,
            ]),
            'spike_404' => Insight::query()->create([
                'store_id' => $store->id,
                'agent' => 'cto',
                'cadence' => 'event',
                'type' => 'spike_404',
                'urgency' => 75,
                'estimated_impact' => (int) ($event['estimated_impact'] ?? 50_000),
                'title' => 'Storefront 404 error spike detected',
                'summary' => sprintf(
                    '%d broken-link 404 hits detected on %s.',
                    (int) ($event['count'] ?? 0),
                    (string) ($event['path'] ?? '/'),
                ),
                'evidence' => [
                    'path' => (string) ($event['path'] ?? '/'),
                    'count' => (int) ($event['count'] ?? 0),
                ],
                'recommended_action' => [
                    'label' => 'Add redirect or fix broken link',
                    'href' => '/seo',
                    'function' => null,
                ],
                'status' => 'open',
                'is_urgent' => false,
            ]),
            'low_roas' => Insight::query()->create([
                'store_id' => $store->id,
                'agent' => 'cmo',
                'cadence' => 'event',
                'type' => 'low_roas',
                'urgency' => 72,
                'estimated_impact' => (int) ($event['estimated_impact'] ?? 120_000),
                'title' => sprintf('Low ROAS on campaign "%s"', (string) ($event['campaign'] ?? 'Meta')),
                'summary' => sprintf(
                    'Campaign ROAS dropped to %.2fx (threshold %.2fx). Consider pausing or reallocating budget.',
                    (float) ($event['roas'] ?? 0),
                    (float) ($event['threshold'] ?? 2.0),
                ),
                'evidence' => [
                    'campaign' => (string) ($event['campaign'] ?? 'Meta'),
                    'roas' => (float) ($event['roas'] ?? 0),
                    'threshold' => (float) ($event['threshold'] ?? 2.0),
                    'spend_piasters' => (int) ($event['spend_piasters'] ?? 0),
                ],
                'recommended_action' => [
                    'label' => 'Propose budget adjustment',
                    'function' => 'spend_budget',
                    'payload' => ['campaign' => (string) ($event['campaign'] ?? 'Meta'), 'action' => 'reduce'],
                ],
                'status' => 'open',
                'is_urgent' => false,
            ]),
            default => null,
        };
    }
}
