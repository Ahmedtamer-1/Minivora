<?php

namespace App\Modules\Checkout\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Captured checkout lead for abandoned-checkout recovery. FR-PAY-10
 *
 * @property int $id
 * @property int $store_id
 * @property string $phone
 * @property string|null $name
 * @property string|null $email
 * @property string|null $governorate
 * @property array<string, mixed>|null $cart_snapshot
 * @property int|null $converted_order_id
 * @property Carbon $captured_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Lead extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'phone',
        'name',
        'email',
        'governorate',
        'cart_snapshot',
        'converted_order_id',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'cart_snapshot' => 'array',
            'converted_order_id' => 'integer',
            'captured_at' => 'datetime',
        ];
    }
}
