<?php

namespace App\Modules\Checkout\Gateways;

use App\Modules\Checkout\Models\Payment;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;

/**
 * Kashier payment gateway — strictly test mode (no live network calls). FR-PAY-04, NFR-OPS-03
 */
class KashierGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'kashier';
    }

    public function initiate(Order $order, array $options = []): Payment
    {
        $externalReference = sprintf('KSH-TEST-%d-%d', $order->store_id, $order->number);
        $checkoutUrl = sprintf(
            'https://checkout.kashier.io/test?merchantOrderId=%s&amount=%d&currency=EGP&mode=test',
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
        $signature = (string) $request->header('X-Kashier-Signature', '');
        if ($signature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): array
    {
        $data = $this->decodePayload($request);
        $status = strtoupper((string) ($data['status'] ?? $data['paymentStatus'] ?? ''));
        $paid = ($data['paid'] ?? false) === true || in_array($status, ['SUCCESS', 'PAID', 'CAPTURED'], true);

        return [
            'event_id' => isset($data['event_id']) ? (string) $data['event_id'] : null,
            'external_reference' => isset($data['external_reference'])
                ? (string) $data['external_reference']
                : (isset($data['merchantOrderId']) ? (string) $data['merchantOrderId'] : null),
            'order_id' => isset($data['order_id']) ? (int) $data['order_id'] : null,
            'paid' => $paid,
            'amount' => (int) ($data['amount'] ?? 0),
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
