import { Link, router, usePage } from '@inertiajs/react';
import { Bell, Check, ExternalLink, LogOut, Plus, SlidersHorizontal, Users } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { LanguageButton, roundButton, ThemeButton } from '@/components/preferences';
import { StoreStatusBadge } from '@/components/status-badge';
import { cn } from '@/lib/cn';
import { formatToday, useT } from '@/lib/i18n';
import { useCan } from '@/lib/permissions';
import { useDismiss } from '@/lib/use-dismiss';
import type { SharedProps } from '@/types';

/** "Brown Tote · Opening soon" line under the greeting or above a page title. */
export function StoreLine() {
    const { store } = usePage<SharedProps>().props;
    if (!store) return null;

    return (
        <div className="flex min-w-0 items-center gap-2">
            <span className="truncate text-sm text-ink-muted">{store.name}</span>
            <StoreStatusBadge status={store.status} />
        </div>
    );
}

function initials(name: string): string {
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] ?? '') + (parts.length > 1 ? parts[parts.length - 1][0] : (parts[0]?.[1] ?? ''))).toUpperCase();
}

function Popover({ open, className, children, label }: { open: boolean; className?: string; children: React.ReactNode; label: string }) {
    if (!open) return null;
    return (
        <div role="menu" aria-label={label} className={cn('glass absolute end-0 top-full z-50 mt-2 rounded-2xl p-1.5', className)}>
            {children}
        </div>
    );
}

function NotificationsButton() {
    const t = useT();
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const trigger = useRef<HTMLButtonElement>(null);
    const close = useCallback(() => setOpen(false), []);
    useDismiss(ref, open, close, trigger);

    return (
        <div className="relative" ref={ref}>
            <button ref={trigger} type="button" className={roundButton} aria-label={t('nav.notifications')} aria-expanded={open} aria-haspopup="menu" onClick={() => setOpen((v) => !v)}>
                <Bell className="h-[18px] w-[18px]" aria-hidden />
            </button>
            <Popover open={open} label={t('nav.notifications')} className="w-72">
                <div className="px-3 py-3">
                    <p className="text-sm font-medium">{t('notifications.empty_title')}</p>
                    <p className="mt-1 text-caption text-ink-muted">{t('notifications.empty_body')}</p>
                </div>
            </Popover>
        </div>
    );
}

const menuItem = 'flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-start text-sm hover:bg-secondary';

function AccountMenu() {
    const t = useT();
    const can = useCan();
    const { auth, store, stores } = usePage<SharedProps>().props;
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const trigger = useRef<HTMLButtonElement>(null);
    const close = useCallback(() => setOpen(false), []);
    useDismiss(ref, open, close, trigger);
    const name = auth.user?.name ?? '';

    return (
        <div className="relative" ref={ref}>
            <button
                ref={trigger}
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="grid h-11 w-11 place-items-center rounded-full bg-brand-highlight font-display text-[11px] font-semibold tracking-[0.12em] text-[#0B1A26] shadow-sm"
                aria-label={t('nav.account', { name })}
                aria-expanded={open}
                aria-haspopup="menu"
            >
                {initials(name)}
            </button>
            <Popover open={open} label={t('nav.account', { name })} className="w-72">
                <div className="px-3 pb-2 pt-2">
                    <p className="truncate text-sm font-semibold">{name}</p>
                    <p className="truncate text-caption text-ink-muted">{auth.user?.email}</p>
                </div>
                {stores.length > 0 ? (
                    <>
                        <div className="my-1 border-t border-line" />
                        <p className="px-3 pb-1 pt-2 text-caption text-ink-muted">{t('nav.switch_store')}</p>
                        {stores.map((s) => (
                            <button key={s.id} role="menuitem" type="button" onClick={() => router.post(`/stores/${s.id}/switch`)} className={menuItem}>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-medium">{s.name}</span>
                                    <span className="block truncate text-caption text-ink-muted" dir="ltr">
                                        {s.subdomain}
                                    </span>
                                </span>
                                {s.id === store?.id ? <Check className="h-4 w-4 text-ink-accent" aria-label={t('nav.current_store')} /> : null}
                            </button>
                        ))}
                        <Link role="menuitem" href="/stores/create" className={cn(menuItem, 'text-ink-link')}>
                            <Plus className="h-4 w-4" aria-hidden />
                            {t('nav.create_store')}
                        </Link>
                    </>
                ) : null}
                <div className="my-1 border-t border-line" />
                {store ? (
                    <a role="menuitem" href="/preview" target="_blank" rel="noopener" className={menuItem}>
                        <ExternalLink className="h-4 w-4" aria-hidden />
                        {t('nav.view_store')}
                    </a>
                ) : null}
                {can('members.manage') ? (
                    <Link role="menuitem" href="/settings/members" className={menuItem}>
                        <Users className="h-4 w-4" aria-hidden />
                        {t('nav.members')}
                    </Link>
                ) : null}
                {can('settings.manage') ? (
                    <Link role="menuitem" href="/settings" className={menuItem}>
                        <SlidersHorizontal className="h-4 w-4" aria-hidden />
                        {t('nav.settings')}
                    </Link>
                ) : null}
                <Link role="menuitem" href="/logout" method="post" as="button" className={menuItem}>
                    <LogOut className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                    {t('nav.sign_out')}
                </Link>
            </Popover>
        </div>
    );
}

/** Top-right cluster from Figma: date, notifications, language, theme, account. */
export function TopActions() {
    const { locale } = usePage<SharedProps>().props;

    return (
        <div className="flex shrink-0 items-center gap-2 sm:gap-3">
            <span className="hidden text-sm text-ink-muted xl:inline">{formatToday(locale)}</span>
            <NotificationsButton />
            <LanguageButton />
            <ThemeButton />
            <AccountMenu />
        </div>
    );
}
