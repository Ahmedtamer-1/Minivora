<?php

namespace App\Modules\Checkout\Gateways;

use App\Modules\Checkout\Models\Payment;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;

/**
 * Contract for all payment gateways (test mode only, NFR-OPS-03). FR-PAY-04..09
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Initiate a payment for an order and persist the pending Payment record.
     *
     * @param  array<string, mixed>  $options
     */
    public function initiate(Order $order, array $options = []): Payment;

    /**
     * Verify the incoming webhook's HMAC signature using constant-time comparison.
     */
    public function verifyWebhook(Request $request, string $secret): bool;

    /**
     * Parse a verified webhook payload into a normalized array.
     *
     * @return array{
     *     event_id: string|null,
     *     external_reference: string|null,
     *     order_id: int|null,
     *     paid: bool,
     *     amount: int,
     *     gateway_fee: int
     * }
     */
    public function parseWebhook(Request $request): array;
}
