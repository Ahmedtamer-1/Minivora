import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bot,
    CheckSquare,
    History,
    Home,
    LayoutTemplate,
    Megaphone,
    Package,
    Search,
    Settings,
    Shield,
    ShoppingBag,
    Users,
    UsersRound,
    type LucideIcon,
} from 'lucide-react';
import { Logo } from '@/components/logo';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';
import { useCan, type Permission } from '@/lib/permissions';
import type { SharedProps } from '@/types';

interface NavItem {
    key: string;
    icon: LucideIcon;
    href?: string; // no href = module not built yet
    permission?: Permission;
    match?: string;
}

// SRS §7.1 dashboard areas. Items without href show as "Soon" until their module ships.
const main: NavItem[] = [
    { key: 'home', icon: Home, href: '/dashboard', match: '/dashboard' },
    { key: 'ai', icon: Bot },
    { key: 'approvals', icon: CheckSquare },
    { key: 'products', icon: Package },
    { key: 'orders', icon: ShoppingBag },
    { key: 'customers', icon: UsersRound },
    { key: 'builder', icon: LayoutTemplate },
    { key: 'marketing', icon: Megaphone },
    { key: 'seo', icon: Search },
    { key: 'finance', icon: BarChart3 },
];

const settings: NavItem[] = [
    { key: 'settings', icon: Settings, href: '/settings', permission: 'settings.manage', match: '/settings' },
    { key: 'members', icon: Users, href: '/settings/members', permission: 'members.manage', match: '/settings/members' },
    { key: 'activity', icon: History, href: '/settings/activity', permission: 'settings.manage', match: '/settings/activity' },
];

export function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
    const t = useT();
    const can = useCan();
    const { url, props } = usePage<SharedProps>();
    const path = url.split('?')[0];

    const isActive = (item: NavItem) => item.match !== undefined && path === item.match;

    const renderItem = (item: NavItem) => {
        const Icon = item.icon;
        const label = t(`nav.${item.key}`);

        if (!item.href) {
            return (
                <li key={item.key}>
                    <span className="flex cursor-default items-center gap-3 rounded-md px-3 py-2 text-sm text-ink-muted/70" aria-disabled>
                        <Icon className="h-4 w-4 shrink-0" aria-hidden />
                        <span className="flex-1 truncate">{label}</span>
                        <Badge className="normal-case">{t('common.soon')}</Badge>
                    </span>
                </li>
            );
        }

        return (
            <li key={item.key}>
                <Link
                    href={item.href}
                    onClick={onNavigate}
                    className={cn(
                        'flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                        isActive(item) ? 'bg-accent-subtle font-medium text-ink' : 'text-ink-muted hover:bg-secondary hover:text-ink',
                    )}
                    aria-current={isActive(item) ? 'page' : undefined}
                >
                    <Icon className={cn('h-4 w-4 shrink-0', isActive(item) && 'text-primary')} aria-hidden />
                    <span className="truncate">{label}</span>
                </Link>
            </li>
        );
    };

    const visibleSettings = settings.filter((item) => !item.permission || can(item.permission));

    return (
        <nav className="flex h-full flex-col gap-6 overflow-y-auto px-3 py-5" aria-label="Main">
            <div className="px-3">
                <Logo href="/dashboard" />
            </div>
            <ul className="space-y-0.5">{main.map(renderItem)}</ul>
            {visibleSettings.length > 0 ? <ul className="space-y-0.5 border-t border-line pt-4">{visibleSettings.map(renderItem)}</ul> : null}
            {props.auth.user?.is_platform_admin ? (
                <ul className="mt-auto border-t border-line pt-4">{renderItem({ key: 'admin', icon: Shield, href: '/admin/stores', match: '/admin/stores' })}</ul>
            ) : null}
        </nav>
    );
}
