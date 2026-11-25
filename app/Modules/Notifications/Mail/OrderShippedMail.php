<?php

namespace App\Modules\Notifications\Mail;

use App\Modules\Orders\Models\Order;
use App\Modules\Platform\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Bilingual (AR/EN) store-branded shipping notification with courier name and tracking URL.
 * FR-NTF-01
 */
class OrderShippedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public readonly string $mailLocale;

    public function __construct(
        public readonly Order $order,
        public readonly Store $store,
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
        $courier = $this->order->courier_name !== null && $this->order->courier_name !== ''
            ? e($this->order->courier_name)
            : ($isArabic ? 'شركة الشحن' : 'Courier');

        $subject = $isArabic
            ? "{$this->store->name} — تم شحن طلبك {$orderNumber}"
            : "{$this->store->name} — Your order {$orderNumber} has shipped";

        $heading = $isArabic ? 'طلبك في الطريق إليك' : 'Your order is on the way';
        $greeting = $isArabic ? "مرحباً {$customerName}،" : "Hello {$customerName},";
        $body = $isArabic
            ? "تم شحن طلبك رقم {$orderNumber} عبر {$courier}."
            : "Your order {$orderNumber} has been shipped via {$courier}.";
        $courierLabel = $isArabic ? 'شركة الشحن' : 'Courier';
        $trackButtonLabel = $isArabic ? 'تتبع شحنتك' : 'Track your shipment';
        $footer = $isArabic
            ? "شكراً لتسوقك من {$storeName}."
            : "Thank you for shopping with {$storeName}.";

        $trackingHtml = '';
        if ($this->order->tracking_url !== null && $this->order->tracking_url !== '') {
            $url = e($this->order->tracking_url);
            $trackingHtml = <<<HTML
      <p style="margin:20px 0 8px;">
        <a href="{$url}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;font-size:14px;">{$trackButtonLabel}</a>
      </p>
      <p style="margin:0;font-size:13px;color:#6b7280;word-break:break-all;">{$url}</p>
HTML;
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
      <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">{$body}</p>
      <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;font-size:14px;">
        <p style="margin:0 0 6px;"><strong>{$courierLabel}:</strong> {$courier}</p>
        <p style="margin:0;"><strong>{$orderNumber}</strong></p>
      </div>
      {$trackingHtml}
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
}
