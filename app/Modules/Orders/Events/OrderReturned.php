<?php

namespace App\Modules\Orders\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A shipped order came back (Ledger restocks inventory at cost and refunds if it was paid).
 * Carries a plain snapshot (Order::ledgerSnapshot()), so listeners in other modules never touch
 * the Orders models.
 */
class OrderReturned
{
    use Dispatchable;

    /** @param  array<string, mixed>  $order */
    public function __construct(public readonly array $order) {}
}
