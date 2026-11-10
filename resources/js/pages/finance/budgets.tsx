// Budgets and planning: budget vs actual per account, what-if on last month's real numbers,
// break-even and a pricing calculator. FR-FPA-01, FR-FPA-02, FR-FPA-03, FR-FPA-05
import { router, useForm } from '@inertiajs/react';
import { Copy, Save, SlidersHorizontal } from 'lucide-react';
import { useMemo, useState } from 'react';
import { StatTile } from '@/components/commerce';
import { Amount, formatMonth, useAccountName, useMoney } from '@/components/finance';
import { Meter } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { useLocale, useT } from '@/lib/i18n';
import { useCan } from '@/lib/permissions';

interface Row {
    account_id: number;
    code: string;
    name_en: string;
    name_ar: string;
    kind: 'revenue' | 'contra' | 'cost';
    budget: number;
    expected: number;
    actual: number;
    variance: number;
    pct: number | null;
    favourable: boolean;
}

interface Props {
    month: string;
    currentMonth: string;
    report: { month: string; rows: Row[]; totals: { revenue_budget: number; revenue_actual: number; cost_budget: number; cost_actual: number; profit_budget: number; profit_actual: number }; has_budget: boolean; running: boolean; share: number };
    breakEven: { fixed: number; variable_ratio: number | null; break_even: number | null; month_sales: number; progress: number | null; average_order: number | null; orders_needed: number | null; basis_months: number };
    whatIf: { month: string; revenue: number; cogs: number; variable: number; fixed: number; marketing: number; profit: number };
}

const pounds = (p: number) => (p ? String(p / 100) : '');

export default function FinanceBudgets({ month, report, breakEven, whatIf }: Props) {
    const t = useT();
    const locale = useLocale();
    const money = useMoney();
    const can = useCan();
    const edit = can('budgets.manage');
    const [picked, setPicked] = useState(month);
    const form = useForm({ month, amounts: Object.fromEntries(report.rows.map((r) => [r.account_id, pounds(r.budget)])) as Record<number, string> });
    const dirty = report.rows.some((r) => (form.data.amounts[r.account_id] ?? '') !== pounds(r.budget));
    const sections = (['revenue', 'contra', 'cost'] as const).map((k) => [k, report.rows.filter((r) => r.kind === k)] as const).filter(([, rows]) => rows.length);

    return (
        <DashboardLayout title={t('nav.finance_budgets')}>
            <PageHeader
                title={t('budgets.title')}
                description={t('budgets.subtitle')}
                action={
                    <form
                        className="flex items-center gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get('/finance/budgets', { month: picked });
                        }}
                    >
                        <Input type="month" aria-label={t('budgets.month')} value={picked} onChange={(e) => setPicked(e.target.value)} className="!w-auto rounded-full" />
                        <Button type="submit" variant="secondary">
                            {t('fin.apply')}
                        </Button>
                    </form>
                }
            />

            <div className="mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile label={t('budgets.revenue_vs')} value={money(report.totals.revenue_actual)} hint={t('budgets.of_plan', { amount: money(report.totals.revenue_budget) })} />
                <StatTile label={t('budgets.costs_vs')} value={money(report.totals.cost_actual)} tone={report.totals.cost_budget > 0 && report.totals.cost_actual > report.totals.cost_budget ? 'warning' : 'default'} hint={t('budgets.of_plan', { amount: money(report.totals.cost_budget) })} />
                <StatTile label={t('budgets.profit_vs')} value={money(report.totals.profit_actual)} tone={report.totals.profit_actual < 0 ? 'danger' : 'default'} hint={t('budgets.of_plan', { amount: money(report.totals.profit_budget) })} />
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                <Card>
                    <CardHeader
                        title={t('budgets.vs_actual_month', { month: formatMonth(month, locale) })}
                        description={report.running ? t('budgets.month_running', { pct: Math.round(report.share * 100) }) : t('budgets.vs_actual_hint')}
                        action={
                            edit ? (
                                <Button size="sm" variant="secondary" onClick={() => router.post('/finance/budgets/copy', { month }, { preserveScroll: true })}>
                                    <Copy className="h-3.5 w-3.5" aria-hidden />
                                    {t('budgets.copy')}
                                </Button>
                            ) : null
                        }
                    />
                    <CardBody>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.put('/finance/budgets', { preserveScroll: true });
                            }}
                        >
                            {!report.has_budget ? <p className="mb-4 rounded-2xl bg-info-subtle p-3 text-sm text-info">{t('budgets.no_budget')}</p> : null}
                            {sections.map(([kind, rows]) => (
                                <section key={kind} className="mb-5">
                                    <h3 className="mb-2 text-caption font-semibold text-ink-muted">{t(`budgets.kind_${kind}`)}</h3>
                                    <ul className="space-y-4">
                                        {rows.map((r) => (
                                            <BudgetLine key={r.account_id} row={r} value={form.data.amounts[r.account_id] ?? ''} onChange={(v) => form.setData('amounts', { ...form.data.amounts, [r.account_id]: v })} edit={edit} error={(form.errors as Record<string, string>)[`amounts.${r.account_id}`]} />
                                        ))}
                                    </ul>
                                </section>
                            ))}
                            {edit ? (
                                <div className={cn('flex items-center gap-3', dirty && 'glass sticky bottom-24 z-10 -mx-2 rounded-full p-2 lg:bottom-4')}>
                                    <Button type="submit" disabled={!dirty || form.processing}>
                                        <Save className="h-4 w-4" aria-hidden />
                                        {t('budgets.save')}
                                    </Button>
                                    {dirty ? <span className="text-caption text-ink-muted">{t('budgets.unsaved')}</span> : null}
                                </div>
                            ) : null}
                        </form>
                    </CardBody>
                </Card>

                <div className="space-y-6">
                    <WhatIf base={whatIf} />
                    <Card>
                        <CardHeader title={t('finance.breakeven')} description={t('budgets.breakeven_hint', { months: breakEven.basis_months })} />
                        <CardBody>
                            {breakEven.break_even !== null ? (
                                <>
                                    <p className="font-display text-xl">
                                        <Amount value={breakEven.break_even} />
                                        <span className="ms-1 text-sm text-ink-muted">{t('budgets.per_month')}</span>
                                    </p>
                                    <dl className="mt-3 space-y-1.5 text-sm">
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-ink-muted">{t('budgets.fixed_costs')}</dt>
                                            <dd>
                                                <Amount value={breakEven.fixed} />
                                            </dd>
                                        </div>
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-ink-muted">{t('budgets.variable_ratio')}</dt>
                                            <dd dir="ltr" className="tabular">
                                                {breakEven.variable_ratio !== null ? `${Math.round(breakEven.variable_ratio * 1000) / 10}%` : '—'}
                                            </dd>
                                        </div>
                                    </dl>
                                    <div className="mt-3">
                                        <Meter value={breakEven.progress ?? 0} tone={(breakEven.progress ?? 0) >= 100 ? 'success' : 'accent'} label={t('finance.breakeven')} />
                                    </div>
                                    <p className="mt-2 text-caption text-ink-muted">{t('finance.sold_this_month', { amount: money(breakEven.month_sales) })}</p>
                                    <p className="mt-3 text-caption text-ink-muted">{t('budgets.breakeven_formula')}</p>
                                </>
                            ) : (
                                <p className="text-sm text-ink-muted">{t('finance.breakeven_none')}</p>
                            )}
                        </CardBody>
                    </Card>
                    <PriceCalculator />
                </div>
            </div>
        </DashboardLayout>
    );
}

function BudgetLine({ row, value, onChange, edit, error }: { row: Row; value: string; onChange: (v: string) => void; edit: boolean; error?: string }) {
    const t = useT();
    const name = useAccountName();
    const id = `budget-${row.account_id}`;
    const pct = row.expected > 0 ? (row.actual / row.expected) * 100 : 0;
    const tone = row.budget === 0 ? 'accent' : row.favourable ? (row.kind === 'cost' && pct > 90 ? 'warning' : 'success') : 'danger';
    return (
        <li className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem] sm:items-center sm:gap-4">
            <div className="min-w-0">
                <div className="mb-1.5 flex flex-wrap items-baseline justify-between gap-2 text-sm">
                    <label htmlFor={id} className="font-medium">
                        {name(row)}
                    </label>
                    <span className="text-caption text-ink-muted">
                        {t('budgets.actual')} <Amount value={row.actual} className="text-ink" />
                        {row.budget > 0 ? (
                            <Badge tone={row.favourable ? 'success' : 'danger'} className="ms-2">
                                <span dir="ltr">
                                    {row.variance > 0 ? '+' : ''}
                                    {Math.round(pct - 100)}%
                                </span>
                                <span className="sr-only">{row.favourable ? t('budgets.favourable') : t('budgets.unfavourable')}</span>
                            </Badge>
                        ) : null}
                    </span>
                </div>
                <Meter value={row.budget > 0 ? pct : 0} tone={tone} label={name(row)} />
            </div>
            {edit ? (
                <div>
                    <Input id={id} inputMode="decimal" dir="ltr" placeholder={t('budgets.plan')} value={value} onChange={(e) => onChange(e.target.value)} invalid={!!error} />
                    {error ? <p className="mt-1 text-xs text-danger">{error}</p> : null}
                </div>
            ) : (
                <Amount value={row.budget} className="text-end text-sm" />
            )}
        </li>
    );
}

/**
 * What-if on last month's real numbers. Price moves revenue; volume moves revenue, cost of goods and
 * the per-order variable costs (fees, shipping, packaging); unit cost moves cost of goods; marketing
 * is a fixed cost you choose:
 *   profit' = R(1+p)(1+v) − COGS(1+c)(1+v) − V(1+v) − F − M(1+m)
 */
function WhatIf({ base }: { base: Props['whatIf'] }) {
    const t = useT();
    const locale = useLocale();
    const [price, setPrice] = useState(0);
    const [volume, setVolume] = useState(0);
    const [cost, setCost] = useState(0);
    const [mkt, setMkt] = useState(0);

    const result = useMemo(() => {
        const p = price / 100;
        const v = volume / 100;
        const c = cost / 100;
        const m = mkt / 100;
        const revenue = base.revenue * (1 + p) * (1 + v);
        const cogs = base.cogs * (1 + c) * (1 + v);
        const variable = base.variable * (1 + v);
        const marketing = base.marketing * (1 + m);
        const profit = Math.round(revenue - cogs - variable - base.fixed - marketing);
        return { revenue: Math.round(revenue), profit, margin: revenue > 0 ? Math.round(((revenue - cogs) / revenue) * 1000) / 10 : null };
    }, [base, price, volume, cost, mkt]);

    const sliders: Array<[string, number, (n: number) => void, number, number]> = [
        ['price', price, setPrice, -30, 30],
        ['volume', volume, setVolume, -50, 50],
        ['unit_cost', cost, setCost, -30, 30],
        ['marketing', mkt, setMkt, -100, 100],
    ];

    return (
        <Card>
            <CardHeader title={t('budgets.whatif')} description={t('budgets.whatif_base', { month: formatMonth(base.month, locale) })} />
            <CardBody>
                {base.revenue === 0 ? (
                    <p className="text-sm text-ink-muted">{t('budgets.whatif_empty')}</p>
                ) : (
                    <>
                        <div className="space-y-4">
                            {sliders.map(([key, value, set, min, max]) => (
                                <div key={key}>
                                    <label htmlFor={`wi-${key}`} className="flex items-center justify-between text-sm">
                                        <span className="inline-flex items-center gap-2">
                                            {key === 'price' ? <SlidersHorizontal className="h-4 w-4 text-ink-muted" aria-hidden /> : null}
                                            {t(`budgets.wi_${key}`)}
                                        </span>
                                        <span className="tabular font-semibold" dir="ltr">
                                            {value > 0 ? '+' : ''}
                                            {value}%
                                        </span>
                                    </label>
                                    <input id={`wi-${key}`} type="range" min={min} max={max} step={1} value={value} onChange={(e) => set(Number(e.target.value))} className="mt-2 w-full accent-[var(--mv-brand-accent)]" />
                                </div>
                            ))}
                        </div>
                        <dl className="mt-5 grid grid-cols-2 gap-3 rounded-2xl bg-secondary p-4" aria-live="polite">
                            <div>
                                <dt className="text-caption text-ink-muted">{t('budgets.wi_revenue')}</dt>
                                <dd className="font-display text-base">
                                    <Amount value={result.revenue} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-caption text-ink-muted">{t('budgets.profit_after')}</dt>
                                <dd className={cn('font-display text-base', result.profit >= 0 ? 'text-success' : 'text-danger')}>
                                    <Amount value={result.profit} />
                                </dd>
                            </div>
                            <div className="col-span-2 text-caption text-ink-muted">
                                {t('budgets.wi_vs', { amount: '' })}
                                <Amount value={result.profit - base.profit} signed />
                                {result.margin !== null ? ` · ${t('budgets.wi_margin', { pct: result.margin })}` : ''}
                            </div>
                        </dl>
                        <div className="mt-3 flex items-center justify-between gap-3">
                            <p className="text-caption text-ink-muted">{t('budgets.whatif_note')}</p>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                    setPrice(0);
                                    setVolume(0);
                                    setCost(0);
                                    setMkt(0);
                                }}
                            >
                                {t('budgets.reset')}
                            </Button>
                        </div>
                    </>
                )}
            </CardBody>
        </Card>
    );
}

/** p = c / (1 − m): the price that leaves margin m after unit cost c (Documentation §6.2). */
function PriceCalculator() {
    const t = useT();
    const money = useMoney();
    const [cost, setCost] = useState('');
    const [margin, setMargin] = useState('50');
    const c = Number(cost.replace(/,/g, '')) * 100;
    const m = Number(margin) / 100;
    const price = c > 0 && m >= 0 && m < 1 ? Math.round(c / (1 - m)) : null;
    const vat = price !== null ? Math.round(price * 1.14) : null;
    return (
        <Card>
            <CardHeader title={t('budgets.price_title')} description={t('budgets.price_hint')} />
            <CardBody>
                <div className="grid grid-cols-2 gap-3">
                    <Field label={t('budgets.unit_cost')} htmlFor="pc-cost">
                        <Input id="pc-cost" inputMode="decimal" dir="ltr" value={cost} onChange={(e) => setCost(e.target.value)} placeholder="300" />
                    </Field>
                    <Field label={t('budgets.target_margin')} htmlFor="pc-margin">
                        <Input id="pc-margin" inputMode="decimal" dir="ltr" value={margin} onChange={(e) => setMargin(e.target.value)} />
                    </Field>
                </div>
                <p className="mt-4 text-sm" aria-live="polite">
                    {price !== null ? (
                        <>
                            {t('budgets.suggested')} <strong className="font-display text-lg">{money(price)}</strong>
                            <span className="block text-caption text-ink-muted">{t('budgets.with_vat', { amount: money(vat ?? 0) })}</span>
                        </>
                    ) : (
                        <span className="text-ink-muted">{t('budgets.price_enter')}</span>
                    )}
                </p>
            </CardBody>
        </Card>
    );
}
