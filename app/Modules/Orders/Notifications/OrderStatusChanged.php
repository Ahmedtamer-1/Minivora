<?php

namespace App\Modules\Orders\Notifications;

use App\Modules\Orders\Enums\OrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the shopper their order was paid, shipped or cancelled. FR-ORD-02
 * Sent in the store's language via ->locale() at dispatch time.
 */
class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $storeName,
        public readonly int $number,
        public readonly OrderStatus $status,
        public readonly ?string $trackingUrl = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = 'emails.order_'.$this->status->value;
        $mail = (new MailMessage)
            ->subject(__($key.'.subject', ['store' => $this->storeName, 'number' => $this->number]))
            ->line(__($key.'.body', ['store' => $this->storeName, 'number' => $this->number]));

        if ($this->status === OrderStatus::Shipped && $this->trackingUrl !== null) {
            $mail->action(__('emails.order_shipped.action'), $this->trackingUrl);
        }

        return $mail;
    }
}
