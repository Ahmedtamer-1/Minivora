<?php

namespace App\Modules\Checkout\Gateways;

use App\Modules\Checkout\Models\Payment;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;

/**
 * Paymob payment gateway — strictly test mode (no live network calls). FR-PAY-07, NFR-OPS-03
 */
class PaymobGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paymob';
    }

    public function initiate(Order $order, array $options = []): Payment
    {
        $externalReference = sprintf('PMB-TEST-%d-%d', $order->store_id, $order->number);
        $checkoutUrl = sprintf(
            'https://accept.paymob.com/api/acceptance/iframes/test?merchant_order_id=%s&amount_cents=%d&currency=EGP&mode=test',
            urlencode($externalReference),
            $order->total,
        );

        return Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => $this->name(),
            'mode' => Payment::MODE_TEST,
            'status' => Payment::STATUS_PENDING,
            'amount' => $order->total,
            'gateway_fee' => 0,
            'cod_fee' => 0,
            'external_reference' => $externalReference,
            'payload' => [
                'checkout_url' => $checkoutUrl,
                'currency' => 'EGP',
                'mode' => Payment::MODE_TEST,
            ],
        ]);
    }

    public function verifyWebhook(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Paymob-Signature', '');
        if ($signature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): array
    {
        $data = $this->decodePayload($request);
        $status = strtoupper((string) ($data['status'] ?? ''));
        $paid = ($data['paid'] ?? false) === true
            || ($data['success'] ?? false) === true
            || in_array($status, ['SUCCESS', 'PAID', 'CAPTURED'], true);

        return [
            'event_id' => isset($data['event_id']) ? (string) $data['event_id'] : null,
            'external_reference' => isset($data['external_reference'])
                ? (string) $data['external_reference']
                : (isset($data['merchant_order_id']) ? (string) $data['merchant_order_id'] : null),
            'order_id' => isset($data['order_id']) ? (int) $data['order_id'] : null,
            'paid' => $paid,
            'amount' => (int) ($data['amount'] ?? $data['amount_cents'] ?? 0),
            'gateway_fee' => max(0, (int) ($data['gateway_fee'] ?? 0)),
        ];
    }

    /** @return array<string, mixed> */
    private function decodePayload(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : $request->all();
    }
}
