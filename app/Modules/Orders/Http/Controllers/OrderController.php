<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Core\Money\Piasters;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Services\InventoryService;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderStatusHistory;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\ShippingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dashboard orders: list with filters and search, manual orders, detail with status moves,
 * courier, notes and tags, bulk status and CSV export. Orders are always looked up through the
 * store scope, so another store's id is a 404. FR-ORD-01..04, FR-ORD-07, FR-ORD-10, FR-ORD-11
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ShippingService $shipping,
        private readonly InventoryService $inventory,
    ) {}

    public function index(Request $request): Response
    {
        [$filter, $search, $channel] = $this->listParams($request);

        $page = $this->orders->query($filter, $search, $channel)
            ->with('items')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $o) => $o->toRow());

        return Inertia::render('orders/index', [
            'orders' => $page,
            'filter' => $filter,
            'search' => $search,
            'channel' => $channel,
            'counts' => $this->orders->counts(),
            'stats' => $this->orders->dashboard(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('orders/create', [
            'products' => $this->inventory->pickable('', 200),
            'rates' => $this->shipping->rates(),
            'governorates' => ShippingService::GOVERNORATES,
            'channels' => Order::CHANNELS,
            'paymentMethods' => Order::PAYMENT_METHODS,
            'prefill' => $request->only('phone', 'customer_name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9٠-٩+\s()-]{6,32}$/u'],
            'email' => ['nullable', 'email', 'max:255'],
            'governorate' => ['nullable', Rule::in(ShippingService::GOVERNORATES)],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'channel' => ['required', Rule::in(Order::CHANNELS)],
            'payment_method' => ['required', Rule::in(Order::PAYMENT_METHODS)],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'processing'])],
            'shipping_fee' => ['nullable', 'string', 'max:20'],
            'discount' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.unit_price' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $lines = array_map(fn (array $line, int $i) => [
            'product_id' => isset($line['product_id']) ? (int) $line['product_id'] : null,
            'name' => $line['name'] ?? null,
            'unit_price' => $this->piastersOrNull($line['unit_price'] ?? null, "items.{$i}.unit_price"),
            'quantity' => (int) $line['quantity'],
        ], $data['items'], array_keys($data['items']));

        $order = $this->orders->createManual([
            ...$data,
            'shipping_fee' => $this->piastersOrNull($data['shipping_fee'] ?? null, 'shipping_fee'),
            'discount' => $this->piastersOrNull($data['discount'] ?? null, 'discount') ?? 0,
            'tags' => $data['tags'] ?? [],
        ], $lines, $this->user($request));

        return redirect()->route('orders.show', $order->id)->with('success', __('orders.created', ['number' => $order->number]));
    }

    public function show(int $order): Response
    {
        $order = Order::query()->with(['items', 'history', 'customer'])->findOrFail($order);

        return Inertia::render('orders/show', [
            'order' => [
                ...$order->toRow(),
                'email' => $order->email,
                'city' => $order->city,
                'address' => $order->address,
                'subtotal' => $order->subtotal,
                'shipping_fee' => $order->shipping_fee,
                'discount' => $order->discount,
                'courier_name' => $order->courier_name,
                'tracking_url' => $order->tracking_url,
                'notes' => $order->notes,
                'paid' => $order->paid_at !== null,
                'next' => array_map(fn (OrderStatus $s) => $s->value, $order->status->next()),
                'items' => $order->items->map(fn (OrderItem $i) => [
                    'id' => $i->id,
                    'product_id' => $i->product_id,
                    'name' => $i->name,
                    'variant' => $i->variant,
                    'unit_price' => $i->unit_price,
                    'quantity' => $i->quantity,
                    'line_total' => $i->line_total,
                ])->values(),
                'history' => $order->history->map(fn (OrderStatusHistory $h) => [
                    'id' => $h->id,
                    'from' => $h->from_status?->value,
                    'to' => $h->to_status->value,
                    'actor_type' => $h->actor_type,
                    'actor_name' => $h->actor_name,
                    'note' => $h->note,
                    'at' => $h->created_at->toIso8601String(),
                ])->values(),
                'customer' => $order->customer?->toCard(),
            ],
        ]);
    }

    public function updateStatus(Request $request, int $order): RedirectResponse
    {
        $order = Order::query()->with(['items', 'customer'])->findOrFail($order);
        $data = $request->validate([
            'status' => ['required', Rule::in(OrderStatus::values())],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $to = OrderStatus::from($data['status']);
        $this->orders->changeStatus($order, $to, $this->user($request), $data['note'] ?? null);

        return back()->with('success', __('orders.moved', ['number' => $order->number, 'status' => __('orders.status_'.$to->value)]));
    }

    public function bulkStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'status' => ['required', Rule::in(OrderStatus::values())],
        ]);

        $result = $this->orders->bulkChangeStatus(array_map('intval', $data['ids']), OrderStatus::from($data['status']), $this->user($request));

        $message = trans_choice('orders.bulk_moved', $result['changed'], ['count' => $result['changed'], 'status' => __('orders.status_'.$data['status'])]);
        if ($result['skipped'] > 0) {
            $message .= ' '.trans_choice('orders.bulk_skipped', $result['skipped'], ['count' => $result['skipped']]);
        }

        return back()->with('success', $message);
    }

    public function updateFulfilment(Request $request, int $order): RedirectResponse
    {
        $order = Order::query()->findOrFail($order);
        $data = $request->validate([
            'courier_name' => ['nullable', 'string', 'max:80'],
            'tracking_url' => ['nullable', 'url:http,https', 'max:500'],
        ]);

        $this->orders->updateFulfilment($order, $data['courier_name'] ?? null, $data['tracking_url'] ?? null);

        return back()->with('success', __('orders.courier_saved'));
    }

    public function updateNotes(Request $request, int $order): RedirectResponse
    {
        $order = Order::query()->findOrFail($order);
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
        ]);

        $this->orders->updateNotes($order, $data['notes'] ?? null, $data['tags'] ?? []);

        return back()->with('success', __('orders.notes_saved'));
    }

    /** CSV with a UTF-8 BOM so Excel shows Arabic names correctly. FR-ORD-10 */
    public function export(Request $request): StreamedResponse
    {
        [$filter, $search, $channel] = $this->listParams($request);
        $rows = $this->orders->csvRows($filter, $search, $channel);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'orders-'.now('Africa/Cairo')->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: string, 1: string, 2: string|null} */
    private function listParams(Request $request): array
    {
        $filter = in_array($request->query('filter'), OrderService::FILTERS, true) ? (string) $request->query('filter') : 'all';
        $channel = in_array($request->query('channel'), Order::CHANNELS, true) ? (string) $request->query('channel') : null;

        return [$filter, trim((string) $request->query('q', '')), $channel];
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    private function piastersOrNull(?string $pounds, string $field): ?int
    {
        if ($pounds === null || trim($pounds) === '') {
            return null;
        }
        try {
            $amount = Piasters::fromPounds($pounds)->amount;
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([$field => __('products.invalid_amount')]);
        }
        if ($amount < 0) {
            throw ValidationException::withMessages([$field => __('products.invalid_amount')]);
        }

        return $amount;
    }
}
