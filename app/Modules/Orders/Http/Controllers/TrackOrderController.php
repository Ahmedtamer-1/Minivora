<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderStatusHistory;
use App\Modules\Orders\Services\OrderService;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Builder\Storefront\StorefrontView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

/**
 * Storefront: public order tracking by order number + phone or email. Internal notes are never exposed.
 * FR-ORD-05, FR-ORD-07
 */
class TrackOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /** Track-order page (screen S8); the form calls lookup() below. */
    public function page(Request $request, CurrentStore $current, StorefrontView $view): Response
    {
        $store = $current->getOrFail();
        $sf = $view->context($store, StorefrontView::locale($request, $store), false, 'track');

        return $view->make($sf, 'storefront.pages.track-order', ['number' => (string) $request->query('order', '')]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:20'],
            'contact' => ['required', 'string', 'max:255'],
        ]);

        $rawNumber = trim(ltrim($data['order_number'], '#'));
        if ($rawNumber === '' || ! ctype_digit($rawNumber)) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $order = Order::query()
            ->with(['items', 'history'])
            ->where('number', (int) $rawNumber)
            ->first();

        if ($order === null || ! $this->contactMatches($order, $data['contact'])) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json([
            'order' => [
                'number' => $order->number,
                'status' => $order->status->value,
                'placed_at' => ($order->placed_at ?? $order->created_at)?->toIso8601String(),
                'courier_name' => $order->courier_name,
                'tracking_url' => $order->tracking_url,
                'subtotal' => $order->subtotal,
                'shipping_fee' => $order->shipping_fee,
                'discount' => $order->discount,
                'total' => $order->total,
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'name' => $item->name,
                    'variant' => $item->variant,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ])->values()->all(),
                'history' => $order->history->map(fn (OrderStatusHistory $h) => [
                    'from' => $h->from_status?->value,
                    'to' => $h->to_status->value,
                    'at' => $h->created_at->toIso8601String(),
                ])->values()->all(),
            ],
        ]);
    }

    private function contactMatches(Order $order, string $contact): bool
    {
        $trimmed = trim($contact);
        if ($trimmed === '') {
            return false;
        }

        if ($order->email !== null && $order->email !== '' && mb_strtolower($trimmed) === mb_strtolower(trim($order->email))) {
            return true;
        }

        $normalised = $this->orders->normalisePhone($trimmed);
        if ($normalised !== null && $order->phone !== null && $order->phone !== '' && $normalised === $order->phone) {
            return true;
        }

        return false;
    }
}
