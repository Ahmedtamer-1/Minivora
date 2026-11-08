<?php

namespace App\Modules\Orders\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Core\AuditLog\Actor;
use App\Core\AuditLog\ActorType;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Services\InventoryService;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Notifications\OrderStatusChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Orders: manual orders, status moves and what they trigger (history, restock, shopper email),
 * bulk changes, courier, notes and tags, CSV export and the dashboard numbers. The dashboard and
 * (later) the AI team both go through here. FR-ORD-01..04, FR-ORD-07, FR-ORD-10, FR-ORD-11
 */
class OrderService
{
    public const FILTERS = ['all', 'pending', 'to_ship', 'shipped', 'delivered', 'closed'];

    public function __construct(
        private readonly CurrentStore $current,
        private readonly ActivityLogger $activity,
        private readonly InventoryService $inventory,
        private readonly ShippingService $shipping,
    ) {}

    /**
     * Orders matching a list filter and a search over number, name and phone.
     *
     * @return Builder<Order>
     */
    public function query(string $filter = 'all', string $search = '', ?string $channel = null): Builder
    {
        $query = Order::query()->latest('id');

        match ($filter) {
            'pending' => $query->where('status', OrderStatus::Pending),
            'to_ship' => $query->toShip(),
            'shipped' => $query->where('status', OrderStatus::Shipped),
            'delivered' => $query->where('status', OrderStatus::Delivered),
            'closed' => $query->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Returned]),
            default => null,
        };

        if ($channel !== null && in_array($channel, Order::CHANNELS, true)) {
            $query->where('channel', $channel);
        }

        $search = trim(ltrim($search, '#'));
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($search, $like) {
                $q->where('customer_name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like);
                if (ctype_digit($search)) {
                    $q->orWhere('number', (int) $search);
                }
            });
        }

        return $query;
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $byStatus = Order::query()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);
        $get = fn (OrderStatus ...$s) => array_sum(array_map(fn (OrderStatus $x) => $byStatus[$x->value] ?? 0, $s));

        return [
            'all' => $byStatus->sum(),
            'pending' => $get(OrderStatus::Pending),
            'to_ship' => $get(OrderStatus::Paid, OrderStatus::Processing),
            'shipped' => $get(OrderStatus::Shipped),
            'delivered' => $get(OrderStatus::Delivered),
            'closed' => $get(OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Returned),
        ];
    }

    /**
     * A sale taken on Instagram, WhatsApp, by phone or in person. FR-ORD-04
     * Matches the shopper by phone (or creates them), snapshots prices, takes stock off the shelf
     * and logs the first status.
     *
     * @param  array{
     *     customer_name: string, phone: string|null, email?: string|null, governorate?: string|null,
     *     city?: string|null, address?: string|null, channel: string, payment_method: string,
     *     shipping_fee?: int|null, discount?: int, notes?: string|null, tags?: list<string>,
     *     status?: string|null,
     * }  $data  money already in piasters
     * @param  list<array{product_id?: int|null, name?: string|null, unit_price?: int|null, quantity: int}>  $lines
     */
    public function createManual(array $data, array $lines, ?User $by = null): Order
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => __('orders.needs_items')]);
        }

        $products = $this->inventory->snapshot(array_values(array_filter(array_map(fn ($l) => $l['product_id'] ?? null, $lines))));

        $items = [];
        $deduct = [];
        foreach ($lines as $i => $line) {
            $qty = max(1, (int) $line['quantity']);
            $product = isset($line['product_id']) ? ($products[$line['product_id']] ?? null) : null;
            if (isset($line['product_id']) && $product === null) {
                throw ValidationException::withMessages(["items.{$i}.product_id" => __('orders.product_gone')]);
            }
            $name = $product['name'] ?? trim((string) ($line['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages(["items.{$i}.name" => __('orders.line_needs_name')]);
            }
            $unit = isset($line['unit_price']) ? (int) $line['unit_price'] : (int) ($product['price'] ?? 0);
            $items[] = [
                'product_id' => $product['id'] ?? null,
                'name' => mb_substr($name, 0, 255),
                'unit_price' => $unit,
                'unit_cost' => $product['cost'] ?? null,
                'quantity' => $qty,
                'line_total' => $unit * $qty,
            ];
            if ($product !== null) {
                $deduct[$product['id']] = ($deduct[$product['id']] ?? 0) + $qty;
            }
        }

        $subtotal = array_sum(array_column($items, 'line_total'));
        $shipping = $data['shipping_fee'] ?? $this->shipping->feeFor($data['governorate'] ?? null);
        $discount = min($subtotal + $shipping, max(0, (int) ($data['discount'] ?? 0)));
        $status = OrderStatus::tryFrom((string) ($data['status'] ?? '')) ?? OrderStatus::Pending;
        if (! in_array($status, [OrderStatus::Pending, OrderStatus::Paid, OrderStatus::Processing], true)) {
            $status = OrderStatus::Pending;
        }

        return DB::transaction(function () use ($data, $items, $deduct, $subtotal, $shipping, $discount, $status, $by) {
            $this->inventory->deduct($deduct);

            $customer = $this->matchCustomer($data);
            $now = Carbon::now();

            $order = Order::query()->create([
                'number' => $this->nextNumber(),
                'customer_id' => $customer?->id,
                'status' => $status,
                'channel' => $data['channel'],
                'payment_method' => $data['payment_method'],
                'customer_name' => trim($data['customer_name']),
                'phone' => isset($data['phone']) ? $this->normalisePhone((string) $data['phone']) : null,
                'email' => $data['email'] ?? null,
                'governorate' => $data['governorate'] ?? null,
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
                'subtotal' => $subtotal,
                'shipping_fee' => $shipping,
                'discount' => $discount,
                'total' => $subtotal + $shipping - $discount,
                'notes' => $data['notes'] ?? null,
                'tags' => $this->cleanTags($data['tags'] ?? []),
                'stock_deducted' => $deduct !== [],
                'created_by' => $by?->id,
                'placed_at' => $now,
            ]);
            if ($status === OrderStatus::Paid) {
                $order->paid_at = $now;
                $order->save();
            }

            $order->items()->createMany(array_map(fn ($item) => $item + ['store_id' => $order->store_id], $items));
            $this->record($order, null, $status, $by, __('orders.history_created_'.$order->channel));

            if ($customer !== null) {
                $this->refreshCustomer($customer);
            }
            $this->activity->log('order.created', $order, ['number' => $order->number, 'channel' => $order->channel, 'total' => $order->total]);

            return $order;
        });
    }

    /**
     * Move an order to a new status. Logs who did it, restocks on cancel/return and emails the
     * shopper on paid, shipped and cancelled. FR-ORD-01..03
     */
    public function changeStatus(Order $order, OrderStatus $to, ?User $by = null, ?string $note = null, ?Actor $actor = null): Order
    {
        $order->loadMissing(['items', 'customer']);
        $from = $order->status;
        if (! $from->canMoveTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('orders.cannot_move', ['from' => __('orders.status_'.$from->value), 'to' => __('orders.status_'.$to->value)]),
            ]);
        }

        DB::transaction(function () use ($order, $from, $to, $by, $note, $actor) {
            $now = Carbon::now();
            $order->status = $to;
            match ($to) {
                OrderStatus::Paid => $order->paid_at ??= $now,
                OrderStatus::Shipped => $order->shipped_at ??= $now,
                OrderStatus::Delivered => $order->delivered_at ??= $now,
                OrderStatus::Cancelled => $order->cancelled_at ??= $now,
                default => null,
            };
            // Cash on delivery is paid when the courier hands it over.
            if ($to === OrderStatus::Delivered && $order->payment_method === 'cod') {
                $order->paid_at ??= $now;
            }

            if ($to->restocks() && $order->stock_deducted) {
                $this->inventory->restock($this->quantities($order));
                $order->stock_deducted = false;
            }
            $order->save();

            $this->record($order, $from, $to, $by, $note, $actor);
            if ($order->customer !== null) {
                $this->refreshCustomer($order->customer);
            }
            $this->activity->log('order.status_changed', $order, ['number' => $order->number, 'from' => $from->value, 'to' => $to->value], $actor);
        });

        if ($to->emailsShopper() && $order->email !== null && $order->email !== '') {
            $store = $this->current->getOrFail();
            Notification::route('mail', $order->email)->notify(
                (new OrderStatusChanged($store->name, $order->number, $to, $order->tracking_url))->locale($store->default_locale),
            );
        }

        return $order;
    }

    /**
     * Move several orders at once. Orders that cannot make that move are skipped, not failed. FR-ORD-10
     *
     * @param  list<int>  $ids
     * @return array{changed: int, skipped: int}
     */
    public function bulkChangeStatus(array $ids, OrderStatus $to, ?User $by = null): array
    {
        $changed = 0;
        $skipped = 0;
        foreach (Order::query()->with(['items', 'customer'])->whereIn('id', $ids)->orderBy('id')->get() as $order) {
            if ($order->status->canMoveTo($to)) {
                $this->changeStatus($order, $to, $by);
                $changed++;
            } else {
                $skipped++;
            }
        }

        return ['changed' => $changed, 'skipped' => $skipped];
    }

    /** Courier and tracking link, shown on the shopper's tracking page. FR-ORD-07 */
    public function updateFulfilment(Order $order, ?string $courier, ?string $trackingUrl): Order
    {
        $order->courier_name = $courier !== null && trim($courier) !== '' ? trim($courier) : null;
        $order->tracking_url = $trackingUrl !== null && trim($trackingUrl) !== '' ? trim($trackingUrl) : null;
        $order->save();

        return $order;
    }

    /**
     * Internal notes and tags. FR-ORD-11
     *
     * @param  list<string>  $tags
     */
    public function updateNotes(Order $order, ?string $notes, array $tags): Order
    {
        $order->notes = $notes !== null && trim($notes) !== '' ? $notes : null;
        $order->tags = $this->cleanTags($tags);
        $order->save();

        return $order;
    }

    /**
     * CSV rows (header first) for the orders matching a filter. FR-ORD-10
     *
     * @return iterable<list<string|int>>
     */
    public function csvRows(string $filter = 'all', string $search = '', ?string $channel = null): iterable
    {
        yield ['number', 'date', 'status', 'channel', 'payment', 'customer', 'phone', 'email', 'governorate', 'city', 'address', 'items', 'subtotal', 'shipping', 'discount', 'total', 'courier', 'tracking', 'tags'];

        foreach ($this->query($filter, $search, $channel)->with('items')->lazyById(200) as $order) {
            /** @var Order $order */
            yield [
                $order->number,
                ($order->placed_at ?? $order->created_at)->timezone('Africa/Cairo')->format('Y-m-d H:i'),
                $order->status->value,
                $order->channel,
                $order->payment_method,
                $this->csvSafe($order->customer_name),
                $this->csvSafe((string) $order->phone),
                $this->csvSafe((string) $order->email),
                (string) $order->governorate,
                $this->csvSafe((string) $order->city),
                $this->csvSafe((string) $order->address),
                $this->csvSafe($order->items->map(fn ($i) => "{$i->quantity}× {$i->name}")->implode('; ')),
                $this->pounds($order->subtotal),
                $this->pounds($order->shipping_fee),
                $this->pounds($order->discount),
                $this->pounds($order->total),
                $this->csvSafe((string) $order->courier_name),
                $this->csvSafe((string) $order->tracking_url),
                $this->csvSafe(implode(', ', $order->tags ?? [])),
            ];
        }
    }

    /**
     * Today's sales against yesterday, and what is waiting to ship, for the dashboard home.
     *
     * @return array{sales: array{amount: int, orders: int, change: int|null}, toShip: array{count: int, needs_call: int}}
     */
    public function dashboard(): array
    {
        // Midnight in Cairo, expressed in the timezone the timestamps are stored in.
        $today = Carbon::now('Africa/Cairo')->startOfDay()->setTimezone((string) config('app.timezone'));
        $yesterday = $today->copy()->subDay();

        $todaySales = Order::query()->countingAsSale()->where('placed_at', '>=', $today);
        $amount = (int) (clone $todaySales)->sum('total');
        $before = (int) Order::query()->countingAsSale()->whereBetween('placed_at', [$yesterday, $today])->sum('total');

        return [
            'sales' => [
                'amount' => $amount,
                'orders' => (clone $todaySales)->count(),
                'change' => $before > 0 ? (int) round(($amount - $before) * 100 / $before) : null,
            ],
            'toShip' => [
                'count' => Order::query()->toShip()->count(),
                // Unconfirmed cash orders: call the shopper before packing.
                'needs_call' => Order::query()->where('status', OrderStatus::Pending)->where('payment_method', 'cod')->count(),
            ],
        ];
    }

    /** @return array<int, int> product id => quantity */
    private function quantities(Order $order): array
    {
        $quantities = [];
        foreach ($order->items as $item) {
            if ($item->product_id !== null) {
                $quantities[$item->product_id] = ($quantities[$item->product_id] ?? 0) + $item->quantity;
            }
        }

        return $quantities;
    }

    private function record(Order $order, ?OrderStatus $from, OrderStatus $to, ?User $by, ?string $note = null, ?Actor $actor = null): void
    {
        $by ??= auth()->user() instanceof User ? auth()->user() : null;
        $order->history()->create([
            'store_id' => $order->store_id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => ($actor?->type ?? ($by !== null ? ActorType::User : ActorType::System))->value,
            'actor_id' => $actor?->userId ?? $by?->id,
            'actor_name' => $actor?->name ?? $by?->name,
            'note' => $note !== null ? mb_substr($note, 0, 500) : null,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function matchCustomer(array $data): ?Customer
    {
        $phone = isset($data['phone']) ? $this->normalisePhone((string) $data['phone']) : null;
        if ($phone === null) {
            return null;
        }

        $customer = Customer::query()->firstOrNew(['phone' => $phone]);
        $customer->fill(array_filter([
            'name' => trim($data['customer_name']),
            'email' => $data['email'] ?? null,
            'governorate' => $data['governorate'] ?? null,
            'city' => $data['city'] ?? null,
            'address' => $data['address'] ?? null,
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

    public function normalisePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', strtr($phone, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
        if ($digits === null || $digits === '') {
            return null;
        }
        // +20 10... → 010...
        if (str_starts_with($digits, '20') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    private function nextNumber(): int
    {
        $max = Order::query()->lockForUpdate()->max('number');

        return max(Order::FIRST_NUMBER, ((int) $max) + 1);
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    private function cleanTags(array $tags): array
    {
        $clean = [];
        foreach ($tags as $tag) {
            $tag = mb_substr(trim((string) $tag), 0, 30);
            if ($tag !== '' && ! in_array(mb_strtolower($tag), array_map('mb_strtolower', $clean), true)) {
                $clean[] = $tag;
            }
        }

        return array_slice($clean, 0, 10);
    }

    private function pounds(int $piasters): string
    {
        return intdiv($piasters, 100).'.'.str_pad((string) ($piasters % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Stop spreadsheet apps from running a cell as a formula. */
    private function csvSafe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
