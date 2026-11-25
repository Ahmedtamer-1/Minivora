<?php

namespace App\Modules\Checkout\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Core\AuditLog\ActorType;
use App\Modules\Accounts\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Services\InventoryService;
use App\Modules\Checkout\Data\CheckoutData;
use App\Modules\Checkout\Gateways\CodGateway;
use App\Modules\Checkout\Gateways\FawryGateway;
use App\Modules\Checkout\Gateways\InstaPayGateway;
use App\Modules\Checkout\Gateways\KashierGateway;
use App\Modules\Checkout\Gateways\PaymentGateway;
use App\Modules\Checkout\Gateways\PaymobGateway;
use App\Modules\Checkout\Models\DiscountCode;
use App\Modules\Checkout\Models\Lead;
use App\Modules\Checkout\Models\Payment;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Single-page checkout orchestration: lead capture, order placement, discount application,
 * risk tagging, gateway initiation, idempotent webhooks, and InstaPay verification.
 * FR-PAY-01..12, FR-CRT-05, NFR-SEC-14, NFR-OPS-03
 */
class CheckoutService
{
    /** Maximum allowed age (seconds) for a signed webhook timestamp (NFR-SEC-14). */
    public const WEBHOOK_MAX_AGE_SECONDS = 300;

    public function __construct(
        private readonly CartService $carts,
        private readonly ShippingService $shipping,
        private readonly DiscountService $discounts,
        private readonly RiskService $risk,
        private readonly InventoryService $inventory,
        private readonly OrderService $orders,
        private readonly ActivityLogger $activity,
        private readonly CodGateway $cod,
        private readonly KashierGateway $kashier,
        private readonly InstaPayGateway $instapay,
        private readonly PaymobGateway $paymob,
        private readonly FawryGateway $fawry,
    ) {}

    /**
     * Capture or update an open checkout lead by normalized Egyptian phone. FR-PAY-10
     *
     * @param  array{
     *     name?: string|null,
     *     email?: string|null,
     *     governorate?: string|null,
     *     cart_snapshot?: array<string, mixed>|null
     * }  $extra
     */
    public function captureLead(string $phone, array $extra = []): Lead
    {
        $normalized = EgyptianPhone::normalize($phone);

        /** @var Lead|null $lead */
        $lead = Lead::query()
            ->where('phone', $normalized)
            ->whereNull('converted_order_id')
            ->latest('id')
            ->first();

        $attributes = [
            'phone' => $normalized,
            'captured_at' => Carbon::now(),
        ];

        foreach (['name', 'email', 'governorate', 'cart_snapshot'] as $field) {
            if (array_key_exists($field, $extra) && $extra[$field] !== null) {
                $attributes[$field] = $extra[$field];
            }
        }

        if ($lead !== null) {
            $lead->fill($attributes);
            $lead->save();

            return $lead->refresh();
        }

        return Lead::query()->create($attributes);
    }

    /**
     * Place an order from the shopper's cart, apply shipping/discounts/COD fee, deduct stock,
     * evaluate risk, convert any open lead, and initiate payment. FR-PAY-01..08, FR-PAY-11..12
     *
     * @return array{order: Order, payment: Payment, risk: array{flagged: bool, reasons: list<string>}}
     *
     * @throws ValidationException
     */
    public function placeOrder(CheckoutData $data, Cart $cart, ?string $ip = null): array
    {
        $normalizedPhone = EgyptianPhone::normalize($data->phone);
        $gateway = $this->gateway($data->payment_method);

        $this->carts->reconcile($cart);
        $cartItems = $cart->items()->get();

        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $productIds = $cartItems->pluck('product_id')->unique()->values()->all();
        $products = $this->inventory->snapshot($productIds);

        $items = [];
        $deduct = [];

        foreach ($cartItems as $cartItem) {
            $product = $products[$cartItem->product_id] ?? null;
            if ($product === null) {
                throw ValidationException::withMessages([
                    'cart' => 'One or more products in your cart are no longer available.',
                ]);
            }

            $qty = max(1, $cartItem->quantity);
            $unitPrice = (int) $product['price'];
            $items[] = [
                'product_id' => $product['id'],
                'name' => mb_substr((string) $product['name'], 0, 255),
                'unit_price' => $unitPrice,
                'unit_cost' => $product['cost'] ?? null,
                'quantity' => $qty,
                'line_total' => $unitPrice * $qty,
            ];
            $deduct[$product['id']] = ($deduct[$product['id']] ?? 0) + $qty;
        }

        $subtotal = array_sum(array_column($items, 'line_total'));

        $shippingFee = $this->shipping->feeFor($data->governorate);
        if (
            $data->free_shipping_threshold !== null
            && $data->free_shipping_threshold > 0
            && $subtotal >= $data->free_shipping_threshold
        ) {
            $shippingFee = 0;
        }

        $discountAmount = 0;
        /** @var DiscountCode|null $appliedDiscount */
        $appliedDiscount = null;
        $codeToApply = $data->discount_code ?? $cart->discount_code;

        if ($codeToApply !== null && trim($codeToApply) !== '') {
            $discountResult = $this->discounts->apply($codeToApply, $subtotal, $shippingFee);
            $appliedDiscount = $discountResult['discount_code'];
            $discountAmount = $discountResult['discount_amount'];
            if ($discountResult['free_shipping']) {
                $shippingFee = 0;
            }
        }

        $codFee = $data->payment_method === 'cod' ? max(0, $data->cod_fee) : 0;
        // Include COD fee in order shipping_fee so subtotal + shipping_fee - discount === total (balanced Ledger sale).
        $effectiveShipping = $shippingFee + $codFee;
        $total = max(0, $subtotal + $effectiveShipping - $discountAmount);
        $orderPaymentMethod = $this->mapOrderPaymentMethod($data->payment_method);

        $notes = ($ip !== null && trim($ip) !== '') ? '[ip:'.trim($ip).']' : null;

        return DB::transaction(function () use (
            $data,
            $cart,
            $normalizedPhone,
            $gateway,
            $items,
            $deduct,
            $subtotal,
            $effectiveShipping,
            $discountAmount,
            $total,
            $codFee,
            $orderPaymentMethod,
            $appliedDiscount,
            $notes,
            $ip,
        ): array {
            $this->inventory->deduct($deduct);

            $customer = $this->matchCustomer($data, $normalizedPhone);
            $now = Carbon::now();

            /** @var Order $order */
            $order = Order::query()->create([
                'number' => $this->nextOrderNumber(),
                'customer_id' => $customer->id,
                'status' => OrderStatus::Pending,
                'channel' => 'website',
                'payment_method' => $orderPaymentMethod,
                'customer_name' => trim($data->name),
                'phone' => $normalizedPhone,
                'email' => $data->email,
                'governorate' => $data->governorate,
                'address' => $data->address,
                'subtotal' => $subtotal,
                'shipping_fee' => $effectiveShipping,
                'discount' => $discountAmount,
                'total' => $total,
                'notes' => $notes,
                'tags' => [],
                'stock_deducted' => $deduct !== [],
                'placed_at' => $now,
            ]);

            $order->items()->createMany(array_map(
                fn (array $item) => $item + ['store_id' => $order->store_id],
                $items,
            ));

            $order->history()->create([
                'store_id' => $order->store_id,
                'from_status' => null,
                'to_status' => OrderStatus::Pending,
                'actor_type' => ActorType::System->value,
                'actor_id' => null,
                'actor_name' => null,
                'note' => 'Order placed via storefront checkout',
            ]);

            if ($appliedDiscount !== null) {
                $appliedDiscount->increment('uses_count');
            }

            Lead::query()
                ->where('phone', $normalizedPhone)
                ->whereNull('converted_order_id')
                ->update(['converted_order_id' => $order->id]);

            $risk = $this->risk->evaluate($normalizedPhone, $order->total, $ip, [], $order->id);
            if ($risk['flagged']) {
                $order->tags = ['risk_review'];
                $order->save();
            }

            $payment = $gateway->initiate($order, [
                'cod_fee' => $codFee,
                'receipt_path' => $data->receipt_path,
            ]);

            $this->refreshCustomer($customer);
            $this->activity->log('order.created', $order, [
                'number' => $order->number,
                'channel' => 'website',
                'gateway' => $gateway->name(),
                'total' => $order->total,
                'risk_flagged' => $risk['flagged'],
            ]);

            $cart->items()->delete();
            $cart->update(['discount_code' => null]);

            return [
                'order' => $order->fresh(['items']),
                'payment' => $payment->refresh(),
                'risk' => $risk,
            ];
        });
    }

    /**
     * Verify HMAC signature and process a gateway webhook idempotently.
     * FR-PAY-09, NFR-SEC-14, V-04
     *
     * @throws HttpException
     */
    public function handleWebhook(string $gatewayName, Request $request, string $secret): Payment
    {
        $gateway = $this->gateway($gatewayName);

        if (! $gateway->verifyWebhook($request, $secret)) {
            throw new HttpException(403, 'Invalid webhook signature.');
        }

        $parsed = $gateway->parseWebhook($request);
        $eventId = $parsed['event_id'];

        // Idempotency check 1: webhook_event_id already processed -> return existing payment with 0 writes.
        if ($eventId !== null && $eventId !== '') {
            /** @var Payment|null $existingByEvent */
            $existingByEvent = Payment::query()
                ->where('gateway', $gateway->name())
                ->where('webhook_event_id', $eventId)
                ->first();

            if ($existingByEvent !== null) {
                return $existingByEvent;
            }
        }

        $payment = $this->findPaymentForWebhook($gateway->name(), $parsed['external_reference'], $parsed['order_id']);
        if ($payment === null) {
            throw new HttpException(404, 'Payment not found.');
        }

        // Idempotency check 2: payment already paid -> return existing payment with 0 writes.
        if ($payment->isPaid()) {
            return $payment;
        }

        // NFR-SEC-14: reject unseen webhook with a signed timestamp older than 5 minutes.
        $this->assertFreshWebhookTimestamp($request);

        $transitionedToPaid = false;

        $payment = DB::transaction(function () use ($payment, $parsed, $eventId, $request, &$transitionedToPaid): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPaid()) {
                return $locked;
            }

            $existingPayload = is_array($locked->payload) ? $locked->payload : [];
            $webhookPayload = json_decode($request->getContent(), true);
            $mergedPayload = array_merge($existingPayload, [
                'webhook' => is_array($webhookPayload) ? $webhookPayload : $request->all(),
            ]);

            if ($parsed['paid']) {
                $locked->status = Payment::STATUS_PAID;
                $locked->paid_at = Carbon::now();
                $locked->gateway_fee = $parsed['gateway_fee'];
                $locked->webhook_event_id = $eventId;
                $locked->payload = $mergedPayload;
                $locked->save();
                $transitionedToPaid = true;
            } else {
                $locked->status = Payment::STATUS_FAILED;
                $locked->webhook_event_id = $eventId;
                $locked->payload = $mergedPayload;
                $locked->save();
            }

            return $locked->refresh();
        });

        if ($transitionedToPaid) {
            /** @var Order $order */
            $order = Order::query()->findOrFail($payment->order_id);
            if ($order->status === OrderStatus::Pending) {
                $this->orders->changeStatus($order, OrderStatus::Paid, null, "Paid via {$gateway->name()}");
            }
        }

        return $payment;
    }

    /**
     * Staff verification of an InstaPay receipt payment (idempotent). FR-PAY-06
     *
     * @throws ValidationException
     */
    public function verifyInstaPay(Payment $payment, User $staff): Payment
    {
        if ($payment->gateway !== $this->instapay->name()) {
            throw ValidationException::withMessages([
                'payment' => 'Only InstaPay payments can be manually verified.',
            ]);
        }

        if ($payment->isPaid()) {
            return $payment;
        }

        $payment = $this->instapay->verifyReceipt($payment);

        /** @var Order $order */
        $order = Order::query()->findOrFail($payment->order_id);
        if ($order->status === OrderStatus::Pending) {
            $this->orders->changeStatus($order, OrderStatus::Paid, $staff, 'InstaPay receipt verified');
        }

        return $payment->refresh();
    }

    public function gateway(string $name): PaymentGateway
    {
        return match (strtolower(trim($name))) {
            'cod' => $this->cod,
            'kashier' => $this->kashier,
            'instapay' => $this->instapay,
            'paymob' => $this->paymob,
            'fawry' => $this->fawry,
            default => throw new HttpException(404, "Unknown payment gateway: {$name}"),
        };
    }

    private function mapOrderPaymentMethod(string $gatewayMethod): string
    {
        return match ($gatewayMethod) {
            'cod' => 'cod',
            'instapay' => 'instapay',
            default => 'card',
        };
    }

    private function findPaymentForWebhook(string $gatewayName, ?string $externalReference, ?int $orderId): ?Payment
    {
        if ($externalReference !== null && $externalReference !== '') {
            $byRef = Payment::query()
                ->where('gateway', $gatewayName)
                ->where('external_reference', $externalReference)
                ->first();

            if ($byRef !== null) {
                return $byRef;
            }
        }

        if ($orderId !== null) {
            return Payment::query()
                ->where('gateway', $gatewayName)
                ->where('order_id', $orderId)
                ->latest('id')
                ->first();
        }

        return null;
    }

    private function assertFreshWebhookTimestamp(Request $request): void
    {
        $rawTimestamp = $request->header('X-Webhook-Timestamp');
        if ($rawTimestamp === null || $rawTimestamp === '') {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded) && isset($decoded['timestamp'])) {
                $rawTimestamp = (string) $decoded['timestamp'];
            }
        }

        if ($rawTimestamp === null || $rawTimestamp === '') {
            return;
        }

        $timestamp = ctype_digit($rawTimestamp)
            ? (int) $rawTimestamp
            : Carbon::parse($rawTimestamp)->timestamp;

        if (abs(Carbon::now()->timestamp - $timestamp) > self::WEBHOOK_MAX_AGE_SECONDS) {
            throw new HttpException(403, 'Webhook timestamp is too old.');
        }
    }

    private function matchCustomer(CheckoutData $data, string $normalizedPhone): Customer
    {
        $customer = Customer::query()->firstOrNew(['phone' => $normalizedPhone]);
        $customer->fill(array_filter([
            'name' => trim($data->name),
            'email' => $data->email,
            'governorate' => $data->governorate,
            'address' => $data->address,
        ], fn ($v) => $v !== null && $v !== ''));
        $customer->save();

        return $customer;
    }

    private function refreshCustomer(Customer $customer): void
    {
        $orders = Order::query()->where('customer_id', $customer->id);
        $customer->orders_count = (clone $orders)->count();
        $customer->total_spent = (int) (clone $orders)->countingAsSale()->sum('total');
        $last = (clone $orders)->max('placed_at');
        $customer->last_order_at = $last !== null ? Carbon::parse($last) : null;
        $customer->save();
    }

    private function nextOrderNumber(): int
    {
        $max = Order::query()->lockForUpdate()->max('number');

        return max(Order::FIRST_NUMBER, ((int) $max) + 1);
    }
}
