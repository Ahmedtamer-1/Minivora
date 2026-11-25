<?php

namespace App\Modules\Cart\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit/analytics event recorded whenever a cart item is added, updated, or removed. FR-CRT-02
 * Money columns hold integer piasters.
 *
 * @property int $id
 * @property int $store_id
 * @property int|null $cart_id
 * @property int $product_id
 * @property int|null $variant_id
 * @property string $event_type
 * @property int $delta
 * @property int $quantity_after
 * @property int $unit_price
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Cart|null $cart
 */
class CartEvent extends Model
{
    use BelongsToStore;

    public const TYPE_ADD = 'add';

    public const TYPE_QUANTITY_CHANGE = 'quantity_change';

    public const TYPE_REMOVE = 'remove';

    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'event_type',
        'delta',
        'quantity_after',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'delta' => 'integer',
            'quantity_after' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
