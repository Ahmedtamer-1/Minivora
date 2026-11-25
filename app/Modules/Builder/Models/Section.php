<?php

namespace App\Modules\Builder\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One ordered section on a store page: section type, visibility, and JSON settings.
 * FR-BLD-01, FR-BLD-02
 *
 * @property int $id
 * @property int $store_id
 * @property int $page_id
 * @property string $type
 * @property int $sort_order
 * @property bool $is_visible
 * @property array<string, mixed>|null $settings
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Page $page
 */
class Section extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'page_id',
        'type',
        'sort_order',
        'is_visible',
        'settings',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'page_id' => 'integer',
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
