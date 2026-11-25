<?php

namespace App\Modules\Checkout\Gateways;

use App\Modules\Checkout\Models\Payment;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * InstaPay transfer gateway with receipt upload and manual staff verification. FR-PAY-06
 */
class InstaPayGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'instapay';
    }

    public function initiate(Order $order, array $options = []): Payment
    {
        $receiptPath = isset($options['receipt_path']) && trim((string) $options['receipt_path']) !== ''
            ? trim((string) $options['receipt_path'])
            : null;

        $externalReference = sprintf('IP-TEST-%d-%d', $order->store_id, $order->number);

        return Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => $this->name(),
            'mode' => Payment::MODE_TEST,
            'status' => Payment::STATUS_PENDING,
            'amount' => $order->total,
            'gateway_fee' => 0,
            'cod_fee' => 0,
            'external_reference' => $externalReference,
            'receipt_path' => $receiptPath,
            'payload' => [
                'receipt_path' => $receiptPath,
                'mode' => Payment::MODE_TEST,
            ],
        ]);
    }

    /**
     * Mark an InstaPay receipt payment as verified and paid (idempotent). FR-PAY-06
     */
    public function verifyReceipt(Payment $payment): Payment
    {
        if ($payment->isPaid()) {
            return $payment;
        }

        $payment->status = Payment::STATUS_PAID;
        $payment->paid_at ??= Carbon::now();
        $payment->save();

        return $payment->refresh();
    }

    public function verifyWebhook(Request $request, string $secret): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): array
    {
        return [
            'event_id' => null,
            'external_reference' => null,
            'order_id' => null,
            'paid' => false,
            'amount' => 0,
            'gateway_fee' => 0,
        ];
    }
}
