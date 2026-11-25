<?php

namespace App\Modules\Notifications\Mail;

use App\Core\Money\MoneyFormatter;
use App\Modules\Orders\Models\Order;
use App\Modules\Platform\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Bilingual (AR/EN) store-branded shopper email for order lifecycle events:
 * confirmation, paid, cancelled, refunded, return_approved. FR-NTF-01, FR-NTF-02
 */
class OrderConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public const EVENTS = ['confirmation', 'paid', 'cancelled', 'refunded', 'return_approved'];

    public readonly string $mailLocale;

    public function __construct(
        public readonly Order $order,
        public readonly Store $store,
        public readonly string $event = 'confirmation',
        ?string $locale = null,
    ) {
        $resolved = $locale ?? $store->default_locale ?? 'ar';
        $this->mailLocale = $resolved === 'en' ? 'en' : 'ar';
        $this->locale($this->mailLocale);
    }

    public function build(): static
    {
        $isArabic = $this->mailLocale === 'ar';
        $dir = $isArabic ? 'rtl' : 'ltr';
        $align = $isArabic ? 'right' : 'left';
        $storeName = e($this->store->name);
        $customerName = e($this->order->customer_name);
        $orderNumber = '#'.$this->order->number;
        $formattedTotal = e(MoneyFormatter::format($this->order->total, $this->mailLocale));

        [$subject, $heading, $bodyLine] = $this->eventCopy($orderNumber, $isArabic);

        $greeting = $isArabic ? "مرحباً {$customerName}،" : "Hello {$customerName},";
        $orderLabel = $isArabic ? 'رقم الطلب' : 'Order number';
        $totalLabel = $isArabic ? 'الإجمالي' : 'Total';
        $footer = $isArabic
            ? "شكراً لتسوقك من {$storeName}."
            : "Thank you for shopping with {$storeName}.";

        $itemsHtml = '';
        $this->order->loadMissing('items');
        if ($this->order->items->isNotEmpty()) {
            $itemsTitle = $isArabic ? 'تفاصيل المنتجات' : 'Order items';
            $rows = '';
            foreach ($this->order->items as $item) {
                $itemName = e($item->product_name ?? $item->name ?? '');
                $qty = (int) $item->quantity;
                $lineTotal = e(MoneyFormatter::format((int) ($item->line_total ?? ($item->unit_price * $qty)), $this->mailLocale));
                $rows .= "<tr><td style=\"padding:6px 0;border-bottom:1px solid #e5e7eb;\">{$itemName} &times; {$qty}</td>"
                    .'<td style="padding:6px 0;border-bottom:1px solid #e5e7eb;text-align:'.($isArabic ? 'left' : 'right').";\">{$lineTotal}</td></tr>";
            }
            $itemsHtml = "<h3 style=\"margin:20px 0 8px;font-size:15px;color:#111827;\">{$itemsTitle}</h3>"
                ."<table style=\"width:100%;border-collapse:collapse;font-size:14px;\">{$rows}</table>";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="{$this->mailLocale}" dir="{$dir}">
<head><meta charset="UTF-8"><title>{$subject}</title></head>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#1f2937;direction:{$dir};text-align:{$align};">
  <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
    <div style="background:#111827;color:#ffffff;padding:20px 24px;">
      <h1 style="margin:0;font-size:20px;">{$storeName}</h1>
    </div>
    <div style="padding:24px;">
      <h2 style="margin:0 0 12px;font-size:18px;color:#111827;">{$heading}</h2>
      <p style="margin:0 0 12px;font-size:15px;">{$greeting}</p>
      <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">{$bodyLine}</p>
      <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;font-size:14px;">
        <p style="margin:0 0 6px;"><strong>{$orderLabel}:</strong> {$orderNumber}</p>
        <p style="margin:0;"><strong>{$totalLabel}:</strong> {$formattedTotal}</p>
      </div>
      {$itemsHtml}
    </div>
    <div style="padding:16px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:13px;color:#6b7280;">
      {$footer}
    </div>
  </div>
</body>
</html>
HTML;

        return $this->subject($subject)->html($html);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function eventCopy(string $orderNumber, bool $isArabic): array
    {
        $storeName = $this->store->name;

        if ($isArabic) {
            return match ($this->event) {
                'paid' => [
                    "{$storeName} — تم تأكيد الدفع للطلب {$orderNumber}",
                    'تم استلام الدفع بنجاح',
                    "تم تأكيد عملية الدفع لطلبك رقم {$orderNumber} وجارٍ تجهيزه للشحن.",
                ],
                'cancelled' => [
                    "{$storeName} — تم إلغاء الطلب {$orderNumber}",
                    'تم إلغاء طلبك',
                    "نؤكد إلغاء طلبك رقم {$orderNumber}. إذا كان لديك أي استفسار يرجى التواصل معنا.",
                ],
                'refunded' => [
                    "{$storeName} — تم استرداد مبلغ الطلب {$orderNumber}",
                    'تم إصدار استرداد مالي',
                    "تمت معالجة استرداد المبلغ الخاص بطلبك رقم {$orderNumber}.",
                ],
                'return_approved' => [
                    "{$storeName} — تمت الموافقة على طلب الاسترجاع {$orderNumber}",
                    'تمت الموافقة على الاسترجاع',
                    "تمت الموافقة على طلب الاسترجاع الخاص بطلبك رقم {$orderNumber}.",
                ],
                default => [
                    "{$storeName} — تأكيد الطلب {$orderNumber}",
                    'تم استلام طلبك بنجاح',
                    "شكراً لك! تم تسجيل طلبك رقم {$orderNumber} وسنقوم بتحديثك فور تجهيزه.",
                ],
            };
        }

        return match ($this->event) {
            'paid' => [
                "{$storeName} — Payment received for order {$orderNumber}",
                'Payment confirmed',
                "We have received your payment for order {$orderNumber} and are preparing it for shipment.",
            ],
            'cancelled' => [
                "{$storeName} — Order {$orderNumber} cancelled",
                'Your order has been cancelled',
                "Your order {$orderNumber} has been cancelled. Please reply if you have any questions.",
            ],
            'refunded' => [
                "{$storeName} — Refund issued for order {$orderNumber}",
                'Refund processed',
                "A refund has been issued for your order {$orderNumber}.",
            ],
            'return_approved' => [
                "{$storeName} — Return approved for order {$orderNumber}",
                'Your return request is approved',
                "Your return request for order {$orderNumber} has been approved.",
            ],
            default => [
                "{$storeName} — Order {$orderNumber} confirmed",
                'We received your order',
                "Thank you! Your order {$orderNumber} has been placed and we will update you once it ships.",
            ],
        };
    }
}
