<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Store category for organizing products. FR-CAT-03
 *
 * @property int $id
 * @property int $store_id
 * @property string $name
 * @property string|null $name_ar
 * @property string $slug
 * @property string|null $image
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Category extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'image',
        'sort_order',
    ];

    protected $attributes = [
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
