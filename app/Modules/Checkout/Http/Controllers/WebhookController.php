<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payment gateway webhook endpoint with HMAC verification and idempotent processing.
 * FR-PAY-09, NFR-SEC-14
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
    ) {}

    public function handle(Request $request, string $gateway): JsonResponse
    {
        $gatewayKey = strtolower(trim($gateway));
        $secret = (string) config("services.{$gatewayKey}.webhook_secret", 'test-webhook-secret');

        $payment = $this->checkout->handleWebhook($gatewayKey, $request, $secret);

        return response()->json([
            'status' => 'ok',
            'payment_id' => $payment->id,
            'payment_status' => $payment->status,
        ]);
    }
}
