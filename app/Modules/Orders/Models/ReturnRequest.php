<?php

namespace App\Modules\Orders\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A shopper or staff return/exchange request on a delivered order. FR-ORD-08
 *
 * @property int $id
 * @property int $store_id
 * @property int $order_id
 * @property string $type
 * @property string $reason
 * @property string $status
 * @property int $refund_amount
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ReturnRequest extends Model
{
    use BelongsToStore;

    public const TYPES = ['return', 'exchange'];

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'order_id', 'type', 'reason', 'status', 'refund_amount', 'reviewed_by', 'reviewed_at',
    ];

    protected $attributes = [
        'type' => 'return',
        'status' => 'pending',
        'refund_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'refund_amount' => 'integer',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasOne<Refund, $this> */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
