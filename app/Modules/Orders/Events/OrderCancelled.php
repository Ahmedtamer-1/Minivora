<?php

namespace App\Modules\Orders\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order was cancelled (Catalog restocks; Ledger reverses what was posted).
 * Carries a plain snapshot (Order::ledgerSnapshot()), so listeners in other modules never touch
 * the Orders models.
 */
class OrderCancelled
{
    use Dispatchable;

    /** @param  array<string, mixed>  $order */
    public function __construct(public readonly array $order) {}
}
