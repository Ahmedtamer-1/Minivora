// Finance overview: profit, cash and the 3-month forecast, all from the ledger. UC-11, FR-LED-04, FR-FPA-04, FR-AI-03. Preview.
import { usePage } from '@inertiajs/react';
import { Banknote, Landmark, Percent, Sparkles, TrendingUp } from 'lucide-react';
import { StatTile } from '@/components/commerce';
import { Meter, PlannedNotice, PreviewCard } from '@/components/planned';
import { Button } from '@/components/ui/button';
import { openAi } from '@/components/ai-orb';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import type { SharedProps } from '@/types';

const pl = [
    { key: 'revenue', amount: 9020000 },
    { key: 'cogs', amount: -3610000 },
    { key: 'fees', amount: -180000 },
    { key: 'shipping', amount: -470000 },
    { key: 'marketing', amount: -341000 },
    { key: 'rent', amount: -600000 },
];

const forecast = [
    { key: 'm1', cash: 4100000 },
    { key: 'm2', cash: 4650000 },
    { key: 'm3', cash: 5300000 },
];

export default function FinanceOverview() {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const net = pl.reduce((s, r) => s + r.amount, 0);
    const maxCash = Math.max(...forecast.map((f) => f.cash));

    return (
        <DashboardLayout title={t('nav.finance')}>
            <PageHeader
                title={t('finance.title')}
                description={t('finance.subtitle')}
                action={
                    <Button variant="glass" onClick={(e) => openAi(e.currentTarget)}>
                        <Sparkles className="h-4 w-4" aria-hidden />
                        {t('finance.ask')}
                    </Button>
                }
            />
            <PlannedNotice requirements={['UC-11', 'FR-LED-04', 'FR-FPA-04', 'FR-AI-03']} />

            <div className="mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile icon={TrendingUp} label={t('finance.net_profit')} value={formatPounds(net, locale)} hint={t('finance.this_month')} />
                <StatTile icon={Landmark} label={t('finance.cash')} value={formatPounds(3720000, locale)} hint={t('finance.cash_hint')} />
                <StatTile icon={Percent} label={t('finance.margin')} value="60%" hint={t('finance.margin_hint')} />
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <PreviewCard title={t('finance.pl_title')} description={t('finance.pl_hint')}>
                    <dl className="divide-y divide-line">
                        {pl.map((r) => (
                            <div key={r.key} className="flex min-h-[48px] items-center justify-between gap-3">
                                <dt className="text-sm">{t(`finance.line_${r.key}`)}</dt>
                                <dd dir="ltr" className={`tabular text-sm ${r.amount < 0 ? 'text-ink-muted' : ''}`}>{formatPounds(r.amount, locale)}</dd>
                            </div>
                        ))}
                        <div className="flex min-h-[56px] items-center justify-between gap-3">
                            <dt className="font-display text-base">{t('finance.net_profit')}</dt>
                            <dd dir="ltr" className="tabular font-display text-lg text-success">{formatPounds(net, locale)}</dd>
                        </div>
                    </dl>
                    <p className="mt-3 text-caption text-ink-muted">{t('finance.source')}</p>
                </PreviewCard>

                <div className="space-y-6">
                    <PreviewCard title={t('finance.forecast')} description={t('finance.forecast_hint')}>
                        <ul className="space-y-4">
                            {forecast.map((f) => (
                                <li key={f.key}>
                                    <div className="mb-1.5 flex justify-between text-sm">
                                        <span>{t(`finance.${f.key}`)}</span>
                                        <span className="tabular">{formatPounds(f.cash, locale)}</span>
                                    </div>
                                    <Meter value={(f.cash / maxCash) * 100} tone="success" label={t(`finance.${f.key}`)} />
                                </li>
                            ))}
                        </ul>
                    </PreviewCard>
                    <PreviewCard title={t('finance.breakeven')}>
                        <div className="flex items-center gap-3">
                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-success-subtle text-success">
                                <Banknote className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                            </span>
                            <p className="text-sm">{t('finance.breakeven_body', { amount: formatPounds(3950000, locale) })}</p>
                        </div>
                    </PreviewCard>
                </div>
            </div>
        </DashboardLayout>
    );
}
