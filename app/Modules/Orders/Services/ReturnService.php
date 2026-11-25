<?php

namespace App\Modules\Orders\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Services\InventoryService;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Services\FinanceSettings;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Ledger\Services\PostingRules;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Refund;
use App\Modules\Orders\Models\ReturnRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Return requests, approvals/rejections, restocking and full/partial refunds posted to the Ledger.
 * Partial refunds post to the Ledger without changing the order status. FR-ORD-01, FR-ORD-03, FR-ORD-08
 */
class ReturnService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly InventoryService $inventory,
        private readonly LedgerService $ledger,
        private readonly FinanceSettings $financeSettings,
        private readonly ActivityLogger $activity,
    ) {}

    public function requestReturn(Order $order, string $type, string $reason, ?int $refundAmount = null): ReturnRequest
    {
        if ($order->status !== OrderStatus::Delivered) {
            throw ValidationException::withMessages([
                'order' => 'Return requests are only allowed on delivered orders.',
            ]);
        }

        $type = in_array($type, ReturnRequest::TYPES, true) ? $type : 'return';
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Please provide a reason for the return request.',
            ]);
        }

        $amount = $refundAmount ?? $order->total;
        if ($amount < 0 || $amount > $order->total) {
            throw ValidationException::withMessages([
                'refund_amount' => 'Refund amount must be between 0 and the order total.',
            ]);
        }

        $returnRequest = ReturnRequest::query()->create([
            'order_id' => $order->id,
            'type' => $type,
            'reason' => $reason,
            'status' => 'pending',
            'refund_amount' => $amount,
        ]);

        $this->activity->log('return.requested', $returnRequest, [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'type' => $type,
            'refund_amount' => $amount,
        ]);

        return $returnRequest;
    }

    public function approve(ReturnRequest $returnRequest, User $reviewer): ReturnRequest
    {
        if ($returnRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'return_request' => 'This return request has already been reviewed.',
            ]);
        }

        $order = $returnRequest->order()->with(['items', 'customer'])->firstOrFail();
        $refundAmount = min($returnRequest->refund_amount, $order->total);
        $isPartial = $refundAmount > 0 && $refundAmount < $order->total;

        DB::transaction(function () use ($returnRequest, $order, $reviewer, $refundAmount, $isPartial) {
            $returnRequest->status = 'approved';
            $returnRequest->reviewed_by = $reviewer->id;
            $returnRequest->reviewed_at = Carbon::now();
            $returnRequest->save();

            // Restock items if they were deducted and not yet restocked.
            if ($order->stock_deducted) {
                $quantities = $this->quantities($order);
                if ($quantities !== []) {
                    $this->inventory->restock($quantities);
                }
                $order->stock_deducted = false;
                $order->save();
            }

            if ($refundAmount > 0) {
                $refund = Refund::query()->create([
                    'order_id' => $order->id,
                    'return_request_id' => $returnRequest->id,
                    'amount' => $refundAmount,
                    'is_partial' => $isPartial,
                    'reason' => $returnRequest->reason,
                    'issued_by' => $reviewer->id,
                ]);

                // Full refund shares the order's refund source_key so OrderPosting::sync() never double-posts.
                $sourceKey = $isPartial ? "refund:{$refund->id}" : "order:{$order->id}:refund";
                $entry = $this->postRefundToLedger($order, $refundAmount, $sourceKey, $refund->id, $reviewer);
                $refund->journal_entry_id = $entry?->id;
                $refund->save();
            }

            $this->activity->log('return.approved', $returnRequest, [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'refund_amount' => $refundAmount,
                'is_partial' => $isPartial,
            ]);
        });

        // Full refund (or zero-refund full return) transitions the order status; partial refund leaves it unchanged (FR-ORD-01).
        if (! $isPartial && $order->status->canMoveTo(OrderStatus::Returned)) {
            $this->orders->changeStatus($order->fresh(['items', 'customer']), OrderStatus::Returned, $reviewer, $returnRequest->reason);
        }

        return $returnRequest->fresh(['order', 'refund']);
    }

    /**
     * Issue a partial refund and post it to the Ledger without changing the order status. FR-ORD-01
     */
    public function issuePartialRefund(Order $order, int $amount, string $reason, User $issuer): Refund
    {
        if ($amount <= 0 || $amount >= $order->total) {
            throw ValidationException::withMessages([
                'amount' => 'Partial refund amount must be greater than zero and less than the order total.',
            ]);
        }

        $alreadyRefunded = (int) Refund::query()->where('order_id', $order->id)->sum('amount');
        if ($alreadyRefunded + $amount > $order->total) {
            throw ValidationException::withMessages([
                'amount' => 'Total refunds cannot exceed the order total.',
            ]);
        }

        return DB::transaction(function () use ($order, $amount, $reason, $issuer) {
            $refund = Refund::query()->create([
                'order_id' => $order->id,
                'return_request_id' => null,
                'amount' => $amount,
                'is_partial' => true,
                'reason' => mb_substr(trim($reason), 0, 500) ?: null,
                'issued_by' => $issuer->id,
            ]);

            $entry = $this->postRefundToLedger($order, $amount, "refund:{$refund->id}", $refund->id, $issuer);
            $refund->journal_entry_id = $entry?->id;
            $refund->save();

            $this->activity->log('order.partial_refund_issued', $refund, [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'amount' => $amount,
            ]);

            return $refund;
        });
    }

    public function reject(ReturnRequest $returnRequest, User $reviewer): ReturnRequest
    {
        if ($returnRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'return_request' => 'This return request has already been reviewed.',
            ]);
        }

        $returnRequest->status = 'rejected';
        $returnRequest->reviewed_by = $reviewer->id;
        $returnRequest->reviewed_at = Carbon::now();
        $returnRequest->save();

        $this->activity->log('return.rejected', $returnRequest, [
            'order_id' => $returnRequest->order_id,
        ]);

        return $returnRequest;
    }

    private function postRefundToLedger(Order $order, int $amount, string $sourceKey, int $refundId, User $by): ?JournalEntry
    {
        $settings = $this->financeSettings->current();
        $lines = PostingRules::refund($amount, $order->payment_method, $settings);

        return $this->ledger->post(
            Carbon::now('Africa/Cairo')->startOfDay(),
            __('finance.memo_refund', ['number' => $order->number]),
            $lines,
            [
                'source_type' => 'refund',
                'source_id' => $refundId,
                'source_key' => $sourceKey,
                'by' => $by,
                'auto' => true,
            ],
        );
    }

    /** @return array<int, int> product id => quantity */
    private function quantities(Order $order): array
    {
        $quantities = [];
        foreach ($order->items as $item) {
            if ($item->product_id !== null) {
                $quantities[$item->product_id] = ($quantities[$item->product_id] ?? 0) + $item->quantity;
            }
        }

        return $quantities;
    }
}
