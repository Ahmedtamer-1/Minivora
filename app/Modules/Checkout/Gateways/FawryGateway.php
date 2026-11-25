<?php

namespace App\Modules\Checkout\Gateways;

use App\Modules\Checkout\Models\Payment;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Fawry reference-code payment gateway — strictly test mode (no live network calls). FR-PAY-08, NFR-OPS-03
 */
class FawryGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fawry';
    }

    public function initiate(Order $order, array $options = []): Payment
    {
        $hash = abs(crc32(sprintf('fawry-test-%d-%d', $order->store_id, $order->number)));
        $referenceCode = '9'.str_pad((string) ($hash % 1000000000), 9, '0', STR_PAD_LEFT);

        return Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => $this->name(),
            'mode' => Payment::MODE_TEST,
            'status' => Payment::STATUS_PENDING,
            'amount' => $order->total,
            'gateway_fee' => 0,
            'cod_fee' => 0,
            'external_reference' => $referenceCode,
            'payload' => [
                'reference_code' => $referenceCode,
                'expires_at' => Carbon::now()->addHours(48)->toIso8601String(),
                'mode' => Payment::MODE_TEST,
            ],
        ]);
    }

    public function verifyWebhook(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Fawry-Signature', '');
        if ($signature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): array
    {
        $data = $this->decodePayload($request);
        $status = strtoupper((string) ($data['status'] ?? $data['orderStatus'] ?? ''));
        $paid = ($data['paid'] ?? false) === true || in_array($status, ['PAID', 'SUCCESS'], true);

        return [
            'event_id' => isset($data['event_id']) ? (string) $data['event_id'] : null,
            'external_reference' => isset($data['external_reference'])
                ? (string) $data['external_reference']
                : (isset($data['reference_code']) ? (string) $data['reference_code'] : null),
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
