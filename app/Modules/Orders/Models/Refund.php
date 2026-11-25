<?php

namespace App\Modules\Orders\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A full or partial refund issued for an order, optionally linked to a return request and ledger entry.
 * FR-ORD-01, FR-ORD-08
 *
 * @property int $id
 * @property int $store_id
 * @property int $order_id
 * @property int|null $return_request_id
 * @property int $amount
 * @property bool $is_partial
 * @property string|null $reason
 * @property int|null $journal_entry_id
 * @property int|null $issued_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Refund extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'order_id', 'return_request_id', 'amount', 'is_partial', 'reason', 'journal_entry_id', 'issued_by',
    ];

    protected $attributes = [
        'is_partial' => false,
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'return_request_id' => 'integer',
            'amount' => 'integer',
            'is_partial' => 'boolean',
            'journal_entry_id' => 'integer',
            'issued_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<ReturnRequest, $this> */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
