<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Shopper inquiry submitted via the storefront contact form. FR-CAT-13
 *
 * @property int $id
 * @property int $store_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $message
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ContactMessage extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'message',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
