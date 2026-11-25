<?php

namespace App\Modules\Marketing\Events;

use App\Modules\Marketing\Models\AdSpend;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when ad spend is recorded so the ledger can post the marketing expense.
 */
class AdSpendRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly AdSpend $adSpend) {}
}
