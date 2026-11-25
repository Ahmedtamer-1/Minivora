<?php

namespace App\Modules\Marketing\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Abandoned checkout record for WhatsApp recovery. FR-MKT-01
 * Money columns hold integer piasters.
 *
 * @property int $id
 * @property int $store_id
 * @property int|null $lead_id
 * @property string|null $customer_name
 * @property string $phone
 * @property list<array<string, mixed>> $items
 * @property int $subtotal
 * @property int|null $recovered_order_id
 * @property Carbon|null $reminded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AbandonedCheckout extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'lead_id',
        'customer_name',
        'phone',
        'items',
        'subtotal',
        'recovered_order_id',
        'reminded_at',
    ];

    protected $attributes = [
        'subtotal' => 0,
    ];

    protected function casts(): array
    {
        return [
            'lead_id' => 'integer',
            'items' => 'array',
            'subtotal' => 'integer',
            'recovered_order_id' => 'integer',
            'reminded_at' => 'datetime',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('recovered_order_id');
    }
}
