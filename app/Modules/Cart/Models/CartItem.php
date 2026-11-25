<?php

namespace App\Modules\Cart\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single product (or variant) line inside a shopper cart. FR-CRT-02, FR-CRT-03
 * Money columns hold integer piasters.
 *
 * @property int $id
 * @property int $store_id
 * @property int $cart_id
 * @property int $product_id
 * @property int|null $variant_id
 * @property int $quantity
 * @property int $unit_price_snapshot
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Cart $cart
 */
class CartItem extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'quantity' => 'integer',
            'unit_price_snapshot' => 'integer',
        ];
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
