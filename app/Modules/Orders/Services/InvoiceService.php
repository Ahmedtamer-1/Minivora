<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Platform\Models\Store;

/**
 * Printable invoice and packing slip data and HTML generation. Internal notes are never included.
 * FR-ORD-09
 */
class InvoiceService
{
    /**
     * Structured invoice data for an order (excluding internal notes).
     *
     * @return array<string, mixed>
     */
    public function invoiceData(Order $order): array
    {
        $order->loadMissing('items');
        $store = Store::query()->find($order->store_id);

        return [
            'invoice_number' => 'INV-'.$order->number,
            'order_number' => $order->number,
            'status' => $order->status->value,
            'placed_at' => ($order->placed_at ?? $order->created_at)?->timezone('Africa/Cairo')->format('Y-m-d H:i'),
            'payment_method' => $order->payment_method,
            'store' => [
                'name' => $store?->name ?? '',
                'subdomain' => $store?->subdomain ?? '',
                'locale' => $store?->default_locale ?? 'ar',
            ],
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->phone,
                'email' => $order->email,
                'governorate' => $order->governorate,
                'city' => $order->city,
                'address' => $order->address,
            ],
            'items' => $order->items->map(fn (OrderItem $item) => [
                'name' => $item->name,
                'variant' => $item->variant,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'unit_price_formatted' => $this->formatEgp($item->unit_price),
                'line_total' => $item->line_total,
                'line_total_formatted' => $this->formatEgp($item->line_total),
            ])->values()->all(),
            'totals' => [
                'subtotal' => $order->subtotal,
                'subtotal_formatted' => $this->formatEgp($order->subtotal),
                'shipping_fee' => $order->shipping_fee,
                'shipping_fee_formatted' => $this->formatEgp($order->shipping_fee),
                'discount' => $order->discount,
                'discount_formatted' => $this->formatEgp($order->discount),
                'total' => $order->total,
                'total_formatted' => $this->formatEgp($order->total),
                'currency' => 'EGP',
            ],
        ];
    }

    /**
     * Structured packing slip data for an order (excluding internal notes and prices).
     *
     * @return array<string, mixed>
     */
    public function packingSlipData(Order $order): array
    {
        $order->loadMissing('items');
        $store = Store::query()->find($order->store_id);

        return [
            'order_number' => $order->number,
            'placed_at' => ($order->placed_at ?? $order->created_at)?->timezone('Africa/Cairo')->format('Y-m-d H:i'),
            'courier_name' => $order->courier_name,
            'store' => [
                'name' => $store?->name ?? '',
                'subdomain' => $store?->subdomain ?? '',
                'locale' => $store?->default_locale ?? 'ar',
            ],
            'recipient' => [
                'name' => $order->customer_name,
                'phone' => $order->phone,
                'governorate' => $order->governorate,
                'city' => $order->city,
                'address' => $order->address,
            ],
            'items' => $order->items->map(fn (OrderItem $item) => [
                'name' => $item->name,
                'variant' => $item->variant,
                'quantity' => $item->quantity,
            ])->values()->all(),
            'total_items' => $order->itemsCount(),
        ];
    }

    /** Printable bilingual (EN/AR) EGP invoice HTML. Internal notes are never rendered. */
    public function renderInvoiceHtml(Order $order): string
    {
        $data = $this->invoiceData($order);
        $dir = $data['store']['locale'] === 'ar' ? 'rtl' : 'ltr';

        $rows = '';
        foreach ($data['items'] as $item) {
            $name = e($item['name'].($item['variant'] ? ' — '.$item['variant'] : ''));
            $qty = (int) $item['quantity'];
            $unit = e($item['unit_price_formatted']);
            $line = e($item['line_total_formatted']);
            $rows .= "<tr><td>{$name}</td><td class=\"num\">{$qty}</td><td class=\"num\">{$unit}</td><td class=\"num\">{$line}</td></tr>";
        }

        $storeName = e($data['store']['name']);
        $invoiceNumber = e($data['invoice_number']);
        $orderNumber = e('#'.$data['order_number']);
        $placedAt = e((string) $data['placed_at']);
        $paymentMethod = e(strtoupper((string) $data['payment_method']));
        $customerName = e($data['customer']['name']);
        $customerPhone = e((string) $data['customer']['phone']);
        $addressParts = array_filter([$data['customer']['address'], $data['customer']['city'], $data['customer']['governorate']]);
        $customerAddress = e(implode(', ', $addressParts));

        $subtotal = e($data['totals']['subtotal_formatted']);
        $shipping = e($data['totals']['shipping_fee_formatted']);
        $discount = e($data['totals']['discount_formatted']);
        $total = e($data['totals']['total_formatted']);

        return <<<HTML
<!DOCTYPE html>
<html lang="{$data['store']['locale']}" dir="{$dir}">
<head>
<meta charset="UTF-8">
<title>{$invoiceNumber} — {$storeName}</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;color:#111;margin:24px;line-height:1.5}
header{display:flex;justify-content:space-between;border-bottom:2px solid #111;padding-bottom:12px;margin-bottom:20px}
table{width:100%;border-collapse:collapse;margin:20px 0}
th,td{border-bottom:1px solid #ddd;padding:8px;text-align:start}
.num{text-align:end;white-space:nowrap}
.totals{max-width:320px;margin-inline-start:auto}
.totals td{border:none;padding:4px 8px}
.grand{font-weight:700;font-size:1.1em;border-top:2px solid #111}
@media print{body{margin:0}}
</style>
</head>
<body>
<header>
  <div><h1>{$storeName}</h1><div>Invoice / فاتورة: <strong>{$invoiceNumber}</strong></div></div>
  <div><div>Order / الطلب: <strong>{$orderNumber}</strong></div><div>Date / التاريخ: {$placedAt}</div><div>Payment / الدفع: {$paymentMethod}</div></div>
</header>
<section>
  <div><strong>Customer / العميل:</strong> {$customerName}</div>
  <div><strong>Phone / الهاتف:</strong> {$customerPhone}</div>
  <div><strong>Address / العنوان:</strong> {$customerAddress}</div>
</section>
<table>
  <thead><tr><th>Item / الصنف</th><th class="num">Qty / الكمية</th><th class="num">Unit / السعر</th><th class="num">Total / الإجمالي</th></tr></thead>
  <tbody>{$rows}</tbody>
</table>
<table class="totals">
  <tr><td>Subtotal / المجموع الفرعي</td><td class="num">{$subtotal}</td></tr>
  <tr><td>Shipping / الشحن</td><td class="num">{$shipping}</td></tr>
  <tr><td>Discount / الخصم</td><td class="num">-{$discount}</td></tr>
  <tr class="grand"><td>Total / الإجمالي</td><td class="num">{$total}</td></tr>
</table>
</body>
</html>
HTML;
    }

    /** Printable bilingual (EN/AR) packing slip HTML. Internal notes are never rendered. */
    public function renderPackingSlipHtml(Order $order): string
    {
        $data = $this->packingSlipData($order);
        $dir = $data['store']['locale'] === 'ar' ? 'rtl' : 'ltr';

        $rows = '';
        foreach ($data['items'] as $item) {
            $name = e($item['name'].($item['variant'] ? ' — '.$item['variant'] : ''));
            $qty = (int) $item['quantity'];
            $rows .= "<tr><td>{$name}</td><td class=\"num\">{$qty}</td></tr>";
        }

        $storeName = e($data['store']['name']);
        $orderNumber = e('#'.$data['order_number']);
        $placedAt = e((string) $data['placed_at']);
        $courier = e((string) ($data['courier_name'] ?? ''));
        $recipientName = e($data['recipient']['name']);
        $recipientPhone = e((string) $data['recipient']['phone']);
        $addressParts = array_filter([$data['recipient']['address'], $data['recipient']['city'], $data['recipient']['governorate']]);
        $recipientAddress = e(implode(', ', $addressParts));
        $totalItems = (int) $data['total_items'];

        return <<<HTML
<!DOCTYPE html>
<html lang="{$data['store']['locale']}" dir="{$dir}">
<head>
<meta charset="UTF-8">
<title>Packing Slip {$orderNumber} — {$storeName}</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;color:#111;margin:24px;line-height:1.5}
header{display:flex;justify-content:space-between;border-bottom:2px solid #111;padding-bottom:12px;margin-bottom:20px}
table{width:100%;border-collapse:collapse;margin:20px 0}
th,td{border-bottom:1px solid #ddd;padding:8px;text-align:start}
.num{text-align:end;white-space:nowrap}
@media print{body{margin:0}}
</style>
</head>
<body>
<header>
  <div><h1>{$storeName}</h1><div>Packing Slip / بوليصة التعبئة</div></div>
  <div><div>Order / الطلب: <strong>{$orderNumber}</strong></div><div>Date / التاريخ: {$placedAt}</div><div>Courier / شركة الشحن: {$courier}</div></div>
</header>
<section>
  <div><strong>Ship To / المستلم:</strong> {$recipientName}</div>
  <div><strong>Phone / الهاتف:</strong> {$recipientPhone}</div>
  <div><strong>Address / العنوان:</strong> {$recipientAddress}</div>
</section>
<table>
  <thead><tr><th>Item / الصنف</th><th class="num">Qty / الكمية</th></tr></thead>
  <tbody>{$rows}</tbody>
</table>
<div><strong>Total Items / إجمالي القطع:</strong> {$totalItems}</div>
</body>
</html>
HTML;
    }

    private function formatEgp(int $piasters): string
    {
        return number_format($piasters / 100, 2).' EGP';
    }
}
