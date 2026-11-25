<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Verified customer product review with moderation approval. FR-CAT-10, FR-CAT-18
 *
 * @property int $id
 * @property int $store_id
 * @property int $product_id
 * @property int|null $order_id
 * @property string $customer_name
 * @property int $rating
 * @property string|null $body
 * @property bool $approved
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Review extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'product_id',
        'order_id',
        'customer_name',
        'rating',
        'body',
        'approved',
        'approved_at',
    ];

    protected $attributes = [
        'approved' => false,
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'order_id' => 'integer',
            'rating' => 'integer',
            'approved' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeApproved(Builder $query): void
    {
        $query->where('approved', true);
    }
}
