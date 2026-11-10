<?php

namespace App\Modules\Orders\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order left with the courier (Ledger posts cost of goods; Notifications email).
 * Carries a plain snapshot (Order::ledgerSnapshot()), so listeners in other modules never touch
 * the Orders models.
 */
class OrderShipped
{
    use Dispatchable;

    /** @param  array<string, mixed>  $order */
    public function __construct(public readonly array $order) {}
}
