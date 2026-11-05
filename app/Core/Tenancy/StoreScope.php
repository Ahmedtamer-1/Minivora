<?php

namespace App\Core\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global query scope used by BelongsToStore: WHERE store_id = current store.
 * With no current store, queries return nothing rather than every store's rows (fail closed).
 * Code that truly needs all stores (platform admin, scheduled jobs) calls withoutStoreScope().
 */
class StoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $storeId = app(CurrentStore::class)->id();

        if ($storeId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('store_id'), $storeId);
    }
}
