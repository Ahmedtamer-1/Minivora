import { Link, usePage } from '@inertiajs/react';
import { House, LayoutTemplate, Package, Shield, ShoppingBag, SlidersHorizontal, Users, Wallet, type LucideIcon } from 'lucide-react';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';
import { useCan, type Permission } from '@/lib/permissions';
import type { SharedProps } from '@/types';

interface NavItem {
    key: string;
    icon: LucideIcon;
    href?: string; // no href = module not built yet
    permission?: Permission;
    match?: (path: string) => boolean;
}

// Figma "Rail": Home, Products, Orders, Customers, Design, Money, Settings.
const items: NavItem[] = [
    { key: 'home', icon: House, href: '/dashboard', match: (p) => p === '/dashboard' },
    { key: 'products', icon: Package, href: '/products', permission: 'products.view', match: (p) => p.startsWith('/products') },
    { key: 'orders', icon: ShoppingBag },
    { key: 'customers', icon: Users },
    { key: 'builder', icon: LayoutTemplate },
    { key: 'finance', icon: Wallet },
    { key: 'settings', icon: SlidersHorizontal, href: '/settings', permission: 'settings.manage', match: (p) => p.startsWith('/settings') },
];

const admin: NavItem = { key: 'admin', icon: Shield, href: '/admin/stores', match: (p) => p.startsWith('/admin') };

function useNav() {
    const t = useT();
    const can = useCan();
    const { url, props } = usePage<SharedProps>();
    const path = url.split('?')[0];
    const visible = items.filter((i) => !i.permission || can(i.permission));
    if (props.auth.user?.is_platform_admin) visible.push(admin);

    return { t, path, visible };
}

/**
 * Desktop: floating glass rail. Icons carry a tooltip and an accessible name; the current page is
 * filled. Modules that are not built yet stay visible but say "Soon".
 */
export function Rail() {
    const { t, path, visible } = useNav();

    return (
        <nav aria-label={t('nav.main')} className="glass vt-rail fixed start-8 top-[136px] z-30 hidden w-[62px] rounded-full p-[8px] lg:block">
            <ul className="flex flex-col items-center gap-2">
                {visible.map((item) => {
                    const Icon = item.icon;
                    const label = t(`nav.${item.key}`);
                    const active = item.match?.(path) ?? false;
                    const tip = (
                        <span
                            role="tooltip"
                            className="glass-float pointer-events-none absolute start-full top-1/2 z-40 ms-3 -translate-y-1/2 whitespace-nowrap rounded-full px-3 py-1.5 text-caption text-ink opacity-0 shadow-md transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100"
                        >
                            {label}
                            {item.href ? null : <span className="text-ink-muted"> · {t('common.soon')}</span>}
                        </span>
                    );

                    if (!item.href) {
                        return (
                            <li key={item.key}>
                                <span
                                    tabIndex={0}
                                    aria-disabled="true"
                                    aria-label={`${label}, ${t('common.soon')}`}
                                    className="group relative grid h-11 w-11 cursor-default place-items-center rounded-full text-ink-muted"
                                >
                                    <Icon className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                                    {tip}
                                </span>
                            </li>
                        );
                    }

                    return (
                        <li key={item.key}>
                            <Link
                                href={item.href}
                                prefetch
                                aria-label={label}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'group relative grid h-11 w-11 place-items-center rounded-full transition-colors',
                                    active ? 'bg-primary text-primary-fg shadow-md' : 'text-ink hover:bg-secondary',
                                )}
                            >
                                <Icon className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                                {tip}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

/** Phones and tablets: a bottom tab bar with labels, only for areas that exist. */
export function TabBar() {
    const { t, path, visible } = useNav();
    const tabs = visible.filter((i) => i.href);

    return (
        <nav aria-label={t('nav.main')} className="glass vt-tabbar fixed inset-x-3 bottom-3 z-30 rounded-full p-1.5 lg:hidden">
            <ul className="flex">
                {tabs.map((item) => {
                    const Icon = item.icon;
                    const active = item.match?.(path) ?? false;
                    return (
                        <li key={item.key} className="flex-1">
                            <Link
                                href={item.href!}
                                prefetch
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-[48px] flex-col items-center justify-center gap-0.5 rounded-full text-[11px] font-medium transition-colors',
                                    active ? 'bg-primary text-primary-fg' : 'text-ink-muted hover:text-ink',
                                )}
                            >
                                <Icon className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                                {t(`nav.${item.key}`)}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
