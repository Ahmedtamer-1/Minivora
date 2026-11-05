<?php

namespace App\Core\Tenancy;

use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait for every store-owned model: adds the store_id filter to all queries and fills store_id on
 * create. NFR-SEC-05
 *
 * @method static Builder<static> withoutStoreScope()
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(function ($model): void {
            if ($model->getAttribute('store_id') === null) {
                $model->setAttribute('store_id', app(CurrentStore::class)->getOrFail()->getKey());
            }
        });
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @param  Builder<static>  $query */
    public function scopeWithoutStoreScope(Builder $query): void
    {
        $query->withoutGlobalScope(StoreScope::class);
    }
}
