<?php

namespace App\Modules\Checkout\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Store discount code: percentage, fixed amount (piasters), or free shipping. FR-PAY-11, FR-CRT-05
 *
 * @property int $id
 * @property int $store_id
 * @property string $code
 * @property string $type
 * @property int $value
 * @property int $min_order_amount
 * @property int|null $max_uses
 * @property int $uses_count
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DiscountCode extends Model
{
    use BelongsToStore;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'uses_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $attributes = [
        'value' => 0,
        'min_order_amount' => 0,
        'uses_count' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_order_amount' => 'integer',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
