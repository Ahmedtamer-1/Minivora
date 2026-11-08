<?php

namespace App\Modules\Platform\Models;

use App\Core\Tenancy\StoreUrl;
use App\Modules\Accounts\Models\StoreMember;
use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Data\StoreProfileData;
use App\Modules\Platform\Enums\StoreStatus;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A store: name, subdomain, language, profile and lifecycle. Every other table points here via store_id.
 *
 * @property int $id
 * @property string $name
 * @property string $subdomain
 * @property string $default_locale
 * @property string|null $storefront_password
 * @property array<string, mixed>|null $profile
 * @property Carbon|null $onboarding_completed_at
 * @property Carbon|null $published_at
 * @property Carbon|null $suspended_at
 * @property string|null $suspended_reason
 * @property int|null $created_by
 * @property Carbon $created_at
 */
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'subdomain', 'default_locale', 'storefront_password', 'profile',
        'onboarding_completed_at', 'published_at', 'suspended_at', 'suspended_reason', 'created_by',
    ];

    protected $hidden = ['storefront_password'];

    /** Column defaults, so freshly created models match what the database holds. */
    protected $attributes = [
        'default_locale' => 'ar',
        'storefront_password' => null,
        'profile' => null,
        'onboarding_completed_at' => null,
        'published_at' => null,
        'suspended_at' => null,
        'suspended_reason' => null,
        'created_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'profile' => 'array',
            'storefront_password' => 'hashed',
            'onboarding_completed_at' => 'datetime',
            'published_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    protected static function newFactory(): StoreFactory
    {
        return StoreFactory::new();
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_members')->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<StoreMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(StoreMember::class)->withoutGlobalScopes();
    }

    /** @return HasMany<StoreDomain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

    public function status(): StoreStatus
    {
        return match (true) {
            $this->suspended_at !== null => StoreStatus::Suspended,
            $this->published_at !== null => StoreStatus::Live,
            default => StoreStatus::OpeningSoon,
        };
    }

    public function isLive(): bool
    {
        return $this->status() === StoreStatus::Live;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function profileData(): StoreProfileData
    {
        return StoreProfileData::from($this->profile ?? []);
    }

    public function url(string $path = '/'): string
    {
        return StoreUrl::to($this->subdomain, $path);
    }

    public function host(): string
    {
        return StoreUrl::host($this->subdomain);
    }
}
