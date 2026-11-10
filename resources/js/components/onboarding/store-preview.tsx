import { ImageIcon } from 'lucide-react';
import type { CSSProperties } from 'react';
import { cn } from '@/lib/cn';
import { formatNumber, useT } from '@/lib/i18n';
import type { Draft, Palette } from '@/lib/onboarding';

interface Props {
    draft: Draft;
    palette: Palette;
    storeName: string;
    address: string;
    /** Corner radius of the storefront's cards and buttons, in px. */
    radius?: number;
    /** While building: which parts exist yet. Omit for the finished draft. */
    progress?: { header: boolean; hero: boolean; products: number };
    className?: string;
}

/** A small, non-interactive picture of the storefront draft inside a browser frame. Decorative for screen readers except its label. */
export function StorePreview({ draft, palette, storeName, address, radius = 14, progress, className }: Props) {
    const t = useT();
    const shown = progress ?? { header: true, hero: true, products: draft.products.length };
    const r = (n = 1) => `${Math.round(radius * n)}px`;
    const vars = { background: palette.bg, color: palette.ink, transition: 'background-color 500ms, color 500ms' } as CSSProperties;

    return (
        <figure className={cn('card overflow-hidden p-0 shadow-lg', className)} aria-label={t('flow.preview_label', { name: storeName })}>
            <div className="flex items-center gap-3 border-b border-line px-4 py-2.5" aria-hidden>
                <span className="flex gap-1.5">
                    <span className="h-2 w-2 rounded-full bg-line-strong" />
                    <span className="h-2 w-2 rounded-full bg-line-strong" />
                    <span className="h-2 w-2 rounded-full bg-line-strong" />
                </span>
                <span dir="ltr" className="min-w-0 flex-1 truncate text-[0.6875rem] text-ink-muted">
                    {address}
                </span>
                <span className="text-[0.625rem] font-semibold uppercase tracking-[0.14em] text-ink-accent">{t('flow.draft_preview')}</span>
            </div>

            <div aria-hidden className="space-y-3 p-3 sm:space-y-4 sm:p-4" style={vars}>
                {/* Store header */}
                <div className={cn('flex items-center justify-between gap-4 px-1 transition-opacity duration-500', shown.header ? 'opacity-100' : 'opacity-0')}>
                    <span className="truncate text-[0.6875rem] font-semibold uppercase tracking-[0.3em]">{storeName}</span>
                    <span className="hidden gap-4 text-[0.6875rem] sm:flex" style={{ color: palette.muted }}>
                        {draft.nav.map((n) => (
                            <span key={n}>{n}</span>
                        ))}
                    </span>
                </div>

                {/* Hero */}
                {shown.hero ? (
                    <div className="mv-rise grid items-center gap-4 p-4 sm:grid-cols-[1.3fr_1fr] sm:p-5" style={{ background: palette.panel, borderRadius: r(1.2), transition: 'background-color 500ms, border-radius 300ms' }}>
                        <div className="min-w-0">
                            <p className="font-display text-xl font-bold leading-tight tracking-[-0.02em] sm:text-2xl lg:text-[1.75rem]">{draft.hero}</p>
                            <p className="mt-1.5 text-[0.75rem] leading-snug" style={{ color: palette.muted }}>
                                {draft.tagline}
                            </p>
                            <span className="mt-3 inline-flex h-8 items-center px-4 text-[0.75rem] font-semibold" style={{ background: palette.accent, color: palette.onAccent, borderRadius: r(1) }}>
                                {t('flow.shop_now')}
                            </span>
                        </div>
                        <div className="hidden aspect-[4/3] place-items-center sm:grid" style={{ background: palette.image, borderRadius: r(0.8), color: palette.muted }}>
                            <ImageIcon className="h-6 w-6 opacity-60" />
                        </div>
                    </div>
                ) : (
                    <div className="h-36 animate-pulse rounded-xl" style={{ background: palette.panel }} />
                )}

                {/* Products */}
                <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4 sm:gap-3">
                    {draft.products.map((p, i) =>
                        i < shown.products ? (
                            <div key={p.name} className="mv-rise p-2" style={{ background: palette.card, borderRadius: r(0.9), transition: 'background-color 500ms, border-radius 300ms' }}>
                                <div className="grid aspect-[4/3] place-items-center" style={{ background: palette.image, borderRadius: r(0.6), color: palette.muted }}>
                                    <ImageIcon className="h-5 w-5 opacity-60" />
                                </div>
                                <p className="mt-2 truncate text-[0.6875rem] font-medium">{p.name}</p>
                                <p className="text-[0.6875rem]" style={{ color: palette.price }}>
                                    {formatNumber(p.price)} EGP
                                </p>
                            </div>
                        ) : (
                            <div key={p.name} className="p-2" style={{ background: palette.card, borderRadius: r(0.9) }}>
                                <div className="aspect-[4/3] animate-pulse" style={{ background: palette.image, borderRadius: r(0.6), opacity: 0.6 }} />
                                <div className="mt-2 h-2 w-3/4 animate-pulse rounded-full" style={{ background: palette.image }} />
                                <div className="mt-1.5 h-2 w-1/3 animate-pulse rounded-full" style={{ background: palette.image }} />
                            </div>
                        ),
                    )}
                </div>
            </div>
        </figure>
    );
}
