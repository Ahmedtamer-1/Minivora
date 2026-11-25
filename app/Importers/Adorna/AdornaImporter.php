<?php

namespace App\Importers\Adorna;

use App\Core\Tenancy\CurrentStore;
use App\Importers\Adorna\Mappers\CustomerMapper;
use App\Importers\Adorna\Mappers\OrderMapper;
use App\Importers\Adorna\Mappers\ProductMapper;
use App\Importers\Adorna\Mappers\VisitMapper;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * One-way Adorna EG importer and piaster-exact reconciler. FR-ONB-09, V-07
 *
 * Imports categories, products, variants, shipping zones, customers, orders,
 * order items, ad spends, and visits inside CurrentStore::runAs($store, ...) within
 * a single database transaction. Reconciles imported totals against Adorna's
 * expected records with zero-tolerance piaster-exact parity.
 */
class AdornaImporter
{
    public function __construct(
        private readonly CurrentStore $current,
        private readonly ProductMapper $products,
        private readonly CustomerMapper $customers,
        private readonly OrderMapper $orders,
        private readonly VisitMapper $visits,
    ) {}

    /**
     * Import an Adorna EG export dataset into the target store.
     *
     * @param  array<string, mixed>  $dataset
     * @return array{
     *     categories: array{imported: int, skipped: int},
     *     products: array{imported: int, skipped: int},
     *     variants: array{imported: int, skipped: int},
     *     shipping_zones: array{imported: int, skipped: int},
     *     customers: array{imported: int, skipped: int},
     *     orders: array{imported: int, skipped: int},
     *     order_items: array{imported: int, skipped: int},
     *     ad_spends: array{imported: int, skipped: int},
     *     visits: array{imported: int, skipped: int},
     *     page_views: array{imported: int, skipped: int},
     * }
     */
    public function import(Store $store, array $dataset): array
    {
        return $this->current->runAs($store, function () use ($dataset): array {
            return DB::transaction(function () use ($dataset): array {
                $categoryResult = $this->products->importCategories(
                    is_array($dataset['categories'] ?? null) ? $dataset['categories'] : [],
                );

                $productResult = $this->products->importProducts(
                    is_array($dataset['products'] ?? null) ? $dataset['products'] : [],
                    is_array($dataset['variants'] ?? null) ? $dataset['variants'] : [],
                    $categoryResult['by_slug'],
                    $categoryResult['by_external_id'],
                );

                $shippingResult = $this->orders->importShippingZones(
                    is_array($dataset['shipping_zones'] ?? null) ? $dataset['shipping_zones'] : [],
                );

                $customerResult = $this->customers->import(
                    is_array($dataset['customers'] ?? null) ? $dataset['customers'] : [],
                );

                $orderResult = $this->orders->importOrders(
                    is_array($dataset['orders'] ?? null) ? $dataset['orders'] : [],
                    $customerResult['by_phone'],
                    $customerResult['by_external_id'],
                    $productResult['by_sku'],
                    $productResult['by_slug'],
                    $productResult['by_external_id'],
                );

                $adSpendResult = $this->orders->importAdSpends(
                    is_array($dataset['ad_spends'] ?? null) ? $dataset['ad_spends'] : [],
                );

                $visitResult = $this->visits->import(
                    is_array($dataset['visits'] ?? null) ? $dataset['visits'] : [],
                    $productResult['by_sku'],
                    $productResult['by_slug'],
                    $productResult['by_external_id'],
                );

                return [
                    'categories' => [
                        'imported' => $categoryResult['imported'],
                        'skipped' => $categoryResult['skipped'],
                    ],
                    'products' => $productResult['products'],
                    'variants' => $productResult['variants'],
                    'shipping_zones' => $shippingResult,
                    'customers' => [
                        'imported' => $customerResult['imported'],
                        'skipped' => $customerResult['skipped'],
                    ],
                    'orders' => $orderResult['orders'],
                    'order_items' => $orderResult['order_items'],
                    'ad_spends' => $adSpendResult,
                    'visits' => $visitResult['visits'],
                    'page_views' => $visitResult['page_views'],
                ];
            });
        });
    }

    /**
     * Reconcile actual store database totals against expected Adorna EG records. V-07
     *
     * Verifies piaster-exact parity across order_count, total_stock, and monthly_sales
     * (map of 'YYYY-MM' => int piasters for sales orders, excluding cancelled/refunded/returned).
     *
     * @param  array{
     *     order_count?: int,
     *     total_stock?: int,
     *     monthly_sales?: array<string, int>,
     * }  $expected
     * @return array{
     *     matched: bool,
     *     diffs: array<string, mixed>,
     *     actual: array{
     *         order_count: int,
     *         total_stock: int,
     *         monthly_sales: array<string, int>,
     *     },
     * }
     */
    public function reconcile(Store $store, array $expected): array
    {
        return $this->current->runAs($store, function () use ($expected): array {
            $actualOrderCount = Order::query()->count();
            $actualTotalStock = (int) Product::query()->sum('stock');

            $actualMonthlySales = [];
            $saleOrders = Order::query()
                ->countingAsSale()
                ->get(['total', 'placed_at', 'created_at']);

            foreach ($saleOrders as $order) {
                $timestamp = $order->placed_at ?? $order->created_at;
                $month = $timestamp->format('Y-m');
                $actualMonthlySales[$month] = ($actualMonthlySales[$month] ?? 0) + (int) $order->total;
            }
            ksort($actualMonthlySales);

            $diffs = [];

            if (array_key_exists('order_count', $expected)) {
                $expectedCount = (int) $expected['order_count'];
                if ($actualOrderCount !== $expectedCount) {
                    $diffs['order_count'] = [
                        'expected' => $expectedCount,
                        'actual' => $actualOrderCount,
                        'delta' => $actualOrderCount - $expectedCount,
                    ];
                }
            }

            if (array_key_exists('total_stock', $expected)) {
                $expectedStock = (int) $expected['total_stock'];
                if ($actualTotalStock !== $expectedStock) {
                    $diffs['total_stock'] = [
                        'expected' => $expectedStock,
                        'actual' => $actualTotalStock,
                        'delta' => $actualTotalStock - $expectedStock,
                    ];
                }
            }

            if (array_key_exists('monthly_sales', $expected) && is_array($expected['monthly_sales'])) {
                $months = array_values(array_unique(array_merge(
                    array_keys($expected['monthly_sales']),
                    array_keys($actualMonthlySales),
                )));
                sort($months);

                $monthDiffs = [];
                foreach ($months as $month) {
                    $expectedPiasters = (int) ($expected['monthly_sales'][$month] ?? 0);
                    $actualPiasters = (int) ($actualMonthlySales[$month] ?? 0);

                    if ($actualPiasters !== $expectedPiasters) {
                        $monthDiffs[$month] = [
                            'expected' => $expectedPiasters,
                            'actual' => $actualPiasters,
                            'delta' => $actualPiasters - $expectedPiasters,
                        ];
                    }
                }

                if ($monthDiffs !== []) {
                    $diffs['monthly_sales'] = $monthDiffs;
                }
            }

            return [
                'matched' => $diffs === [],
                'diffs' => $diffs,
                'actual' => [
                    'order_count' => $actualOrderCount,
                    'total_stock' => $actualTotalStock,
                    'monthly_sales' => $actualMonthlySales,
                ],
            ];
        });
    }
}
