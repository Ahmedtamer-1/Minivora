<?php

namespace App\Modules\Ledger\Listeners;

use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Ledger\Services\OrderPosting;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderReturned;
use App\Modules\Orders\Events\OrderShipped;
use App\Modules\Orders\Events\RefundIssued;
use Throwable;

/**
 * Posts sales, fees, cost of goods, refunds and restocks as orders move (Documentation §5.12).
 * A ledger problem must never block an order: it is reported, and the month-close check and the
 * "catch up" button post whatever is missing.
 */
class PostOrderToLedger
{
    public function __construct(private readonly OrderPosting $posting) {}

    public function handle(OrderPaid|OrderShipped|OrderCancelled|OrderReturned|RefundIssued $event): void
    {
        try {
            $this->posting->sync($event->order);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
