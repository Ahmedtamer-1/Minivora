<?php

namespace App\Modules\Accounts\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Email verification, sent in the background so it never slows store creation. FR-ONB-07
 */
class VerifyEmailQueued extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
