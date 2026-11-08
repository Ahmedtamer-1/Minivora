<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Catalog\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Product: AR/EN name and description, price, compare-at price, unit cost, stock, status. FR-CAT-01
 * Money columns hold integer piasters.
 *
 * @property int $id
 * @property int $store_id
 * @property string $name
 * @property string|null $name_ar
 * @property string|null $description
 * @property string|null $category
 * @property int $price
 * @property int|null $compare_at_price
 * @property int|null $cost
 * @property int $stock
 * @property ProductStatus $status
 * @property bool $has_options
 * @property list<array{name: string, hex: string}>|null $colors
 * @property list<string>|null $images
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string $source
 * @property int $sold_count
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 */
class Product extends Model
{
    use BelongsToStore;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** At or below this many in stock, a product counts as running low. */
    public const LOW_STOCK = 5;

    protected $fillable = [
        'name', 'name_ar', 'description', 'category', 'price', 'compare_at_price', 'cost', 'stock',
        'status', 'has_options', 'colors', 'images', 'seo_title', 'seo_description', 'source',
    ];

    protected $attributes = [
        'status' => 'draft',
        'source' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'cost' => 'integer',
            'stock' => 'integer',
            'sold_count' => 'integer',
            'status' => ProductStatus::class,
            'has_options' => 'boolean',
            'colors' => 'array',
            'images' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public function isLive(): bool
    {
        return $this->status === ProductStatus::Live;
    }

    public function isSoldOut(): bool
    {
        return $this->stock === 0;
    }

    public function isRunningLow(): bool
    {
        return $this->stock > 0 && $this->stock <= self::LOW_STOCK;
    }

    /** @param  Builder<self>  $query */
    public function scopeRunningLow(Builder $query): void
    {
        $query->where('stock', '>', 0)->where('stock', '<=', self::LOW_STOCK);
    }

    /** @param  Builder<self>  $query */
    public function scopeSoldOut(Builder $query): void
    {
        $query->where('stock', 0);
    }

    /** @return list<string> */
    public function imageUrls(): array
    {
        return array_map(fn (string $path) => Storage::disk('public')->url($path), $this->images ?? []);
    }

    /** Shape sent to the dashboard. */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'price' => $this->price,
            'stock' => $this->stock,
            'status' => $this->status->value,
            'cover' => $this->imageUrls()[0] ?? null,
        ];
    }
}
