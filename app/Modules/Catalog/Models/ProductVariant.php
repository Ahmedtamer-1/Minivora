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

    /** Option names in display order (other names follow, alphabetically). */
    public const OPTION_ORDER = ['Color', 'Size'];

    /** @return array<string, string> options in display order (databases may reorder JSON keys) */
    public function orderedOptions(): array
    {
        $options = array_map('strval', (array) $this->options);
        uksort($options, function ($a, $b) {
            $ia = array_search($a, self::OPTION_ORDER, true);
            $ib = array_search($b, self::OPTION_ORDER, true);

            return [$ia === false ? 99 : $ia, $a] <=> [$ib === false ? 99 : $ib, $b];
        });

        return $options;
    }

    /** "Black / M" */
    public function label(): string
    {
        return implode(' / ', array_values($this->orderedOptions()));
    }
}
