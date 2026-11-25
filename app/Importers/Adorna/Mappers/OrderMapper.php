<?php

namespace App\Importers\Adorna\Mappers;

use App\Importers\Adorna\AdornaMoney;
use App\Modules\Catalog\Models\Product;
use App\Modules\Marketing\Models\AdSpend;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\ShippingZone;
use Illuminate\Support\Carbon;

/**
 * Maps Adorna shipping zones, orders, order items, and ad spends into the current store.
 * All money values are stored in integer piasters.
 * Idempotent by (store_id, governorate) for shipping zones, (store_id, number) for orders,
 * and (store_id, channel, campaign_name, spend_date) for ad spends.
 */
class OrderMapper
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int}
     */
    public function importShippingZones(array $rows): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $index => $row) {
            $governorate = trim((string) ($row['governorate'] ?? ''));
            if ($governorate === '') {
                continue;
            }

            $existing = ShippingZone::query()->where('governorate', $governorate)->first();

            $attributes = [
                'governorate' => $governorate,
                'rate' => (int) AdornaMoney::extract($row, 'rate', 0),
                'delivery_days' => max(1, (int) ($row['delivery_days'] ?? 3)),
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ];

            if ($existing !== null) {
                $existing->fill($attributes)->save();
                $skipped++;
            } else {
                ShippingZone::query()->create($attributes);
                $imported++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, Customer>  $customersByPhone
     * @param  array<string, Customer>  $customersByExternalId
     * @param  array<string, Product>  $productsBySku
     * @param  array<string, Product>  $productsBySlug
     * @param  array<string, Product>  $productsByExternalId
     * @return array{
     *     orders: array{imported: int, skipped: int},
     *     order_items: array{imported: int, skipped: int},
     * }
     */
    public function importOrders(
        array $rows,
        array $customersByPhone = [],
        array $customersByExternalId = [],
        array $productsBySku = [],
        array $productsBySlug = [],
        array $productsByExternalId = [],
    ): array {
        $ordersImported = 0;
        $ordersSkipped = 0;
        $itemsImported = 0;
        $itemsSkipped = 0;
        $touchedCustomerIds = [];

        foreach ($rows as $row) {
            $number = (int) ($row['number'] ?? 0);
            if ($number <= 0) {
                continue;
            }

            $existing = Order::query()->where('number', $number)->first();
            if ($existing !== null) {
                $ordersSkipped++;
                $itemsSkipped += is_array($row['items'] ?? null) ? count($row['items']) : 0;

                continue;
            }

            $phone = CustomerMapper::normalizePhone(isset($row['phone']) ? (string) $row['phone'] : null);
            $customer = $this->resolveCustomer($row, $phone, $customersByPhone, $customersByExternalId);

            $rawItems = is_array($row['items'] ?? null) ? $row['items'] : [];
            $preparedItems = [];
            $computedSubtotal = 0;

            foreach ($rawItems as $itemRow) {
                $quantity = max(1, (int) ($itemRow['quantity'] ?? 1));
                $unitPrice = (int) AdornaMoney::extract($itemRow, 'unit_price', 0);
                $unitCost = AdornaMoney::extract($itemRow, 'unit_cost', null);
                $lineTotal = AdornaMoney::extract($itemRow, 'line_total', null);
                $lineTotal = $lineTotal !== null ? (int) $lineTotal : ($unitPrice * $quantity);

                $product = $this->resolveProduct($itemRow, $productsBySku, $productsBySlug, $productsByExternalId);

                $preparedItems[] = [
                    'product_id' => $product?->id,
                    'name' => (string) ($itemRow['name'] ?? $product?->name ?? ''),
                    'variant' => isset($itemRow['variant']) ? (string) $itemRow['variant'] : null,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];

                $computedSubtotal += $lineTotal;
            }

            $subtotal = AdornaMoney::extract($row, 'subtotal', null);
            $subtotal = $subtotal !== null ? (int) $subtotal : $computedSubtotal;
            $shippingFee = (int) AdornaMoney::extract($row, 'shipping_fee', 0);
            $discount = (int) AdornaMoney::extract($row, 'discount', 0);
            $total = AdornaMoney::extract($row, 'total', null);
            $total = $total !== null ? (int) $total : max(0, $subtotal + $shippingFee - $discount);

            $status = $this->mapStatus((string) ($row['status'] ?? 'pending'));
            $placedAt = isset($row['placed_at']) ? Carbon::parse((string) $row['placed_at']) : Carbon::now();

            $order = new Order([
                'number' => $number,
                'customer_id' => $customer?->id,
                'status' => $status,
                'channel' => $this->mapChannel((string) ($row['channel'] ?? 'website')),
                'payment_method' => $this->mapPaymentMethod((string) ($row['payment_method'] ?? 'cod')),
                'customer_name' => trim((string) ($row['customer_name'] ?? $customer?->name ?? '')),
                'phone' => $phone ?? $customer?->phone,
                'email' => isset($row['email']) ? (string) $row['email'] : $customer?->email,
                'governorate' => isset($row['governorate']) ? (string) $row['governorate'] : $customer?->governorate,
                'city' => isset($row['city']) ? (string) $row['city'] : $customer?->city,
                'address' => isset($row['address']) ? (string) $row['address'] : $customer?->address,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'discount' => $discount,
                'total' => $total,
                'courier_name' => isset($row['courier_name']) ? (string) $row['courier_name'] : null,
                'tracking_url' => isset($row['tracking_url']) ? (string) $row['tracking_url'] : null,
                'notes' => isset($row['notes']) ? (string) $row['notes'] : null,
                'tags' => is_array($row['tags'] ?? null) ? array_values($row['tags']) : null,
                'stock_deducted' => (bool) ($row['stock_deducted'] ?? $status->countsAsSale()),
                'placed_at' => $placedAt,
            ]);

            if (isset($row['paid_at'])) {
                $order->paid_at = Carbon::parse((string) $row['paid_at']);
            } elseif (in_array($status, [OrderStatus::Paid, OrderStatus::Delivered], true)) {
                $order->paid_at = $placedAt;
            }

            if (isset($row['shipped_at'])) {
                $order->shipped_at = Carbon::parse((string) $row['shipped_at']);
            }
            if (isset($row['delivered_at'])) {
                $order->delivered_at = Carbon::parse((string) $row['delivered_at']);
            } elseif ($status === OrderStatus::Delivered) {
                $order->delivered_at = $placedAt;
            }
            if (isset($row['cancelled_at'])) {
                $order->cancelled_at = Carbon::parse((string) $row['cancelled_at']);
            } elseif ($status === OrderStatus::Cancelled) {
                $order->cancelled_at = $placedAt;
            }

            $order->save();
            $ordersImported++;

            foreach ($preparedItems as $itemData) {
                $order->items()->create([
                    'product_id' => $itemData['product_id'],
                    'name' => $itemData['name'],
                    'variant' => $itemData['variant'],
                    'unit_price' => $itemData['unit_price'],
                    'unit_cost' => $itemData['unit_cost'],
                    'quantity' => $itemData['quantity'],
                    'line_total' => $itemData['line_total'],
                ]);
                $itemsImported++;
            }

            if ($customer !== null) {
                $touchedCustomerIds[$customer->id] = true;
            }
        }

        $this->recalculateCustomerStats(array_keys($touchedCustomerIds));

        return [
            'orders' => ['imported' => $ordersImported, 'skipped' => $ordersSkipped],
            'order_items' => ['imported' => $itemsImported, 'skipped' => $itemsSkipped],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int}
     */
    public function importAdSpends(array $rows): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $channel = strtolower(trim((string) ($row['channel'] ?? 'meta')));
            $campaignName = trim((string) ($row['campaign_name'] ?? ''));
            $spendDate = isset($row['spend_date'])
                ? Carbon::parse((string) $row['spend_date'])->toDateString()
                : null;

            if ($campaignName === '' || $spendDate === null) {
                continue;
            }

            $amount = (int) AdornaMoney::extract($row, 'amount', 0);

            $existing = AdSpend::query()
                ->where('channel', $channel)
                ->where('campaign_name', $campaignName)
                ->whereDate('spend_date', $spendDate)
                ->first();

            if ($existing !== null) {
                $existing->amount = $amount;
                $existing->save();
                $skipped++;
            } else {
                AdSpend::query()->create([
                    'channel' => $channel,
                    'campaign_name' => $campaignName,
                    'spend_date' => $spendDate,
                    'amount' => $amount,
                ]);
                $imported++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, Customer>  $byPhone
     * @param  array<string, Customer>  $byExternalId
     */
    private function resolveCustomer(
        array $row,
        ?string $phone,
        array &$byPhone,
        array $byExternalId,
    ): ?Customer {
        if (isset($row['customer_external_id']) && isset($byExternalId[(string) $row['customer_external_id']])) {
            return $byExternalId[(string) $row['customer_external_id']];
        }

        if (isset($row['customer_id']) && isset($byExternalId[(string) $row['customer_id']])) {
            return $byExternalId[(string) $row['customer_id']];
        }

        if ($phone !== null) {
            if (isset($byPhone[$phone])) {
                return $byPhone[$phone];
            }

            $existing = Customer::query()->where('phone', $phone)->first();
            if ($existing !== null) {
                $byPhone[$phone] = $existing;

                return $existing;
            }

            $created = Customer::query()->create([
                'name' => trim((string) ($row['customer_name'] ?? '')),
                'phone' => $phone,
                'email' => isset($row['email']) ? (string) $row['email'] : null,
                'governorate' => isset($row['governorate']) ? (string) $row['governorate'] : null,
                'city' => isset($row['city']) ? (string) $row['city'] : null,
                'address' => isset($row['address']) ? (string) $row['address'] : null,
            ]);
            $byPhone[$phone] = $created;

            return $created;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $itemRow
     * @param  array<string, Product>  $bySku
     * @param  array<string, Product>  $bySlug
     * @param  array<string, Product>  $byExternalId
     */
    private function resolveProduct(
        array $itemRow,
        array $bySku,
        array $bySlug,
        array $byExternalId,
    ): ?Product {
        if (isset($itemRow['product_sku']) && isset($bySku[(string) $itemRow['product_sku']])) {
            return $bySku[(string) $itemRow['product_sku']];
        }

        if (isset($itemRow['sku']) && isset($bySku[(string) $itemRow['sku']])) {
            return $bySku[(string) $itemRow['sku']];
        }

        if (isset($itemRow['product_slug']) && isset($bySlug[(string) $itemRow['product_slug']])) {
            return $bySlug[(string) $itemRow['product_slug']];
        }

        if (isset($itemRow['product_id']) && isset($byExternalId[(string) $itemRow['product_id']])) {
            return $byExternalId[(string) $itemRow['product_id']];
        }

        return null;
    }

    /** @param  list<int>  $customerIds */
    private function recalculateCustomerStats(array $customerIds): void
    {
        foreach ($customerIds as $customerId) {
            $customer = Customer::query()->find($customerId);
            if ($customer === null) {
                continue;
            }

            $saleOrders = Order::query()
                ->where('customer_id', $customerId)
                ->countingAsSale()
                ->get(['total', 'placed_at', 'created_at']);

            $customer->orders_count = $saleOrders->count();
            $customer->total_spent = (int) $saleOrders->sum('total');
            $customer->last_order_at = $saleOrders
                ->map(fn (Order $o) => $o->placed_at ?? $o->created_at)
                ->filter()
                ->max();
            $customer->save();
        }
    }

    private function mapStatus(string $raw): OrderStatus
    {
        return match (strtolower(trim($raw))) {
            'paid' => OrderStatus::Paid,
            'processing', 'confirmed' => OrderStatus::Processing,
            'shipped', 'in_transit' => OrderStatus::Shipped,
            'delivered', 'completed' => OrderStatus::Delivered,
            'cancelled', 'canceled' => OrderStatus::Cancelled,
            'refunded' => OrderStatus::Refunded,
            'returned' => OrderStatus::Returned,
            default => OrderStatus::Pending,
        };
    }

    private function mapChannel(string $raw): string
    {
        $normalized = strtolower(trim($raw));

        return in_array($normalized, Order::CHANNELS, true) ? $normalized : 'website';
    }

    private function mapPaymentMethod(string $raw): string
    {
        $normalized = strtolower(trim($raw));

        return in_array($normalized, Order::PAYMENT_METHODS, true) ? $normalized : 'cod';
    }
}
