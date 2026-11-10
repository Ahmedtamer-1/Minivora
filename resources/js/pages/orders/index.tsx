// Orders list: today's numbers, filters with counts, search, channel, bulk status and CSV export.
// FR-ORD-01, FR-ORD-10
import { Link, router, usePage } from '@inertiajs/react';
import { Download, PhoneCall, Plus, ShoppingBag, Truck, Wallet } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    ChannelIcon,
    FilterTabs,
    OrderStatusBadge,
    Pagination,
    SearchField,
    StatTile,
    useSearchVisit,
    type Channel,
    type OrderRow,
    type OrderStatus,
} from '@/components/commerce';
import { Button, ButtonLink, buttonClasses } from '@/components/ui/button';
import { Checkbox, Select } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { formatDate, formatNumber, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { useCan } from '@/lib/permissions';
import type { Paginated, SharedProps } from '@/types';

type Filter = 'all' | 'pending' | 'to_ship' | 'shipped' | 'delivered' | 'closed';

interface Props {
    orders: Paginated<OrderRow>;
    filter: Filter;
    search: string;
    channel: Channel | null;
    counts: Record<Filter, number>;
    stats: {
        sales: { amount: number; orders: number; change: number | null };
        toShip: { count: number; needs_call: number };
    };
}

const filters: Filter[] = ['all', 'pending', 'to_ship', 'shipped', 'delivered', 'closed'];
const channels: Channel[] = ['website', 'instagram', 'whatsapp', 'phone', 'other'];
const bulkTargets: OrderStatus[] = ['processing', 'shipped', 'delivered', 'cancelled'];

function query(filter: Filter, channel: Channel | null, q?: string) {
    return { filter: filter === 'all' ? undefined : filter, channel: channel ?? undefined, q: q || undefined };
}

export default function OrdersIndex({ orders, filter, search, channel, counts, stats }: Props) {
    const t = useT();
    const can = useCan();
    const { locale } = usePage<SharedProps>().props;
    const manage = can('orders.manage');
    const [q, setQ] = useSearchVisit('/orders', search, query(filter, channel));
    const [selected, setSelected] = useState<number[]>([]);
    const [busy, setBusy] = useState(false);

    const go = (f: Filter, c: Channel | null = channel) => {
        setSelected([]);
        router.get('/orders', query(f, c, q), { preserveScroll: true });
    };

    const pageIds = useMemo(() => orders.data.map((o) => o.id), [orders.data]);
    const allOnPage = pageIds.length > 0 && pageIds.every((id) => selected.includes(id));
    const toggle = (id: number) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));

    const bulk = (status: OrderStatus) => {
        setBusy(true);
        router.post(
            '/orders/bulk-status',
            { ids: selected, status },
            { preserveScroll: true, onSuccess: () => setSelected([]), onFinish: () => setBusy(false) },
        );
    };

    const exportUrl = `/orders/export?${new URLSearchParams(Object.entries(query(filter, channel, search)).filter(([, v]) => v !== undefined) as [string, string][]).toString()}`;

    return (
        <DashboardLayout title={t('nav.orders')}>
            <PageHeader
                title={t('orders.title')}
                description={t('orders.subtitle', { count: formatNumber(counts.all), to_ship: formatNumber(counts.to_ship) })}
                action={
                    <>
                        <SearchField value={q} onChange={setQ} label={t('orders.search')} />
                        <a href={exportUrl} className={buttonClasses('glass', 'lg')} download>
                            <Download className="h-[18px] w-[18px]" aria-hidden />
                            {t('orders.export')}
                        </a>
                        {manage ? (
                            <ButtonLink href="/orders/create" size="lg" className="shadow-md">
                                <Plus className="h-[18px] w-[18px]" aria-hidden />
                                {t('orders.new')}
                            </ButtonLink>
                        ) : null}
                    </>
                }
            />

            <div className="mv-stagger mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile
                    icon={Wallet}
                    label={t('orders.stat_sales')}
                    value={formatPounds(stats.sales.amount, locale)}
                    hint={
                        stats.sales.change !== null
                            ? t('orders.stat_sales_change', { change: `${stats.sales.change > 0 ? '+' : ''}${stats.sales.change}%` })
                            : t('orders.stat_sales_orders', { count: stats.sales.orders })
                    }
                />
                <button type="button" onClick={() => go('to_ship')} className="rounded-2xl text-start">
                    <StatTile icon={Truck} label={t('orders.stat_to_ship')} value={formatNumber(stats.toShip.count)} hint={t('orders.stat_to_ship_hint')} />
                </button>
                <button type="button" onClick={() => go('pending')} className="rounded-2xl text-start">
                    <StatTile
                        icon={PhoneCall}
                        tone={stats.toShip.needs_call > 0 ? 'warning' : 'default'}
                        label={t('orders.stat_needs_call')}
                        value={formatNumber(stats.toShip.needs_call)}
                        hint={t('orders.stat_needs_call_hint')}
                    />
                </button>
            </div>

            <div className="flex flex-col-reverse justify-between gap-x-4 sm:flex-row sm:items-start">
                <div className="min-w-0 flex-1">
                    <FilterTabs
                        label={t('orders.filters')}
                        active={filter}
                        onChange={(f) => go(f)}
                        options={filters.map((f) => ({ key: f, label: t(`orders.filter_${f}`), count: counts[f] }))}
                    />
                </div>
                <label className="mb-3 flex items-center gap-2 self-end text-sm sm:mb-6">
                    <span className="text-ink-muted">{t('orders.channel_label')}</span>
                    <Select value={channel ?? ''} onChange={(e) => go(filter, (e.target.value || null) as Channel | null)} className="h-9 w-auto rounded-full">
                        <option value="">{t('orders.channel_all')}</option>
                        {channels.map((c) => (
                            <option key={c} value={c}>
                                {t(`orders.channel.${c}`)}
                            </option>
                        ))}
                    </Select>
                </label>
            </div>

            {manage && selected.length > 0 ? (
                <div role="region" aria-label={t('orders.bulk_label')} className="glass-float mv-rise sticky top-4 z-20 mb-4 flex flex-wrap items-center gap-2 rounded-full px-4 py-2 shadow-md">
                    <span className="me-2 text-sm font-semibold">{t('orders.selected', { count: selected.length })}</span>
                    {bulkTargets.map((s) => (
                        <Button key={s} size="sm" variant={s === 'cancelled' ? 'danger' : 'secondary'} disabled={busy} onClick={() => bulk(s)}>
                            {t(`orders.mark_${s}`)}
                        </Button>
                    ))}
                    <Button size="sm" variant="ghost" className="ms-auto" onClick={() => setSelected([])}>
                        {t('common.cancel')}
                    </Button>
                </div>
            ) : null}

            {orders.data.length > 0 ? (
                <div className="card mv-rise overflow-hidden">
                    <div className="hidden grid-cols-[28px_minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,0.9fr)_minmax(0,0.9fr)_minmax(0,1fr)] items-center gap-4 border-b border-line px-5 py-3 text-caption text-ink-muted md:grid">
                        {manage ? (
                            <Checkbox
                                aria-label={t('orders.select_all')}
                                label={<span className="sr-only">{t('orders.select_all')}</span>}
                                checked={allOnPage}
                                onChange={() => setSelected(allOnPage ? selected.filter((id) => !pageIds.includes(id)) : Array.from(new Set([...selected, ...pageIds])))}
                            />
                        ) : (
                            <span />
                        )}
                        <span>{t('orders.col_order')}</span>
                        <span>{t('orders.col_date')}</span>
                        <span>{t('orders.col_status')}</span>
                        <span className="text-end">{t('orders.col_total')}</span>
                        <span className="text-end">{t('orders.col_where')}</span>
                    </div>
                    <ul>
                        {orders.data.map((o) => (
                            <OrderLine key={o.id} order={o} manage={manage} checked={selected.includes(o.id)} onToggle={() => toggle(o.id)} />
                        ))}
                    </ul>
                </div>
            ) : (
                <div className="card mv-rise flex flex-col items-center gap-3 px-6 py-14 text-center">
                    <span className="grid h-14 w-14 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                        <ShoppingBag className="h-6 w-6" aria-hidden strokeWidth={1.75} />
                    </span>
                    <p className="text-sm text-ink-muted">{search ? t('orders.no_results', { q: search }) : t(`orders.empty_${filter}`)}</p>
                    {manage && !search && filter === 'all' ? (
                        <ButtonLink href="/orders/create" className="mt-2">
                            <Plus className="h-4 w-4" aria-hidden />
                            {t('orders.new_first')}
                        </ButtonLink>
                    ) : null}
                </div>
            )}

            <Pagination page={orders} />
        </DashboardLayout>
    );
}

function OrderLine({ order, manage, checked, onToggle }: { order: OrderRow; manage: boolean; checked: boolean; onToggle: () => void }) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    return (
        <li
            className={cn(
                'relative grid grid-cols-[28px_minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 border-b border-line px-5 py-4 transition-colors last:border-b-0 hover:bg-secondary md:grid-cols-[28px_minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,0.9fr)_minmax(0,0.9fr)_minmax(0,1fr)]',
                checked && 'bg-accent-subtle',
            )}
        >
            <div className="relative z-10">
                {manage ? (
                    <Checkbox
                        checked={checked}
                        onChange={onToggle}
                        aria-label={t('orders.select', { number: order.number })}
                        label={<span className="sr-only">{t('orders.select', { number: order.number })}</span>}
                    />
                ) : null}
            </div>
            <div className="min-w-0">
                <Link href={`/orders/${order.id}`} className="block truncate text-sm font-semibold after:absolute after:inset-0 after:content-['']">
                    <span className="tabular text-ink-muted">#{order.number}</span> {order.customer_name || t('orders.no_name')}
                </Link>
                <p className="mt-0.5 flex items-center gap-2 truncate text-caption text-ink-muted">
                    <ChannelIcon channel={order.channel} />
                    {order.items_count !== null ? t('orders.items', { count: order.items_count }) : null}
                    {order.tags.slice(0, 2).map((tag) => (
                        <span key={tag} className="rounded-full bg-secondary px-2 py-0.5 text-[0.6875rem]">
                            {tag}
                        </span>
                    ))}
                </p>
            </div>
            <span className="hidden text-sm text-ink-muted md:block">{formatDate(order.placed_at, locale, true)}</span>
            <span className="hidden md:block">
                <OrderStatusBadge status={order.status} />
            </span>
            <span className="tabular text-end text-sm font-semibold">{formatPounds(order.total, locale)}</span>
            <span className="hidden truncate text-end text-sm text-ink-muted md:block">{order.governorate ? t(`gov.${order.governorate}`) : '—'}</span>
            <span className="col-start-2 md:hidden">
                <OrderStatusBadge status={order.status} />
            </span>
        </li>
    );
}
