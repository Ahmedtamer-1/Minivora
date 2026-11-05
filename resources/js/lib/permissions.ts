import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

/** Mirrors App\Core\Permissions\Permission. The server always re-checks. */
export type Permission =
    | 'design.manage'
    | 'products.view'
    | 'products.manage'
    | 'orders.view'
    | 'orders.manage'
    | 'refunds.view'
    | 'refunds.request'
    | 'refunds.manage'
    | 'marketing.manage'
    | 'ledger.view'
    | 'ledger.manage'
    | 'budgets.view'
    | 'budgets.manage'
    | 'ai.ask_finance'
    | 'ai.approve'
    | 'members.manage'
    | 'settings.manage'
    | 'store.delete';

export function useCan(): (permission: Permission) => boolean {
    const { store } = usePage<SharedProps>().props;
    return (permission) => store?.permissions.includes(permission) ?? false;
}
