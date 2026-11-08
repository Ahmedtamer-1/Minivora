// Customers list, built from orders: who buys, how often, how much. Search and simple segments.
import { Link, router, usePage } from '@inertiajs/react';
import { Repeat, Users, Wallet } from 'lucide-react';
import { FilterTabs, Initials, Pagination, SearchField, StatTile, useSearchVisit, type CustomerCard } from '@/components/commerce';
import { Badge } from '@/components/ui/badge';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatDate, formatNumber, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import type { Paginated, SharedProps } from '@/types';

type Filter = 'all' | 'repeat' | 'new' | 'inactive';

interface Props {
    customers: Paginated<CustomerCard>;
    filter: Filter;
    search: string;
    counts: Record<Filter, number>;
    stats: { total_spent: number; repeat_rate: number | null };
}

const filters: Filter[] = ['all', 'repeat', 'new', 'inactive'];

export default function CustomersIndex({ customers, filter, search, counts, stats }: Props) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const [q, setQ] = useSearchVisit('/customers', search, { filter: filter === 'all' ? undefined : filter });

    const go = (f: Filter) => router.get('/customers', { filter: f === 'all' ? undefined : f, q: q || undefined }, { preserveScroll: true });

    return (
        <DashboardLayout title={t('nav.customers')}>
            <PageHeader
                title={t('customers.title')}
                description={t('customers.subtitle', { count: formatNumber(counts.all) })}
                action={<SearchField value={q} onChange={setQ} label={t('customers.search')} />}
            />

            <div className="mv-stagger mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile icon={Users} label={t('customers.stat_total')} value={formatNumber(counts.all)} hint={t('customers.stat_new', { count: counts.new })} />
                <StatTile icon={Repeat} label={t('customers.stat_repeat')} value={stats.repeat_rate !== null ? `${stats.repeat_rate}%` : '—'} hint={t('customers.stat_repeat_hint', { count: counts.repeat })} />
                <StatTile icon={Wallet} label={t('customers.stat_spent')} value={formatPounds(stats.total_spent, locale)} hint={t('customers.stat_spent_hint')} />
            </div>

            <FilterTabs label={t('customers.filters')} active={filter} onChange={go} options={filters.map((f) => ({ key: f, label: t(`customers.filter_${f}`), count: counts[f] }))} />

            {customers.data.length > 0 ? (
                <ul className="mv-stagger grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {customers.data.map((c) => (
                        <li key={c.id}>
                            <Link href={`/customers/${c.id}`} className="card flex h-full flex-col gap-4 p-5">
                                <div className="flex items-center gap-3">
                                    <Initials name={c.name} />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold">{c.name || t('orders.no_name')}</p>
                                        <p className="truncate text-caption text-ink-muted" dir="ltr">
                                            {c.phone ?? c.email ?? '—'}
                                        </p>
                                    </div>
                                    {c.orders_count >= 2 ? <Badge tone="success">{t('customers.repeat_badge')}</Badge> : null}
                                </div>
                                <dl className="mt-auto grid grid-cols-3 gap-2 border-t border-line pt-3 text-center">
                                    <div>
                                        <dt className="text-[11px] text-ink-muted">{t('customers.orders')}</dt>
                                        <dd className="tabular text-sm font-semibold">{formatNumber(c.orders_count)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-[11px] text-ink-muted">{t('customers.spent')}</dt>
                                        <dd className="tabular truncate text-sm font-semibold">{formatPounds(c.total_spent, locale)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-[11px] text-ink-muted">{t('customers.last')}</dt>
                                        <dd className="truncate text-sm">{c.last_order_at ? formatDate(c.last_order_at, locale) : '—'}</dd>
                                    </div>
                                </dl>
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <div className="card mv-rise flex flex-col items-center gap-3 px-6 py-14 text-center">
                    <span className="grid h-14 w-14 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                        <Users className="h-6 w-6" aria-hidden strokeWidth={1.75} />
                    </span>
                    <p className="max-w-sm text-sm text-ink-muted">{search ? t('customers.no_results', { q: search }) : t(`customers.empty_${filter}`)}</p>
                </div>
            )}

            <Pagination page={customers} />
        </DashboardLayout>
    );
}
