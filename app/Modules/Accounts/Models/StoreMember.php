<?php

namespace App\Modules\Accounts\Models;

use App\Core\Permissions\Role;
use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's membership of a store, with a role. FR-ACC-03
 *
 * @property int $id
 * @property int $store_id
 * @property int $user_id
 * @property Role $role
 * @property-read User $user
 */
class StoreMember extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'user_id', 'role'];

    protected function casts(): array
    {
        return ['role' => Role::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
