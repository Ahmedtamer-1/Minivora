<?php

namespace App\Modules\Orders\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Money was given back to the shopper (Ledger posts the refund).
 * Carries a plain snapshot (Order::ledgerSnapshot()), so listeners in other modules never touch
 * the Orders models.
 */
class RefundIssued
{
    use Dispatchable;

    /** @param  array<string, mixed>  $order */
    public function __construct(public readonly array $order) {}
}
