<?php

namespace App\Modules\Accounts\Models;

use App\Core\Permissions\Permission;
use App\Core\Permissions\PermissionMatrix;
use App\Core\Permissions\Role;
use App\Modules\Accounts\Notifications\VerifyEmailQueued;
use App\Modules\Platform\Models\Store;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A person who signs in to the dashboard. Belongs to stores through store_members with a role.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $locale
 * @property bool $is_platform_admin
 * @property Carbon|null $email_verified_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'locale'];

    /** Column defaults, so freshly created models match what the database holds. */
    protected $attributes = [
        'locale' => 'ar',
        'is_platform_admin' => false,
        'email_verified_at' => null,
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /** Verification goes out on the queue, never blocking store creation. FR-ONB-07 */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailQueued);
    }

    /** @return BelongsToMany<Store, $this> */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_members')->withPivot('role')->withTimestamps();
    }

    public function roleIn(Store|int $store): ?Role
    {
        $storeId = $store instanceof Store ? $store->getKey() : $store;

        $role = StoreMember::withoutStoreScope()
            ->where('store_id', $storeId)
            ->where('user_id', $this->getKey())
            ->value('role');

        return $role instanceof Role || $role === null ? $role : Role::from($role);
    }

    public function canIn(Store|int $store, Permission $permission): bool
    {
        $role = $this->roleIn($store);

        return $role !== null && PermissionMatrix::allows($role, $permission);
    }
}
