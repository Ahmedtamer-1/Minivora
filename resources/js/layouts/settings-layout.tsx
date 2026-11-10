import { Link, usePage } from '@inertiajs/react';
import {
    Accessibility,
    BellRing,
    CreditCard,
    Globe2,
    History,
    Store,
    Truck,
    UserRound,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { useRef, type ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';
import { cleanPath } from '@/lib/nav';
import { useCan, type Permission } from '@/lib/permissions';
import { useCurrentInView } from '@/lib/use-current-in-view';
import { DashboardLayout, PageHeader } from './dashboard-layout';

interface Item {
    key: string;
    href: string;
    icon: LucideIcon;
    permission?: Permission;
    planned?: boolean;
}

// Store settings need settings.manage; your own account and display settings are open to everyone.
const groups: Array<{ key: 'you' | 'store'; items: Item[] }> = [
    {
        key: 'you',
        items: [
            { key: 'account', href: '/settings/account', icon: UserRound },
            { key: 'accessibility', href: '/settings/accessibility', icon: Accessibility },
            { key: 'notifications', href: '/settings/notifications', icon: BellRing, planned: true },
        ],
    },
    {
        key: 'store',
        items: [
            { key: 'general', href: '/settings', icon: Store, permission: 'settings.manage' },
            { key: 'members', href: '/settings/members', icon: Users, permission: 'members.manage' },
            { key: 'payments', href: '/settings/payments', icon: CreditCard, permission: 'settings.manage', planned: true },
            { key: 'shipping', href: '/settings/shipping', icon: Truck, permission: 'settings.manage', planned: true },
            { key: 'domain', href: '/settings/domain', icon: Globe2, permission: 'settings.manage', planned: true },
            { key: 'activity', href: '/settings/activity', icon: History, permission: 'settings.manage' },
        ],
    },
];

/**
 * Every settings page shares this frame: a list of settings on the side (a scrollable row on
 * phones), the page title, and one readable column of cards, so no settings page is wider or
 * narrower than another.
 */
export function SettingsLayout({ title, description, action, children }: { title: string; description?: ReactNode; action?: ReactNode; children: ReactNode }) {
    const t = useT();
    const can = useCan();
    const path = cleanPath(usePage().url);
    const row = useRef<HTMLDivElement>(null);
    useCurrentInView(row);

    const visible = groups.map((g) => ({ ...g, items: g.items.filter((i) => !i.permission || can(i.permission)) })).filter((g) => g.items.length > 0);

    return (
        <DashboardLayout title={title}>
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[220px_minmax(0,1fr)] xl:gap-8">
                <nav aria-label={t('settings.nav')} className="min-w-0 lg:sticky lg:top-6 lg:self-start">
                    <div ref={row} className="-mx-4 flex gap-4 overflow-x-auto px-4 pb-1 sm:-mx-6 sm:px-6 lg:mx-0 lg:block lg:space-y-5 lg:overflow-visible lg:px-0">
                        {visible.map((group) => (
                            <section key={group.key} aria-labelledby={`settings-${group.key}`} className="shrink-0">
                                <h2 id={`settings-${group.key}`} className="mb-1.5 hidden px-3 text-caption text-ink-muted lg:block">
                                    {t(`settings.group_${group.key}`)}
                                </h2>
                                <ul className="flex gap-1.5 lg:flex-col lg:gap-0.5">
                                    {group.items.map((item) => {
                                        const Icon = item.icon;
                                        const active = path === item.href;
                                        return (
                                            <li key={item.key}>
                                                <Link
                                                    href={item.href}
                                                    prefetch
                                                    aria-current={active ? 'page' : undefined}
                                                    className={cn(
                                                        'flex h-10 items-center gap-2.5 whitespace-nowrap rounded-full px-3 text-sm transition-colors duration-200 lg:w-full',
                                                        active ? 'bg-primary text-primary-fg shadow-sm' : 'glass-strong text-ink hover:bg-elevated lg:border-transparent lg:bg-transparent lg:backdrop-blur-none lg:hover:bg-secondary',
                                                    )}
                                                >
                                                    <Icon className="h-4 w-4 shrink-0" aria-hidden strokeWidth={1.75} />
                                                    <span className="lg:flex-1">{t(`settings.item_${item.key}`)}</span>
                                                    {item.planned ? <span className={cn('hidden text-[0.6875rem] lg:inline', active ? 'text-primary-fg' : 'text-ink-muted')}>{t('common.preview')}</span> : null}
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </section>
                        ))}
                    </div>
                </nav>

                <div className="min-w-0 max-w-3xl">
                    <PageHeader title={title} description={description} action={action} />
                    <div className="space-y-6">{children}</div>
                </div>
            </div>
        </DashboardLayout>
    );
}
