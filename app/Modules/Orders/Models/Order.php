<?php

namespace App\Modules\Orders\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Orders\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Order header: shopper and address snapshot, totals in piasters, status, channel, payment method,
 * courier, internal notes and tags. FR-ORD-01, FR-ORD-07, FR-ORD-11
 *
 * @property int $id
 * @property int $store_id
 * @property int $number
 * @property int|null $customer_id
 * @property OrderStatus $status
 * @property string $channel
 * @property string $payment_method
 * @property string $customer_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $governorate
 * @property string|null $city
 * @property string|null $address
 * @property int $subtotal
 * @property int $shipping_fee
 * @property int $discount
 * @property int $total
 * @property string|null $courier_name
 * @property string|null $tracking_url
 * @property string|null $notes
 * @property list<string>|null $tags
 * @property bool $stock_deducted
 * @property int|null $created_by
 * @property Carbon|null $placed_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $cancelled_at
 * @property Carbon $created_at
 */
class Order extends Model
{
    use BelongsToStore;

    public const CHANNELS = ['website', 'instagram', 'whatsapp', 'phone', 'other'];

    public const PAYMENT_METHODS = ['cod', 'card', 'wallet', 'instapay'];

    /** Order numbers start here in every store, so a new store's first order is #1001. */
    public const FIRST_NUMBER = 1001;

    protected $fillable = [
        'number', 'customer_id', 'status', 'channel', 'payment_method', 'customer_name', 'phone', 'email',
        'governorate', 'city', 'address', 'subtotal', 'shipping_fee', 'discount', 'total', 'courier_name',
        'tracking_url', 'notes', 'tags', 'stock_deducted', 'created_by', 'placed_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'channel' => 'website',
        'payment_method' => 'cod',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'shipping_fee' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'tags' => 'array',
            'stock_deducted' => 'boolean',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Orders that still need packing and handing to a courier.
     *
     * @param  Builder<self>  $query
     */
    public function scopeToShip(Builder $query): void
    {
        $query->whereIn('status', [OrderStatus::Paid, OrderStatus::Processing]);
    }

    /** @param  Builder<self>  $query */
    public function scopeCountingAsSale(Builder $query): void
    {
        $query->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Returned]);
    }

    public function itemsCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /** Row shape for the orders list. */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'channel' => $this->channel,
            'payment_method' => $this->payment_method,
            'customer_name' => $this->customer_name,
            'phone' => $this->phone,
            'governorate' => $this->governorate,
            'total' => $this->total,
            'items_count' => $this->relationLoaded('items') ? $this->itemsCount() : null,
            'tags' => $this->tags ?? [],
            'placed_at' => ($this->placed_at ?? $this->created_at)->toIso8601String(),
        ];
    }

    /**
     * What the Ledger needs to post this order (Documentation §5.12), as plain data so the Ledger
     * never reads Orders models. Dates are ISO strings; cost is the snapshot cost of the items.
     *
     * @return array{id: int, number: int, status: string, payment_method: string, subtotal: int, shipping_fee: int, discount: int, total: int, cost: int, paid_at: string|null, shipped_at: string|null, closed_at: string|null}
     */
    public function ledgerSnapshot(): array
    {
        $this->loadMissing('items');
        $closed = $this->status->isClosed() ? ($this->cancelled_at ?? $this->updated_at ?? Carbon::now()) : null;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'payment_method' => $this->payment_method,
            'subtotal' => $this->subtotal,
            'shipping_fee' => $this->shipping_fee,
            'discount' => $this->discount,
            'total' => $this->total,
            'cost' => (int) $this->items->sum(fn (OrderItem $i) => (int) ($i->unit_cost ?? 0) * $i->quantity),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'closed_at' => $closed?->toIso8601String(),
        ];
    }
}
