// Analytics: KPIs, sales trend, traffic sources and demand. FR-ANA-01 to FR-ANA-05. Preview with sample data.
import { Eye, MousePointerClick, Percent, Receipt, Repeat, ShoppingBag } from 'lucide-react';
import { useState } from 'react';
import { FilterTabs, StatTile } from '@/components/commerce';
import { Meter, PlannedNotice, PreviewCard, PreviewTable } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatNumber, useLocale, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { sampleDaily, sampleTopProducts, useBi } from '@/lib/samples';

type Range = '7d' | '30d' | '90d';

const sources = [
    { key: 'instagram', visits: 1840, conv: 2.1 },
    { key: 'facebook', visits: 1210, conv: 1.4 },
    { key: 'search', visits: 760, conv: 3.6 },
    { key: 'direct', visits: 520, conv: 4.2 },
    { key: 'whatsapp', visits: 310, conv: 5.0 },
];

/** Bar chart with one bar per day. Decorative bars, with the summary given to screen readers. */
function DailyBars({ values, label }: { values: number[]; label: string }) {
    const max = Math.max(...values);
    return (
        <div role="img" aria-label={label} className="flex h-40 items-end gap-1.5 sm:gap-2">
            {values.map((v, i) => (
                <div key={i} className="flex-1 rounded-t-md bg-chart-1 opacity-80 transition-opacity hover:opacity-100" style={{ height: `${(v / max) * 100}%` }} />
            ))}
        </div>
    );
}

export default function Analytics() {
    const t = useT();
    const bi = useBi();
    const locale = useLocale();
    const [range, setRange] = useState<Range>('30d');
    const total = sampleDaily.reduce((a, b) => a + b, 0);
    const maxVisits = Math.max(...sources.map((s) => s.visits));

    return (
        <DashboardLayout title={t('nav.analytics')}>
            <PageHeader title={t('analytics.title')} description={t('analytics.subtitle')} />
            <PlannedNotice requirements={['FR-ANA-01', 'FR-ANA-02', 'FR-ANA-04', 'FR-ANA-05']} />

            <FilterTabs label={t('analytics.range')} active={range} onChange={setRange} options={(['7d', '30d', '90d'] as Range[]).map((k) => ({ key: k, label: t(`analytics.range_${k}`) }))} />

            <div className="mb-6 grid grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-3 2xl:grid-cols-6">
                <StatTile icon={Eye} label={t('analytics.visitors')} value={formatNumber(4640)} hint="+12%" />
                <StatTile icon={ShoppingBag} label={t('analytics.orders')} value="118" hint="+8%" />
                <StatTile icon={Receipt} label={t('analytics.revenue')} value={formatPounds(9020000, locale)} hint="+15%" />
                <StatTile icon={Percent} label={t('analytics.conversion')} value="2.5%" hint="−0.2" />
                <StatTile icon={MousePointerClick} label={t('analytics.aov')} value={formatPounds(76400, locale)} hint="+4%" />
                <StatTile icon={Repeat} label={t('analytics.returning')} value="31%" hint="+3" />
            </div>

            <div className="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <PreviewCard title={t('analytics.sales_trend')} description={t('analytics.sales_trend_hint')}>
                    <DailyBars values={sampleDaily} label={t('analytics.chart_label', { total: formatNumber(Math.round(total * 1000)) })} />
                    <div className="mt-2 flex justify-between text-caption text-ink-muted">
                        <span>{t('analytics.days_ago', { count: 14 })}</span>
                        <span>{t('analytics.today')}</span>
                    </div>
                </PreviewCard>

                <PreviewCard title={t('analytics.sources')} description={t('analytics.sources_hint')}>
                    <ul className="space-y-4">
                        {sources.map((s) => (
                            <li key={s.key}>
                                <div className="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                                    <span>{t(`analytics.source_${s.key}`)}</span>
                                    <span className="tabular text-caption text-ink-muted">
                                        {formatNumber(s.visits)} · {s.conv}%
                                    </span>
                                </div>
                                <Meter value={(s.visits / maxVisits) * 100} label={t(`analytics.source_${s.key}`)} />
                            </li>
                        ))}
                    </ul>
                </PreviewCard>
            </div>

            <PreviewCard title={t('analytics.demand')} description={t('analytics.demand_hint')}>
                <PreviewTable
                    caption={t('analytics.demand')}
                    columns={[
                        { key: 'name', label: t('analytics.col_product') },
                        { key: 'units', label: t('analytics.col_units'), align: 'end' },
                        { key: 'revenue', label: t('analytics.col_revenue'), align: 'end' },
                        { key: 'flag', label: t('analytics.col_signal'), align: 'end' },
                    ]}
                    rows={sampleTopProducts.map((p, i) => ({
                        id: p.id,
                        name: <span className="font-medium">{bi(p.name)}</span>,
                        units: formatNumber(p.units),
                        revenue: formatPounds(p.revenue, locale),
                        flag: i === 0 ? <Badge tone="warning">{t('analytics.signal_low')}</Badge> : i === 2 ? <Badge tone="info">{t('analytics.signal_viewed')}</Badge> : <span className="text-ink-muted">—</span>,
                    }))}
                />
            </PreviewCard>
        </DashboardLayout>
    );
}
