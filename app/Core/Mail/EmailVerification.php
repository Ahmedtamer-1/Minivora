<?php

namespace App\Core\Mail;

/**
 * Whether accounts must confirm their email (config minivora.email_verification /
 * EMAIL_VERIFICATION):
 * - "on":   always required.
 * - "off":  never required; every account counts as verified.
 * - "auto" (default): required only when the server can actually send email, so a host with no
 *   outgoing mail set up never leaves owners stuck behind a link that can't arrive.
 */
final class EmailVerification
{
    public static function required(): bool
    {
        return match ((string) config('minivora.email_verification', 'auto')) {
            'on' => true,
            'off' => false,
            default => self::mailConfigured(),
        };
    }

    public static function mailConfigured(): bool
    {
        return match ((string) config('mail.default')) {
            'log', 'array', '' => false,
            'smtp' => trim((string) config('mail.mailers.smtp.host')) !== '',
            default => true, // ses, postmark, resend, mailgun, sendmail… are set up on purpose
        };
    }
}
