<?php

namespace App\Modules\Platform\Events;

use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once a store and its owner exist. Listeners: email verification, and later the ledger
 * (chart of accounts), builder (default theme) and the AI CEO's welcome.
 */
class StoreCreated
{
    use Dispatchable;

    public function __construct(public readonly Store $store, public readonly User $owner) {}
}
