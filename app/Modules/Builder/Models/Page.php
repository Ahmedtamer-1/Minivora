<?php

namespace App\Modules\Builder\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A store page composed of ordered sections, with draft/published lifecycle and version history.
 * FR-BLD-01, FR-BLD-02, FR-BLD-06, FR-BLD-07
 *
 * @property int $id
 * @property int $store_id
 * @property string $slug
 * @property string $title
 * @property string|null $title_ar
 * @property bool $is_home
 * @property string $status
 * @property bool $is_ai_draft
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Section> $sections
 * @property-read Collection<int, PageVersion> $versions
 */
class Page extends Model
{
    use BelongsToStore;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'slug',
        'title',
        'title_ar',
        'is_home',
        'status',
        'is_ai_draft',
        'published_at',
    ];

    protected $attributes = [
        'is_home' => false,
        'status' => self::STATUS_DRAFT,
        'is_ai_draft' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_home' => 'boolean',
            'is_ai_draft' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<PageVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class)->orderByDesc('version_number');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
