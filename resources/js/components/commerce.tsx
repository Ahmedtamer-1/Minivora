// Shared pieces for the orders and customers pages: status badge, channel icon, search, filter
// tabs, pagination, tag input and stat tiles.
import { Link, router } from '@inertiajs/react';
import { Globe, Instagram, MessageCircle, PenLine, Phone, Search, X, type LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState, type KeyboardEvent, type ReactNode } from 'react';
import { Badge, type Tone } from '@/components/ui/badge';
import { cn } from '@/lib/cn';
import { formatNumber, useT } from '@/lib/i18n';
import type { Paginated } from '@/types';

export type OrderStatus = 'pending' | 'paid' | 'processing' | 'shipped' | 'delivered' | 'cancelled' | 'refunded' | 'returned';
export type Channel = 'website' | 'instagram' | 'whatsapp' | 'phone' | 'other';
export type PaymentMethod = 'cod' | 'card' | 'wallet' | 'instapay';

export interface OrderRow {
    id: number;
    number: number;
    status: OrderStatus;
    channel: Channel;
    payment_method: PaymentMethod;
    customer_name: string;
    phone: string | null;
    governorate: string | null;
    total: number;
    items_count: number | null;
    tags: string[];
    placed_at: string;
}

export interface CustomerCard {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    governorate: string | null;
    city: string | null;
    address: string | null;
    tags: string[];
    orders_count: number;
    total_spent: number;
    last_order_at: string | null;
}

const statusTone: Record<OrderStatus, Tone> = {
    pending: 'warning',
    paid: 'info',
    processing: 'highlight',
    shipped: 'info',
    delivered: 'success',
    cancelled: 'neutral',
    refunded: 'neutral',
    returned: 'danger',
};

export function OrderStatusBadge({ status, className }: { status: OrderStatus; className?: string }) {
    const t = useT();
    return (
        <Badge tone={statusTone[status]} className={className}>
            <span className={cn('h-1.5 w-1.5 rounded-full bg-current', status === 'pending' && 'mv-pulse-dot')} aria-hidden />
            {t(`orders.status.${status}`)}
        </Badge>
    );
}

const channelIcon: Record<Channel, LucideIcon> = { website: Globe, instagram: Instagram, whatsapp: MessageCircle, phone: Phone, other: PenLine };

/** Where the order came from, as an icon with an accessible name (or with its label). */
export function ChannelIcon({ channel, withLabel = false, className }: { channel: Channel; withLabel?: boolean; className?: string }) {
    const t = useT();
    const Icon = channelIcon[channel] ?? PenLine;
    const label = t(`orders.channel.${channel}`);
    return (
        <span className={cn('inline-flex items-center gap-1.5 text-ink-muted', className)} title={withLabel ? undefined : label}>
            <Icon className="h-4 w-4 shrink-0" aria-hidden strokeWidth={1.75} />
            {withLabel ? <span className="text-sm text-ink">{label}</span> : <span className="sr-only">{label}</span>}
        </span>
    );
}

/** Glass pill search field. */
export function SearchField({ value, onChange, label, className }: { value: string; onChange: (v: string) => void; label: string; className?: string }) {
    return (
        <label className={cn('glass-strong flex h-11 w-full items-center gap-2 rounded-full px-4 shadow-sm sm:w-[300px]', className)}>
            <Search className="h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
            <span className="sr-only">{label}</span>
            <input
                type="search"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={label}
                className="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-sm placeholder:text-ink-muted focus:ring-0"
            />
        </label>
    );
}

/**
 * Search box state that reloads the page as you type (debounced, replacing history), keeping the
 * other query parameters.
 */
export function useSearchVisit(url: string, initial: string, params: Record<string, string | undefined>) {
    const [q, setQ] = useState(initial);
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => {
            router.get(url, { ...params, q: q || undefined }, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);
        return () => clearTimeout(timer);
    }, [q]); // eslint-disable-line react-hooks/exhaustive-deps
    return [q, setQ] as const;
}

export interface TabOption<K extends string> {
    key: K;
    label: string;
    count?: number;
}

/** Pill filter tabs with counts (Figma "Products · HIG" filters). */
export function FilterTabs<K extends string>({ options, active, onChange, label }: { options: TabOption<K>[]; active: K; onChange: (k: K) => void; label: string }) {
    return (
        <div role="group" aria-label={label} className="-mx-1 mb-6 flex gap-2 overflow-x-auto px-1 pb-1">
            {options.map((o) => {
                const on = o.key === active;
                return (
                    <button
                        key={o.key}
                        type="button"
                        aria-pressed={on}
                        onClick={() => onChange(o.key)}
                        className={cn(
                            'inline-flex h-9 shrink-0 items-center gap-2 rounded-full px-4 text-sm shadow-sm transition-colors',
                            on ? 'bg-primary text-primary-fg' : 'glass-strong text-ink hover:bg-elevated',
                        )}
                    >
                        {o.label}
                        {o.count !== undefined ? <span className={cn('tabular text-caption', on ? 'text-primary-fg' : 'text-ink-muted')}>{formatNumber(o.count)}</span> : null}
                    </button>
                );
            })}
        </div>
    );
}

export function Pagination({ page }: { page: Paginated<unknown> }) {
    const t = useT();
    if (page.last_page <= 1) return null;
    const pill = 'glass-strong rounded-full px-4 py-2 hover:bg-elevated';
    return (
        <nav className="mt-8 flex items-center justify-between text-sm" aria-label={t('pagination.label')}>
            {page.prev_page_url ? (
                <Link href={page.prev_page_url} preserveScroll className={pill}>
                    {t('pagination.prev')}
                </Link>
            ) : (
                <span />
            )}
            <span className="text-ink-muted">{t('pagination.page', { page: page.current_page, last: page.last_page })}</span>
            {page.next_page_url ? (
                <Link href={page.next_page_url} preserveScroll className={pill}>
                    {t('pagination.next')}
                </Link>
            ) : (
                <span />
            )}
        </nav>
    );
}

/** Type a tag and press Enter or comma to add it; Backspace on an empty box removes the last one. */
export function TagInput({ id, value, onChange, placeholder, max = 10 }: { id: string; value: string[]; onChange: (tags: string[]) => void; placeholder?: string; max?: number }) {
    const t = useT();
    const [draft, setDraft] = useState('');

    const add = (raw: string) => {
        const tag = raw.trim().slice(0, 30);
        if (!tag || value.length >= max || value.some((v) => v.toLowerCase() === tag.toLowerCase())) return;
        onChange([...value, tag]);
    };

    const onKey = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || e.key === ',' || e.key === '،') {
            e.preventDefault();
            add(draft);
            setDraft('');
        } else if (e.key === 'Backspace' && draft === '' && value.length > 0) {
            onChange(value.slice(0, -1));
        }
    };

    return (
        <div className="flex min-h-10 flex-wrap items-center gap-1.5 rounded-lg border border-line-control bg-elevated px-2 py-1.5 focus-within:border-line-focus focus-within:ring-1 focus-within:ring-line-focus">
            {value.map((tag) => (
                <span key={tag} className="inline-flex h-7 items-center gap-1 rounded-full bg-accent-subtle ps-2.5 pe-1 text-caption text-ink">
                    {tag}
                    <button
                        type="button"
                        onClick={() => onChange(value.filter((v) => v !== tag))}
                        className="grid h-5 w-5 place-items-center rounded-full hover:bg-secondary"
                        aria-label={t('tags.remove', { tag })}
                    >
                        <X className="h-3 w-3" aria-hidden />
                    </button>
                </span>
            ))}
            <input
                id={id}
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                onKeyDown={onKey}
                onBlur={() => {
                    add(draft);
                    setDraft('');
                }}
                placeholder={value.length === 0 ? placeholder : undefined}
                className="h-7 min-w-24 flex-1 border-0 bg-transparent p-0 text-sm placeholder:text-ink-muted focus:ring-0"
            />
        </div>
    );
}

/** Small number tile for page headers: label, big number, optional hint. */
export function StatTile({ label, value, hint, icon: Icon, tone = 'default' }: { label: string; value: ReactNode; hint?: ReactNode; icon?: LucideIcon; tone?: 'default' | 'warning' | 'danger' }) {
    return (
        <div className="card flex h-full items-start gap-3 p-3 sm:p-4">
            {Icon ? (
                <span
                    className={cn(
                        'hidden h-10 w-10 shrink-0 place-items-center rounded-full sm:grid',
                        tone === 'warning' ? 'bg-warning-subtle text-warning' : tone === 'danger' ? 'bg-danger-subtle text-danger' : 'bg-accent-subtle text-ink-accent',
                    )}
                >
                    <Icon className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                </span>
            ) : null}
            <div className="min-w-0">
                <p className="truncate text-[0.6875rem] text-ink-muted sm:text-caption">{label}</p>
                <p className={cn('tabular mt-0.5 truncate font-display text-base sm:text-xl', tone === 'warning' && 'text-warning sm:text-ink', tone === 'danger' && 'text-danger sm:text-ink')}>{value}</p>
                {hint ? <p className="mt-0.5 hidden truncate text-caption text-ink-muted sm:block">{hint}</p> : null}
            </div>
        </div>
    );
}

/** Address on one line, with the comma of the dashboard language. */
export function joinAddress(parts: Array<string | null | undefined>, locale: 'ar' | 'en'): string {
    return parts.filter(Boolean).join(locale === 'ar' ? '، ' : ', ');
}

/** Initials avatar for a customer. */
export function Initials({ name, className }: { name: string; className?: string }) {
    const letters = name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0] ?? '')
        .join('')
        .toUpperCase();
    return (
        <span aria-hidden className={cn('grid h-10 w-10 shrink-0 place-items-center rounded-full bg-accent-subtle font-display text-sm text-ink-accent', className)}>
            {letters || '?'}
        </span>
    );
}
