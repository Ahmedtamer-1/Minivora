<?php

namespace App\Modules\Cart\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A shopper cart bound to a guest session or registered user within a store. FR-CRT-01
 *
 * @property int $id
 * @property int $store_id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property string|null $discount_code
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, CartItem> $items
 * @property-read Collection<int, CartEvent> $events
 */
class Cart extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'user_id',
        'session_id',
        'discount_code',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /** @return HasMany<CartEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(CartEvent::class);
    }
}
