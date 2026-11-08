<?php

namespace App\Modules\Orders\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

/**
 * Shipping rate and delivery days for one governorate. FR-ORD-06
 *
 * @property int $id
 * @property int $store_id
 * @property string $governorate
 * @property int $rate
 * @property int $delivery_days
 * @property int $sort_order
 */
class ShippingZone extends Model
{
    use BelongsToStore;

    protected $fillable = ['governorate', 'rate', 'delivery_days', 'sort_order'];

    protected function casts(): array
    {
        return ['rate' => 'integer', 'delivery_days' => 'integer', 'sort_order' => 'integer'];
    }
}
