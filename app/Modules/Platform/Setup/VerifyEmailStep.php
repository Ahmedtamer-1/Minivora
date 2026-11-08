<?php

namespace App\Modules\Platform\Setup;

use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Models\Store;

class VerifyEmailStep implements SetupStep
{
    public function key(): string
    {
        return 'verify_email';
    }

    public function isComplete(Store $store): bool
    {
        $owner = $store->created_by !== null ? User::query()->find($store->created_by) : null;

        return $owner?->hasVerifiedEmail() ?? false;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function route(): ?string
    {
        return 'verification.notice';
    }
}
