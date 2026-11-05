<?php

namespace App\Core\Permissions;

/**
 * Roles inside a store, SRS §7.2. Platform admin is a flag on the user, not a store role. FR-ACC-03
 */
enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Accountant = 'accountant';
    case Staff = 'staff';

    public function label(): string
    {
        return __('dashboard.roles.'.$this->value);
    }

    /** Roles an owner can hand out through an invitation. */
    public static function invitable(): array
    {
        return [self::Manager, self::Accountant, self::Staff];
    }
}
