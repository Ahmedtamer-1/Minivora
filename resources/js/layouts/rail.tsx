import { Link, usePage } from '@inertiajs/react';
import { Ellipsis, Shield, UserRound, X, type LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';
import { cleanPath, groups, type GroupKey, type Section } from '@/lib/nav';
import { useCan } from '@/lib/permissions';
import type { SharedProps } from '@/types';

export interface NavEntry {
    key: string;
    icon: LucideIcon;
    href: string;
    planned?: boolean;
    active: boolean;
}

const adminSection: Section = { key: 'admin', icon: Shield, href: '/admin/stores', match: (p) => p.startsWith('/admin') };

/** The sections this member may open, grouped, with the current one marked. */
export function useNav() {
    const t = useT();
    const can = useCan();
    const { url, props } = usePage<SharedProps>();
    const path = cleanPath(url);

    const toEntry = (s: Section): NavEntry => ({
        key: s.key,
        icon: s.icon,
        // Members without store settings still have their own account and display settings.
        href: s.key === 'settings' && !can('settings.manage') ? '/settings/account' : s.href,
        planned: s.planned,
        active: s.match(path),
    });

    const visibleGroups = groups
        .map((g) => ({
            key: g.key as GroupKey | 'platform',
            entries: g.sections.filter((s) => !s.permissions || s.permissions.some((p) => can(p))).map(toEntry),
        }))
        .filter((g) => g.entries.length > 0);

    if (props.auth.user?.is_platform_admin) visibleGroups.push({ key: 'platform', entries: [toEntry(adminSection)] });

    return { t, path, groups: visibleGroups, entries: visibleGroups.flatMap((g) => g.entries) };
}

const railItem = 'group relative grid h-10 w-10 place-items-center rounded-full transition-colors duration-200';

/**
 * Desktop: floating glass rail, split into the SRS groups by thin dividers. Icons carry a tooltip
 * and an accessible name; the current section is filled. Sections still in preview show a small dot.
 */
export function Rail() {
    const { t, groups: navGroups } = useNav();

    return (
        <nav aria-label={t('nav.main')} className="glass vt-rail fixed start-8 top-[128px] z-30 hidden w-[56px] rounded-full p-[8px] lg:block">
            {navGroups.map((group, gi) => (
                <ul key={group.key} aria-label={t(`nav.group_${group.key}`)} className={cn('flex flex-col items-center gap-1', gi > 0 && 'mt-2 border-t border-line pt-2')}>
                    {group.entries.map((item) => {
                        const Icon = item.icon;
                        const label = t(`nav.${item.key}`);
                        return (
                            <li key={item.key}>
                                <Link
                                    href={item.href}
                                    prefetch
                                    aria-label={item.planned ? `${label}, ${t('common.preview')}` : label}
                                    aria-current={item.active ? 'page' : undefined}
                                    className={cn(railItem, item.active ? 'bg-primary text-primary-fg shadow-md' : 'text-ink hover:bg-secondary')}
                                >
                                    <Icon className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                                    {item.planned && !item.active ? <span aria-hidden className="absolute end-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-ink-muted opacity-60" /> : null}
                                    <span
                                        role="tooltip"
                                        className="glass-float pointer-events-none absolute start-full top-1/2 z-40 ms-3 -translate-y-1/2 whitespace-nowrap rounded-full px-3 py-1.5 text-caption text-ink opacity-0 shadow-md transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100"
                                    >
                                        {label}
                                        {item.planned ? <span className="text-ink-muted"> · {t('common.preview')}</span> : null}
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            ))}
        </nav>
    );
}

// Phone tab bar: the four daily areas, then "More" for everything else (bottom nav stays at 5).
const tabKeys = ['home', 'orders', 'products', 'customers'];

const tabClass = 'flex min-h-[52px] w-full flex-col items-center justify-center gap-0.5 rounded-full text-[0.6875rem] font-medium transition-colors';

/** Phones and tablets: a bottom tab bar with labels, plus a "More" sheet with every section. */
export function TabBar() {
    const { t, path, groups: navGroups, entries } = useNav();
    const [open, setOpen] = useState(false);
    const tabs = tabKeys.map((k) => entries.find((e) => e.key === k)).filter((e): e is NavEntry => !!e);
    const moreActive = !tabs.some((e) => e.active);

    // Close the sheet after navigating.
    useEffect(() => setOpen(false), [path]);

    return (
        <>
            <nav aria-label={t('nav.main')} className="glass vt-tabbar fixed inset-x-3 bottom-3 z-30 rounded-full p-1.5 lg:hidden">
                <ul className="flex">
                    {tabs.map((item) => {
                        const Icon = item.icon;
                        return (
                            <li key={item.key} className="flex-1">
                                <Link
                                    href={item.href}
                                    prefetch
                                    aria-current={item.active ? 'page' : undefined}
                                    className={cn(tabClass, item.active ? 'bg-primary text-primary-fg' : 'text-ink-muted hover:text-ink')}
                                >
                                    <Icon className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                                    {t(`nav.${item.key}`)}
                                </Link>
                            </li>
                        );
                    })}
                    <li className="flex-1">
                        <button
                            type="button"
                            onClick={() => setOpen(true)}
                            aria-haspopup="dialog"
                            aria-expanded={open}
                            className={cn(tabClass, moreActive ? 'bg-primary text-primary-fg' : 'text-ink-muted hover:text-ink')}
                        >
                            <Ellipsis className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                            {t('nav.more')}
                        </button>
                    </li>
                </ul>
            </nav>
            <MoreSheet open={open} onClose={() => setOpen(false)} groups={navGroups} />
        </>
    );
}

/** Every section, grouped like the rail, in a native modal dialog (focus trap, Escape, inert page). */
function MoreSheet({ open, onClose, groups: navGroups }: { open: boolean; onClose: () => void; groups: ReturnType<typeof useNav>['groups'] }) {
    const t = useT();
    const ref = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    return (
        <dialog
            ref={ref}
            onClose={onClose}
            onClick={(e) => e.target === ref.current && onClose()}
            aria-labelledby="more-title"
            className="glass-float m-0 mt-auto max-h-[85dvh] w-full max-w-none overflow-y-auto rounded-t-[28px] p-0 text-ink backdrop:bg-black/30 lg:hidden"
        >
            <div className="px-4 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-3">
                <div className="mx-auto mb-3 h-1 w-10 rounded-full bg-line" aria-hidden />
                <div className="mb-2 flex items-center justify-between">
                    <h2 id="more-title" className="font-display text-lg">
                        {t('nav.everything')}
                    </h2>
                    <button type="button" onClick={onClose} className="grid h-11 w-11 place-items-center rounded-full hover:bg-secondary" aria-label={t('common.close')}>
                        <X className="h-5 w-5" aria-hidden />
                    </button>
                </div>
                {navGroups.map((group) => (
                    <section key={group.key} aria-labelledby={`more-${group.key}`} className="mt-3">
                        <h3 id={`more-${group.key}`} className="px-1 pb-1.5 text-caption text-ink-muted">
                            {t(`nav.group_${group.key}`)}
                        </h3>
                        <ul className="grid grid-cols-2 gap-2">
                            {group.entries.map((item) => {
                                const Icon = item.icon;
                                return (
                                    <li key={item.key}>
                                        <Link
                                            href={item.href}
                                            aria-current={item.active ? 'page' : undefined}
                                            className={cn(
                                                'flex min-h-[52px] items-center gap-3 rounded-2xl px-3 text-sm transition-colors',
                                                item.active ? 'bg-primary text-primary-fg' : 'bg-secondary text-ink hover:bg-secondary-hover',
                                            )}
                                        >
                                            <Icon className="h-5 w-5 shrink-0" aria-hidden strokeWidth={1.75} />
                                            <span className="min-w-0 flex-1 truncate">{t(`nav.${item.key}`)}</span>
                                            {item.planned ? <span className={cn('text-[0.6875rem]', item.active ? 'text-primary-fg' : 'text-ink-muted')}>{t('common.preview')}</span> : null}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                ))}
                <Link href="/settings/account" className="mt-4 flex min-h-[52px] items-center gap-3 rounded-2xl px-3 text-sm text-ink-link hover:bg-secondary">
                    <UserRound className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                    {t('nav.your_account')}
                </Link>
            </div>
        </dialog>
    );
}
