<?php

namespace App\Core\Console;

use App\Modules\Accounts\Models\User;
use Illuminate\Console\Command;

/**
 * Marks an account's email as verified without sending mail. For hosts where outgoing email is
 * not set up yet; run from the "Verify an email" GitHub Actions workflow or on the server.
 */
class VerifyUserEmail extends Command
{
    protected $signature = 'user:verify-email {email : The account\'s email address}';

    protected $description = 'Mark an account\'s email address as verified';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error('No account uses that email address.');

            return self::FAILURE;
        }

        if ($user->email_verified_at !== null) {
            $this->info('Already verified.');

            return self::SUCCESS;
        }

        $user->markEmailAsVerified();
        $this->info('Verified. The owner can now publish their store.');

        return self::SUCCESS;
    }
}
