// Budgets, variances and what-if. FR-FPA-01, FR-FPA-02, FR-FPA-03, FR-FPA-05. Preview.
import { usePage } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { Meter, PlannedNotice, PreviewCard } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { sampleBudgets, useBi } from '@/lib/samples';
import type { SharedProps } from '@/types';

export default function FinanceBudgets() {
    const t = useT();
    const bi = useBi();
    const { locale } = usePage<SharedProps>().props;
    // What-if: the only live control on this preview, to show how the scenario panel will feel.
    const [price, setPrice] = useState(0);
    // Sample month: revenue 90,200 EGP, cost of goods 36,100, other costs 15,910. Each 1% on price
    // loses about 1.2% of orders (a typical elasticity for small fashion stores).
    const volume = 1 - (1.2 * price) / 100;
    const scenario = Math.round(9020000 * (1 + price / 100) * volume - 3610000 * volume - 1591000);

    return (
        <DashboardLayout title={t('nav.finance_budgets')}>
            <PageHeader title={t('budgets.title')} description={t('budgets.subtitle')} />
            <PlannedNotice requirements={['FR-FPA-01', 'FR-FPA-02', 'FR-FPA-03', 'FR-FPA-05']} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <PreviewCard title={t('budgets.vs_actual')} description={t('budgets.vs_actual_hint')}>
                    <ul className="space-y-5">
                        {sampleBudgets.map((b) => {
                            const pct = (b.actual / b.budget) * 100;
                            const over = pct > 100;
                            return (
                                <li key={b.id}>
                                    <div className="mb-1.5 flex flex-wrap items-baseline justify-between gap-2 text-sm">
                                        <span className="font-medium">{bi(b.account)}</span>
                                        <span className="tabular text-caption text-ink-muted">
                                            {formatPounds(b.actual, locale)} / {formatPounds(b.budget, locale)}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <div className="flex-1">
                                            <Meter value={pct} tone={over ? 'danger' : pct > 90 ? 'warning' : 'accent'} label={bi(b.account)} />
                                        </div>
                                        {over ? <Badge tone="danger">{t('budgets.over', { pct: Math.round(pct - 100) })}</Badge> : <span className="tabular w-12 text-end text-caption text-ink-muted">{Math.round(pct)}%</span>}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </PreviewCard>

                <PreviewCard title={t('budgets.whatif')} description={t('budgets.whatif_hint')}>
                    <label htmlFor="whatif-price" className="flex items-center justify-between text-sm">
                        <span className="inline-flex items-center gap-2">
                            <SlidersHorizontal className="h-4 w-4 text-ink-muted" aria-hidden />
                            {t('budgets.price_change')}
                        </span>
                        <span className="tabular font-semibold" dir="ltr">
                            {price > 0 ? '+' : ''}
                            {price}%
                        </span>
                    </label>
                    <input id="whatif-price" type="range" min={-20} max={20} step={1} value={price} onChange={(e) => setPrice(Number(e.target.value))} className="mt-3 w-full accent-[var(--mv-brand-accent)]" />
                    <dl className="mt-5 rounded-2xl bg-secondary p-4">
                        <dt className="text-caption text-ink-muted">{t('budgets.profit_after')}</dt>
                        <dd className="tabular mt-1 font-display text-xl" aria-live="polite">
                            {formatPounds(scenario, locale)}
                        </dd>
                    </dl>
                    <p className="mt-3 text-caption text-ink-muted">{t('budgets.whatif_note')}</p>
                </PreviewCard>
            </div>
        </DashboardLayout>
    );
}
