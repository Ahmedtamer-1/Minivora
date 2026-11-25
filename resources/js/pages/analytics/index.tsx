// Live Analytics dashboard: KPIs (visitors, orders, revenue, conversion, AOV, returning buyers)
// and Product Demand ranking with Sold out & wanted signal. FR-ANA-01 to FR-ANA-05.
import { router } from '@inertiajs/react';
import { Eye, MousePointerClick, Percent, Receipt, Repeat, ShoppingBag } from 'lucide-react';
import { FilterTabs, StatTile } from '@/components/commerce';
import { Badge } from '@/components/ui/badge';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatNumber, useLocale, useT } from '@/lib/i18n';
import { formatPiasters } from '@/lib/money';

type Range = '7d' | '30d' | '90d';

interface KpiSummary {
    period: { from: string; to: string };
    visitors: number;
    page_views: number;
    orders: number;
    revenue: number;
    conversion_rate: number;
    aov: number;
    returning_buyer_rate: number;
}

interface DemandRow {
    product_id: number;
    name: string;
    stock: number;
    price: number;
    views: number;
    cart_adds: number;
    units_sold: number;
    demand_score: number;
    sold_out_wanted: boolean;
}

interface AnalyticsProps {
    days?: number;
    kpis: KpiSummary;
    demand: DemandRow[];
}

const rangeToDays: Record<Range, number> = { '7d': 7, '30d': 30, '90d': 90 };

function daysToRange(days?: number): Range {
    if (days === 7) return '7d';
    if (days === 90) return '90d';
    return '30d';
}

export default function Analytics({ days = 30, kpis, demand = [] }: AnalyticsProps) {
    const t = useT();
    const locale = useLocale();
    const activeRange = daysToRange(days);

    const handleRangeChange = (next: Range) => {
        router.get('/analytics', { days: rangeToDays[next] }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const periodHint = kpis?.period ? `${kpis.period.from} → ${kpis.period.to}` : undefined;

    return (
        <DashboardLayout title={t('nav.analytics')}>
            <PageHeader title={t('analytics.title')} description={t('analytics.subtitle')} />

            <FilterTabs
                label={t('analytics.range')}
                active={activeRange}
                onChange={handleRangeChange}
                options={(['7d', '30d', '90d'] as Range[]).map((k) => ({ key: k, label: t(`analytics.range_${k}`) }))}
            />

            <div className="mb-6 grid grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-3 2xl:grid-cols-6">
                <StatTile
                    icon={Eye}
                    label={t('analytics.visitors')}
                    value={formatNumber(kpis?.visitors ?? 0)}
                    hint={`${formatNumber(kpis?.page_views ?? 0)} page views`}
                />
                <StatTile
                    icon={ShoppingBag}
                    label={t('analytics.orders')}
                    value={formatNumber(kpis?.orders ?? 0)}
                    hint={periodHint}
                />
                <StatTile
                    icon={Receipt}
                    label={t('analytics.revenue')}
                    value={formatPiasters(kpis?.revenue ?? 0, locale)}
                    hint={periodHint}
                />
                <StatTile
                    icon={Percent}
                    label={t('analytics.conversion')}
                    value={`${(kpis?.conversion_rate ?? 0).toFixed(2)}%`}
                />
                <StatTile
                    icon={MousePointerClick}
                    label={t('analytics.aov')}
                    value={formatPiasters(kpis?.aov ?? 0, locale)}
                />
                <StatTile
                    icon={Repeat}
                    label={t('analytics.returning')}
                    value={`${(kpis?.returning_buyer_rate ?? 0).toFixed(1)}%`}
                />
            </div>

            <Card>
                <CardHeader title={t('analytics.demand')} description={t('analytics.demand_hint')} />
                <CardBody>
                    {demand.length === 0 ? (
                        <p className="py-8 text-center text-sm text-ink-muted">
                            No product demand signals yet. Once shoppers browse, add to cart, or order products, they will be ranked here.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-start text-sm">
                                <caption className="sr-only">{t('analytics.demand')}</caption>
                                <thead className="border-b border-line text-caption text-ink-muted">
                                    <tr>
                                        <th scope="col" className="pb-3 pe-4 text-start font-medium">
                                            {t('analytics.col_product')}
                                        </th>
                                        <th scope="col" className="pb-3 px-3 text-end font-medium">
                                            Price
                                        </th>
                                        <th scope="col" className="pb-3 px-3 text-end font-medium">
                                            Stock
                                        </th>
                                        <th scope="col" className="pb-3 px-3 text-end font-medium">
                                            Views
                                        </th>
                                        <th scope="col" className="pb-3 px-3 text-end font-medium">
                                            Cart adds
                                        </th>
                                        <th scope="col" className="pb-3 px-3 text-end font-medium">
                                            {t('analytics.col_units')}
                                        </th>
                                        <th scope="col" className="pb-3 ps-3 text-end font-medium">
                                            {t('analytics.col_signal')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {demand.map((row) => (
                                        <tr key={row.product_id}>
                                            <td className="py-3 pe-4 font-medium text-ink">{row.name}</td>
                                            <td className="tabular py-3 px-3 text-end text-ink-muted">{formatPiasters(row.price, locale)}</td>
                                            <td className="tabular py-3 px-3 text-end">
                                                <span className={row.stock === 0 ? 'font-semibold text-danger' : 'text-ink'}>{formatNumber(row.stock)}</span>
                                            </td>
                                            <td className="tabular py-3 px-3 text-end text-ink-muted">{formatNumber(row.views)}</td>
                                            <td className="tabular py-3 px-3 text-end text-ink-muted">{formatNumber(row.cart_adds)}</td>
                                            <td className="tabular py-3 px-3 text-end font-medium text-ink">{formatNumber(row.units_sold)}</td>
                                            <td className="py-3 ps-3 text-end">
                                                {row.sold_out_wanted ? (
                                                    <Badge tone="danger">Sold out &amp; wanted</Badge>
                                                ) : row.stock > 0 && row.stock <= 5 && row.demand_score > 0 ? (
                                                    <Badge tone="warning">{t('analytics.signal_low')}</Badge>
                                                ) : row.views > 0 && row.units_sold === 0 ? (
                                                    <Badge tone="info">{t('analytics.signal_viewed')}</Badge>
                                                ) : (
                                                    <span className="text-ink-muted">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardBody>
            </Card>
        </DashboardLayout>
    );
}
