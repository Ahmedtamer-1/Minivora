<?php

namespace App\Core\Permissions;

/**
 * Who can do what, copied from the SRS §7.2 table. Used by policies and the AI registry.
 * A "manage" grant implies the matching "view" / "request" grant.
 */
final class PermissionMatrix
{
    /** @var array<string, list<Permission>>|null */
    private static ?array $grants = null;

    /** @return array<string, list<Permission>> */
    private static function grants(): array
    {
        return self::$grants ??= [
            Role::Owner->value => Permission::cases(),
            Role::Manager->value => [
                Permission::DesignManage,
                Permission::ProductsManage, Permission::ProductsView,
                Permission::OrdersManage, Permission::OrdersView,
                Permission::RefundsManage, Permission::RefundsRequest, Permission::RefundsView,
                Permission::MarketingManage,
                Permission::LedgerView,
                Permission::BudgetsView,
                Permission::AiAskFinance,
                Permission::AiApprove,
            ],
            Role::Accountant->value => [
                Permission::ProductsView,
                Permission::OrdersView,
                Permission::RefundsView,
                Permission::LedgerManage, Permission::LedgerView,
                Permission::BudgetsManage, Permission::BudgetsView,
                Permission::AiAskFinance,
            ],
            Role::Staff->value => [
                Permission::ProductsView,
                Permission::OrdersManage, Permission::OrdersView,
                Permission::RefundsRequest,
            ],
        ];
    }

    public static function allows(Role $role, Permission $permission): bool
    {
        return in_array($permission, self::grants()[$role->value], true);
    }

    /** @return list<string> permission values, for sending to the dashboard. */
    public static function for(Role $role): array
    {
        return array_map(fn (Permission $p) => $p->value, self::grants()[$role->value]);
    }
}
