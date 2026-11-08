<?php

use App\Core\Permissions\Permission;
use App\Core\Permissions\PermissionMatrix;
use App\Core\Permissions\Role;

test('FR-ACC-03 the permission matrix matches SRS section 7.2', function (Permission $permission, array $expected) {
    foreach (Role::cases() as $role) {
        expect(PermissionMatrix::allows($role, $permission))
            ->toBe(in_array($role, $expected, true), "{$role->value} / {$permission->value}");
    }
})->with([
    'design and publish' => [Permission::DesignManage, [Role::Owner, Role::Manager]],
    'products manage' => [Permission::ProductsManage, [Role::Owner, Role::Manager]],
    'products view' => [Permission::ProductsView, [Role::Owner, Role::Manager, Role::Accountant, Role::Staff]],
    'orders manage' => [Permission::OrdersManage, [Role::Owner, Role::Manager, Role::Staff]],
    'orders view' => [Permission::OrdersView, [Role::Owner, Role::Manager, Role::Accountant, Role::Staff]],
    'refunds manage' => [Permission::RefundsManage, [Role::Owner, Role::Manager]],
    'refunds request' => [Permission::RefundsRequest, [Role::Owner, Role::Manager, Role::Staff]],
    'marketing' => [Permission::MarketingManage, [Role::Owner, Role::Manager]],
    'ledger manage' => [Permission::LedgerManage, [Role::Owner, Role::Accountant]],
    'ledger view' => [Permission::LedgerView, [Role::Owner, Role::Manager, Role::Accountant]],
    'budgets manage' => [Permission::BudgetsManage, [Role::Owner, Role::Accountant]],
    'ask ai finance' => [Permission::AiAskFinance, [Role::Owner, Role::Manager, Role::Accountant]],
    'approve ai' => [Permission::AiApprove, [Role::Owner, Role::Manager]],
    'staff and roles' => [Permission::MembersManage, [Role::Owner]],
    'settings' => [Permission::SettingsManage, [Role::Owner]],
    'delete store' => [Permission::StoreDelete, [Role::Owner]],
]);
