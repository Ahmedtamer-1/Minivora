<?php

namespace App\Modules\Orders\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One order line, with the name, price and unit cost as they were when the order was placed.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property string $name
 * @property string|null $variant
 * @property int $unit_price
 * @property int|null $unit_cost
 * @property int $quantity
 * @property int $line_total
 */
class OrderItem extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'product_id', 'name', 'variant', 'unit_price', 'unit_cost', 'quantity', 'line_total'];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'unit_price' => 'integer',
            'unit_cost' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
