<?php

namespace App\Modules\Checkout\Gateways;

use App\Modules\Checkout\Models\Payment;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;

/**
 * Cash on Delivery gateway with optional COD fee in piasters. FR-PAY-05
 */
class CodGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'cod';
    }

    public function initiate(Order $order, array $options = []): Payment
    {
        $codFee = max(0, (int) ($options['cod_fee'] ?? 0));
        $externalReference = sprintf('COD-%d-%d', $order->store_id, $order->number);

        return Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => $this->name(),
            'mode' => Payment::MODE_TEST,
            'status' => Payment::STATUS_PENDING,
            'amount' => $order->total,
            'gateway_fee' => 0,
            'cod_fee' => $codFee,
            'external_reference' => $externalReference,
            'payload' => [
                'cod_fee' => $codFee,
                'mode' => Payment::MODE_TEST,
            ],
        ]);
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
