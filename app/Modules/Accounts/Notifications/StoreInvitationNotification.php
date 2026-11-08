<?php

namespace App\Modules\Accounts\Notifications;

use App\Modules\Accounts\Models\Invitation;
use App\Modules\Platform\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation email with a one-time link. FR-ACC-04
 */
class StoreInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly Store $store,
        public readonly string $plainToken,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Sent in the store's language via ->locale() at dispatch time.
        $role = __('dashboard.roles.'.$this->invitation->role->value);

        return (new MailMessage)
            ->subject(__('emails.invitation.subject', ['store' => $this->store->name]))
            ->line(__('emails.invitation.body', ['store' => $this->store->name, 'role' => $role]))
            ->action(__('emails.invitation.action'), route('invitations.show', $this->plainToken))
            ->line(__('emails.invitation.expiry'));
    }
}
