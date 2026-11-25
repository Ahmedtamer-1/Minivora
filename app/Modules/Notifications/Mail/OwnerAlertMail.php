<?php

namespace App\Modules\Notifications\Mail;

use App\Modules\Notifications\Models\Alert;
use App\Modules\Platform\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Owner alert email for new orders, low stock, pending AI approvals, and anomalies. FR-NTF-04
 */
class OwnerAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public readonly string $mailLocale;

    public function __construct(
        public readonly Alert $alert,
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
        $title = e($this->alert->title);
        $body = nl2br(e($this->alert->body));
        $typeLabel = e($this->typeLabel($isArabic));
        $severity = e(strtoupper($this->alert->severity));
        $badgeColor = match ($this->alert->severity) {
            'critical' => '#dc2626',
            'warning' => '#d97706',
            default => '#2563eb',
        };

        $subject = "[{$this->store->name}] {$this->alert->title}";

        $actionHtml = '';
        if ($this->alert->action_url !== null && $this->alert->action_url !== '') {
            $url = e($this->alert->action_url);
            $cta = $isArabic ? 'عرض التفاصيل' : 'View details';
            $actionHtml = "<p style=\"margin:20px 0 0;\"><a href=\"{$url}\" style=\"display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;font-size:14px;\">{$cta}</a></p>";
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
      <div style="margin:0 0 12px;">
        <span style="display:inline-block;background:{$badgeColor};color:#ffffff;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:bold;">{$severity}</span>
        <span style="margin:0 8px;font-size:13px;color:#6b7280;">{$typeLabel}</span>
      </div>
      <h2 style="margin:0 0 12px;font-size:18px;color:#111827;">{$title}</h2>
      <p style="margin:0;font-size:15px;line-height:1.6;">{$body}</p>
      {$actionHtml}
    </div>
  </div>
</body>
</html>
HTML;

        return $this->subject($subject)->html($html);
    }

    private function typeLabel(bool $isArabic): string
    {
        if ($isArabic) {
            return match ($this->alert->type) {
                'new_order' => 'طلب جديد',
                'low_stock' => 'مخزون منخفض',
                'pending_approval' => 'موافقة معلقة',
                'anomaly' => 'تنبيه شذوذ',
                default => $this->alert->type,
            };
        }

        return match ($this->alert->type) {
            'new_order' => 'New Order',
            'low_stock' => 'Low Stock',
            'pending_approval' => 'Pending Approval',
            'anomaly' => 'Anomaly Alert',
            default => $this->alert->type,
        };
    }
}
