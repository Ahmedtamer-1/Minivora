<?php

namespace App\Modules\Checkout\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Money for an order was received: online payment captured, InstaPay verified or COD collected (Ledger posts the sale).
 * Carries a plain snapshot (Order::ledgerSnapshot()), so listeners in other modules never touch
 * the Orders models.
 */
class OrderPaid
{
    use Dispatchable;

    /** @param  array<string, mixed>  $order */
    public function __construct(public readonly array $order) {}
}
