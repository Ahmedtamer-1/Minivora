<?php

namespace App\Modules\Builder\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable published snapshot of a page and its sections for version history and restore.
 * FR-BLD-07
 *
 * @property int $id
 * @property int $store_id
 * @property int $page_id
 * @property int $version_number
 * @property array<string, mixed> $snapshot
 * @property int|null $published_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Page $page
 * @property-read User|null $publisher
 */
class PageVersion extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'page_id',
        'version_number',
        'snapshot',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'page_id' => 'integer',
            'version_number' => 'integer',
            'snapshot' => 'array',
            'published_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
