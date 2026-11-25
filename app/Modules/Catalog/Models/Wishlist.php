<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Shopper wishlist item, keyed by signed-in user or guest session. FR-CAT-08
 *
 * @property int $id
 * @property int $store_id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property int $product_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Wishlist extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'user_id',
        'session_id',
        'product_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'product_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
