<?php

namespace App\Modules\Platform\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

/**
 * A seller's own domain (custom domains ship later, FR-SEO-21). Lookups by host happen before a store
 * is known, so host resolution uses withoutStoreScope().
 */
class StoreDomain extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'host', 'is_primary', 'verified_at'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'verified_at' => 'datetime'];
    }
}
