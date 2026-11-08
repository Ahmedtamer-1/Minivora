<?php

namespace App\Modules\Orders\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A shopper, matched by phone within a store. Totals are kept up to date by OrderService.
 *
 * @property int $id
 * @property int $store_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $governorate
 * @property string|null $city
 * @property string|null $address
 * @property list<string>|null $tags
 * @property string|null $notes
 * @property int $orders_count
 * @property int $total_spent
 * @property Carbon|null $last_order_at
 * @property Carbon $created_at
 */
class Customer extends Model
{
    use BelongsToStore;

    protected $fillable = ['name', 'phone', 'email', 'governorate', 'city', 'address', 'tags', 'notes'];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'orders_count' => 'integer',
            'total_spent' => 'integer',
            'last_order_at' => 'datetime',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Shape sent to the dashboard. */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'governorate' => $this->governorate,
            'city' => $this->city,
            'address' => $this->address,
            'tags' => $this->tags ?? [],
            'orders_count' => $this->orders_count,
            'total_spent' => $this->total_spent,
            'last_order_at' => $this->last_order_at?->toIso8601String(),
        ];
    }
}
