<?php

namespace App\Modules\Platform\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

/**
 * Key-value settings per store (store_settings). Read and written through StoreSettingsService.
 *
 * @property string $key
 * @property mixed $value
 */
class StoreSetting extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
