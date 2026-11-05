import { Link, router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, ExternalLink, LogOut, Menu, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Preferences } from '@/components/preferences';
import { StoreStatusBadge } from '@/components/status-badge';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

/** Top bar: menu (mobile), store switcher, view store, language/theme, sign out. */
export function Topbar({ onMenu }: { onMenu: () => void }) {
    const t = useT();
    const { store, stores } = usePage<SharedProps>().props;
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const close = (e: MouseEvent) => !ref.current?.contains(e.target as Node) && setOpen(false);
        const esc = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', esc);
        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', esc);
        };
    }, [open]);

    return (
        <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line bg-elevated/90 px-4 backdrop-blur">
            <button type="button" onClick={onMenu} className="rounded-md p-2 text-ink-muted hover:bg-secondary lg:hidden" aria-label="Menu">
                <Menu className="h-5 w-5" />
            </button>

            {store ? (
                <div className="relative" ref={ref}>
                    <button
                        type="button"
                        onClick={() => setOpen((v) => !v)}
                        className="flex max-w-[60vw] items-center gap-2 rounded-md px-2 py-1.5 hover:bg-secondary"
                        aria-haspopup="menu"
                        aria-expanded={open}
                        aria-label={t('nav.switch_store')}
                    >
                        <span aria-hidden className="grid h-7 w-7 shrink-0 place-items-center rounded-sm bg-accent-subtle font-display text-sm font-semibold text-primary">
                            {store.name.charAt(0).toUpperCase()}
                        </span>
                        <span className="truncate font-medium">{store.name}</span>
                        <ChevronsUpDown className="h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
                    </button>

                    {open ? (
                        <div role="menu" className="absolute start-0 top-full mt-2 w-72 rounded-lg border border-line bg-elevated p-1 shadow-lg">
                            {stores.map((s) => (
                                <button
                                    key={s.id}
                                    role="menuitem"
                                    type="button"
                                    onClick={() => router.post(`/stores/${s.id}/switch`)}
                                    className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-start text-sm hover:bg-secondary"
                                >
                                    <span className="flex-1 truncate">
                                        <span className="block truncate font-medium">{s.name}</span>
                                        <span className="block truncate text-xs text-ink-muted" dir="ltr">
                                            {s.subdomain}
                                        </span>
                                    </span>
                                    {s.id === store.id ? <Check className="h-4 w-4 text-primary" aria-hidden /> : null}
                                </button>
                            ))}
                            <div className="my-1 border-t border-line" />
                            <Link role="menuitem" href="/stores/create" className="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-ink-link hover:bg-secondary">
                                <Plus className="h-4 w-4" aria-hidden />
                                {t('nav.create_store')}
                            </Link>
                        </div>
                    ) : null}
                </div>
            ) : null}

            {store ? (
                <div className="hidden sm:block">
                    <StoreStatusBadge status={store.status} />
                </div>
            ) : null}

            <div className="ms-auto flex items-center gap-1">
                {store ? (
                    <a href="/preview" target="_blank" rel="noopener" className="hidden h-9 items-center gap-1.5 rounded-md px-2.5 text-sm text-ink-muted hover:bg-secondary hover:text-ink md:inline-flex">
                        <ExternalLink className="h-4 w-4" aria-hidden />
                        {t('nav.view_store')}
                    </a>
                ) : null}
                <Preferences />
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    className="inline-flex h-9 w-9 items-center justify-center rounded-md text-ink-muted hover:bg-secondary hover:text-ink"
                    aria-label={t('nav.sign_out')}
                >
                    <LogOut className="h-4 w-4 rtl:-scale-x-100" />
                </Link>
            </div>
        </header>
    );
}
