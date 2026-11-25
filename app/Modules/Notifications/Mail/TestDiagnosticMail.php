<?php

namespace App\Modules\Notifications\Mail;

use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Diagnostic test email containing store name, timestamp, and sender diagnostics. FR-NTF-03
 */
class TestDiagnosticMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public readonly Carbon $sentAt;

    public readonly string $mailerDriver;

    public readonly string $fromAddress;

    public readonly string $fromName;

    public function __construct(
        public readonly Store $store,
        public readonly ?User $staff = null,
        ?Carbon $sentAt = null,
    ) {
        $this->sentAt = $sentAt ?? Carbon::now();
        $this->mailerDriver = (string) config('mail.default', 'log');
        $this->fromAddress = (string) config('mail.from.address', 'hello@example.com');
        $this->fromName = (string) config('mail.from.name', 'Minivora');
    }

    public function build(): static
    {
        $storeName = e($this->store->name);
        $timestamp = e($this->sentAt->toIso8601String());
        $mailer = e($this->mailerDriver);
        $fromAddress = e($this->fromAddress);
        $fromName = e($this->fromName);
        $triggeredBy = $this->staff !== null
            ? e("{$this->staff->name} ({$this->staff->email})")
            : 'system';

        $subject = "[Diagnostic] {$this->store->name} — Email delivery test";

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head><meta charset="UTF-8"><title>{$subject}</title></head>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#1f2937;">
  <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
    <div style="background:#111827;color:#ffffff;padding:20px 24px;">
      <h1 style="margin:0;font-size:20px;">{$storeName} — Email Diagnostics</h1>
    </div>
    <div style="padding:24px;font-size:14px;line-height:1.6;">
      <p style="margin:0 0 16px;">This is a diagnostic test email confirming that outbound email delivery is working for <strong>{$storeName}</strong>.</p>
      <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <tr><td style="padding:8px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Store</td><td style="padding:8px 0;border-bottom:1px solid #e5e7eb;"><strong>{$storeName}</strong></td></tr>
        <tr><td style="padding:8px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Timestamp</td><td style="padding:8px 0;border-bottom:1px solid #e5e7eb;">{$timestamp}</td></tr>
        <tr><td style="padding:8px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Mailer driver</td><td style="padding:8px 0;border-bottom:1px solid #e5e7eb;">{$mailer}</td></tr>
        <tr><td style="padding:8px 0;border-bottom:1px solid #e5e7eb;color:#6b7280;">Sender address</td><td style="padding:8px 0;border-bottom:1px solid #e5e7eb;">{$fromName} &lt;{$fromAddress}&gt;</td></tr>
        <tr><td style="padding:8px 0;color:#6b7280;">Triggered by</td><td style="padding:8px 0;">{$triggeredBy}</td></tr>
      </table>
    </div>
  </div>
</body>
</html>
HTML;

        return $this->subject($subject)->html($html);
    }
}
