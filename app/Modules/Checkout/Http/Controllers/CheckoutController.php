<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\StoreUrl;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Cart\Services\CartService;
use App\Modules\Checkout\Data\CheckoutData;
use App\Modules\Checkout\Models\Payment;
use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Storefront single-page checkout endpoints and dashboard InstaPay receipt verification.
 * FR-PAY-01..08, FR-PAY-10..12
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly CartService $carts,
        private readonly ShippingService $shipping,
        private readonly CurrentStore $current,
    ) {}

    public function show(Request $request): JsonResponse|View
    {
        $cart = $this->carts->resolve($request->user(), $request->session()->getId());
        $governorate = $request->string('governorate')->toString() ?: null;
        $threshold = $request->has('free_shipping_threshold')
            ? $request->integer('free_shipping_threshold')
            : null;
        $summary = $this->carts->summary($cart, $governorate, $threshold);

        if ($request->expectsJson()) {
            return response()->json([
                'cart' => $summary,
                'shipping_rates' => $this->shipping->rates(),
                'payment_methods' => CheckoutData::PAYMENT_METHODS,
            ]);
        }

        return view('storefront.pages.checkout', [
            'store' => $this->current->getOrFail(),
            'summary' => $summary,
            'zones' => $this->shipping->listZones(),
            'shippingRates' => $this->shipping->rates(),
        ]);
    }

    public function captureLead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'governorate' => ['nullable', 'string', 'max:40'],
            'cart_snapshot' => ['nullable', 'array'],
        ]);

        $lead = $this->checkout->captureLead($validated['phone'], [
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'governorate' => $validated['governorate'] ?? null,
            'cart_snapshot' => $validated['cart_snapshot'] ?? null,
        ]);

        return response()->json(['lead' => $lead], 201);
    }

    /** Session key listing the orders this browser placed, so only it can open their confirmation. */
    public const SESSION_ORDERS = 'checkout.placed_orders';

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = CheckoutData::validateAndCreate($request->all());
        $cart = $this->carts->resolve($request->user(), $request->session()->getId());

        $result = $this->checkout->placeOrder($data, $cart, $request->ip());

        if ($request->expectsJson()) {
            return response()->json($result, 201);
        }

        // Plain form post: remember the order for this browser and show the confirmation page. FR-NTF-01
        $placed = (array) $request->session()->get(self::SESSION_ORDERS, []);
        $placed[] = $result['order']->id;
        $request->session()->put(self::SESSION_ORDERS, array_slice($placed, -20));

        $store = $this->current->getOrFail();
        $locale = in_array($request->input('locale'), config('minivora.locales'), true) ? (string) $request->input('locale') : $store->default_locale;

        return redirect(StoreUrl::prefix($store->subdomain).'/'.$locale.'/orders/'.$result['order']->number.'/thank-you');
    }

    /** Order confirmation (screen S7), visible only to the browser that placed the order. */
    public function confirmation(Request $request, string $locale, int $number): View
    {
        $order = Order::query()->with('items')->where('number', $number)->firstOrFail();
        abort_unless(in_array($order->id, (array) $request->session()->get(self::SESSION_ORDERS, []), true), 404);

        $payment = Payment::query()->where('order_id', $order->id)->latest('id')->first();

        return view('storefront.pages.order-confirmation', [
            'store' => $this->current->getOrFail(),
            'order' => $order,
            'payment' => $payment,
            'instructions' => is_array($payment?->payload) ? $payment->payload : [],
        ]);
    }

    public function verifyInstaPay(Request $request, Payment $payment): JsonResponse
    {
        $staff = $request->user();
        abort_unless($staff instanceof User, 403);

        $verified = $this->checkout->verifyInstaPay($payment, $staff);

        return response()->json(['payment' => $verified]);
    }
}
