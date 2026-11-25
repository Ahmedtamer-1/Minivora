<?php

namespace App\Modules\Checkout\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Payment attempt and settlement record for an order. FR-PAY-04..09, NFR-OPS-03
 *
 * @property int $id
 * @property int $store_id
 * @property int $order_id
 * @property string $gateway
 * @property string $mode
 * @property string $status
 * @property int $amount
 * @property int $gateway_fee
 * @property int $cod_fee
 * @property string|null $external_reference
 * @property string|null $webhook_event_id
 * @property string|null $receipt_path
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Payment extends Model
{
    use BelongsToStore;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const MODE_TEST = 'test';

    protected $fillable = [
        'order_id',
        'gateway',
        'mode',
        'status',
        'amount',
        'gateway_fee',
        'cod_fee',
        'external_reference',
        'webhook_event_id',
        'receipt_path',
        'payload',
        'paid_at',
    ];

    protected $attributes = [
        'mode' => self::MODE_TEST,
        'status' => self::STATUS_PENDING,
        'gateway_fee' => 0,
        'cod_fee' => 0,
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'amount' => 'integer',
            'gateway_fee' => 'integer',
            'cod_fee' => 'integer',
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
