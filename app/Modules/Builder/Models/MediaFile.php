<?php

namespace App\Modules\Builder\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Uploaded image or video asset in a store's media library with bilingual alt text.
 * FR-BLD-04, FR-SEO-13
 *
 * @property int $id
 * @property int $store_id
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $alt_en
 * @property string|null $alt_ar
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MediaFile extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'alt_en',
        'alt_ar',
    ];

    protected $attributes = [
        'disk' => 'public',
        'size_bytes' => 0,
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
