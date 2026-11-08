<?php

namespace Database\Factories;

use App\Core\Permissions\Role;
use App\Modules\Accounts\Models\StoreMember;
use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Store> */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'subdomain' => fake()->unique()->regexify('[a-z]{6}[0-9]{3}'),
            'default_locale' => 'en',
        ];
    }

    public function live(): static
    {
        return $this->state(fn () => ['published_at' => now()]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['suspended_at' => now(), 'suspended_reason' => 'test']);
    }

    /** Attach a user with a role (default owner). */
    public function withMember(User $user, Role $role = Role::Owner): static
    {
        return $this->afterCreating(function (Store $store) use ($user, $role) {
            StoreMember::withoutStoreScope()->create(['store_id' => $store->id, 'user_id' => $user->id, 'role' => $role]);
            if ($role === Role::Owner && $store->created_by === null) {
                $store->forceFill(['created_by' => $user->id])->save();
            }
        });
    }
}
