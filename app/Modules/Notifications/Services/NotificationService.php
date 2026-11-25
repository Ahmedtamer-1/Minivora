<?php

namespace App\Modules\Notifications\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Core\AuditLog\Actor;
use App\Core\Money\MoneyFormatter;
use App\Core\Permissions\Role;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\StoreMember;
use App\Modules\Accounts\Models\User;
use App\Modules\Notifications\Mail\OrderConfirmationMail;
use App\Modules\Notifications\Mail\OrderShippedMail;
use App\Modules\Notifications\Mail\OwnerAlertMail;
use App\Modules\Notifications\Mail\TestDiagnosticMail;
use App\Modules\Notifications\Models\Alert;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Outbound shopper emails, diagnostic emails, owner alerts, and WhatsApp order update links.
 * FR-NTF-01, FR-NTF-02, FR-NTF-03, FR-NTF-04, FR-NTF-05
 */
class NotificationService
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * Send a bilingual store-branded email for an order lifecycle event when the order has an email.
     * FR-NTF-01, FR-NTF-02
     */
    public function notifyShopperOrderEvent(Order $order, string $event, ?string $locale = null): bool
    {
        $email = is_string($order->email) ? trim($order->email) : '';
        if ($email === '') {
            return false;
        }

        $store = $order->store ?? $this->currentStore->getOrFail();
        $resolvedLocale = $locale ?? $store->default_locale ?? 'ar';

        if ($event === 'shipped') {
            Mail::to($email)->send(new OrderShippedMail($order, $store, $resolvedLocale));

            return true;
        }

        if (in_array($event, OrderConfirmationMail::EVENTS, true)) {
            Mail::to($email)->send(new OrderConfirmationMail($order, $store, $event, $resolvedLocale));

            return true;
        }

        throw new InvalidArgumentException("Unsupported order notification event: {$event}");
    }

    /**
     * Send a diagnostic test email and record the action in the activity log. FR-NTF-03
     */
    public function sendDiagnosticEmail(string $toEmail, Store $store, User $staff): void
    {
        $sentAt = Carbon::now();

        Mail::to($toEmail)->send(new TestDiagnosticMail($store, $staff, $sentAt));

        $this->activityLogger->log(
            action: 'notifications.diagnostic_email_sent',
            subject: $store,
            properties: [
                'recipient' => $toEmail,
                'mailer' => (string) config('mail.default', 'log'),
                'from_address' => (string) config('mail.from.address', 'hello@example.com'),
                'sent_at' => $sentAt->toIso8601String(),
            ],
            actor: Actor::user($staff->id),
            storeId: $store->id,
        );
    }

    /**
     * Create a store-scoped in-dashboard alert and optionally email store owners. FR-NTF-04
     *
     * @param  array<string, mixed>  $meta
     */
    public function notifyOwner(
        string $type,
        string $title,
        string $body,
        array $meta = [],
        string $severity = 'info',
        ?string $actionUrl = null,
        bool $emailOwner = true,
    ): Alert {
        if (! in_array($type, Alert::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported alert type: {$type}");
        }

        if (! in_array($severity, Alert::SEVERITIES, true)) {
            throw new InvalidArgumentException("Unsupported alert severity: {$severity}");
        }

        $store = $this->currentStore->getOrFail();

        /** @var Alert $alert */
        $alert = Alert::query()->create([
            'store_id' => $store->id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'meta' => $meta === [] ? null : $meta,
        ]);

        if ($emailOwner) {
            $ownerEmails = $store->members()
                ->where('role', Role::Owner->value)
                ->with('user')
                ->get()
                ->map(fn (StoreMember $member) => $member->user?->email)
                ->filter(fn (?string $email) => is_string($email) && trim($email) !== '')
                ->unique()
                ->values();

            foreach ($ownerEmails as $ownerEmail) {
                Mail::to($ownerEmail)->send(new OwnerAlertMail($alert, $store, $store->default_locale ?? 'ar'));
            }
        }

        return $alert;
    }

    /**
     * Build a click-to-WhatsApp URL (https://wa.me/20...) with a prefilled bilingual order update message.
     * FR-NTF-05
     */
    public function whatsappUrl(Order $order, string $locale = 'ar'): ?string
    {
        $normalizedPhone = $this->normalizeEgyptianPhone($order->phone);
        if ($normalizedPhone === null) {
            return null;
        }

        $isArabic = $locale !== 'en';
        $storeName = $order->store?->name ?? $this->currentStore->get()?->name ?? '';
        $statusLabel = $this->statusLabel($order->status, $isArabic);
        $formattedTotal = MoneyFormatter::format($order->total, $isArabic ? 'ar' : 'en');

        if ($isArabic) {
            $lines = [
                "مرحباً {$order->customer_name}،",
                trim("تحديث بخصوص طلبك #{$order->number}".($storeName !== '' ? " من {$storeName}" : '').':'),
                "الحالة: {$statusLabel}",
                "الإجمالي: {$formattedTotal}",
            ];
            if ($order->tracking_url !== null && $order->tracking_url !== '') {
                $lines[] = "رابط التتبع: {$order->tracking_url}";
            }
        } else {
            $lines = [
                "Hello {$order->customer_name},",
                trim("Update regarding your order #{$order->number}".($storeName !== '' ? " from {$storeName}" : '').':'),
                "Status: {$statusLabel}",
                "Total: {$formattedTotal}",
            ];
            if ($order->tracking_url !== null && $order->tracking_url !== '') {
                $lines[] = "Tracking link: {$order->tracking_url}";
            }
        }

        $message = implode("\n", $lines);

        return 'https://wa.me/'.$normalizedPhone.'?text='.rawurlencode($message);
    }

    private function normalizeEgyptianPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0020')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '20')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '20'.substr($digits, 1);
        }

        return '20'.$digits;
    }

    private function statusLabel(OrderStatus $status, bool $isArabic): string
    {
        if ($isArabic) {
            return match ($status) {
                OrderStatus::Pending => 'قيد الانتظار',
                OrderStatus::Paid => 'مدفوع',
                OrderStatus::Processing => 'جاري التجهيز',
                OrderStatus::Shipped => 'تم الشحن',
                OrderStatus::Delivered => 'تم التسليم',
                OrderStatus::Cancelled => 'ملغي',
                OrderStatus::Refunded => 'مسترد',
                OrderStatus::Returned => 'مرتجع',
            };
        }

        return match ($status) {
            OrderStatus::Pending => 'Pending',
            OrderStatus::Paid => 'Paid',
            OrderStatus::Processing => 'Processing',
            OrderStatus::Shipped => 'Shipped',
            OrderStatus::Delivered => 'Delivered',
            OrderStatus::Cancelled => 'Cancelled',
            OrderStatus::Refunded => 'Refunded',
            OrderStatus::Returned => 'Returned',
        };
    }
}
