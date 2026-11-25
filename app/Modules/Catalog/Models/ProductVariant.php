<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Product variant (size/color/etc.) with its own SKU, price, cost, and stock. FR-CAT-02
 * Money columns hold integer piasters.
 *
 * @property int $id
 * @property int $store_id
 * @property int $product_id
 * @property string|null $sku
 * @property array<string, mixed> $options
 * @property int $price
 * @property int|null $cost
 * @property int $stock
 * @property list<string>|null $images
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ProductVariant extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'product_id',
        'sku',
        'options',
        'price',
        'cost',
        'stock',
        'images',
    ];

    protected $attributes = [
        'stock' => 0,
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'options' => 'array',
            'price' => 'integer',
            'cost' => 'integer',
            'stock' => 'integer',
            'images' => 'array',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
