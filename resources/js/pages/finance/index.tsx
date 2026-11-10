// Finance overview: profit, money on hand, the 12-month trend, 3-month cash forecast, break-even and
// what needs attention, all computed from the ledger. UC-11, FR-LED-04, FR-FPA-04, FR-AI-03
import { Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, BookOpen, CalendarCheck, Info, Landmark, Percent, Receipt, RefreshCw, Sparkles, Target, TrendingUp, Wallet } from 'lucide-react';
import { StatTile } from '@/components/commerce';
import { openAi } from '@/components/ai-orb';
import { Amount, CashChart, Delta, EmptyFinance, PeriodPicker, RevenueCostChart, ShareBars, formatMonth, useAccountName, useMoney, type MonthPoint, type Period, type ProfitAndLoss } from '@/components/finance';
import { Meter } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { Button, ButtonLink } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { useLocale, useT } from '@/lib/i18n';
import { useCan } from '@/lib/permissions';

interface Alert {
    key: 'low_cash' | 'overdue_bills' | 'unsynced' | 'close_month' | 'payout' | 'vat';
    tone: 'danger' | 'warning' | 'info';
    month?: string;
    amount?: number;
    count?: number;
}

interface Props {
    period: Period;
    pl: ProfitAndLoss;
    previous: { net_revenue: number; gross_profit: number; net_profit: number; total_costs: number };
    money: { total: number; cash: number; bank: number; clearing: number; receivable: number; payable: number; inventory: number };
    monthly: MonthPoint[];
    forecast: { cash_now: number; safety: number; monthly_flow: number; history: MonthPoint[]; months: Array<{ month: string; cash: number; bills: number }>; first_below: string | null; runway_months: number | null };
    breakEven: { fixed: number; variable_ratio: number | null; break_even: number | null; month_sales: number; progress: number | null; average_order: number | null; orders_needed: number | null; basis_months: number };
    alerts: Alert[];
    hasEntries: boolean;
    vatRegistered: boolean;
}

const alertLink: Record<Alert['key'], string> = {
    low_cash: '/finance/budgets',
    overdue_bills: '/finance/expenses?filter=unpaid',
    unsynced: '/finance/close',
    close_month: '/finance/close',
    payout: '/finance/ledger?tab=accounts',
    vat: '/finance/reports?report=vat&period=last_month',
};

export default function FinanceOverview({ period, pl, previous, money: m, monthly, forecast, breakEven, alerts, hasEntries }: Props) {
    const t = useT();
    const locale = useLocale();
    const money = useMoney();
    const name = useAccountName();
    const can = useCan();
    const { url } = usePage();
    const costs = pl.total_cogs + pl.total_variable + pl.total_fixed;
    const costRows = [...pl.cogs, ...pl.variable, ...pl.fixed].sort((a, b) => b.amount - a.amount);
    const topCosts = costRows.slice(0, 6);
    const other = costRows.slice(6).reduce((s, r) => s + r.amount, 0);

    return (
        <DashboardLayout title={t('nav.finance')}>
            <PageHeader
                title={t('finance.title')}
                description={t('finance.subtitle')}
                action={
                    <>
                        <PeriodPicker period={period} url={url.split('?')[0]} />
                        <Button variant="glass" onClick={(e) => openAi(e.currentTarget)}>
                            <Sparkles className="h-4 w-4" aria-hidden />
                            {t('finance.ask')}
                        </Button>
                    </>
                }
            />

            {!hasEntries ? (
                <Card className="mb-6">
                    <EmptyFinance
                        icon={BookOpen}
                        title={t('finance.empty_title')}
                        body={t('finance.empty_body')}
                        action={
                            can('ledger.manage') ? (
                                <div className="flex flex-wrap justify-center gap-2">
                                    <Button onClick={() => router.post('/finance/catch-up', {}, { preserveScroll: true })}>
                                        <RefreshCw className="h-4 w-4" aria-hidden />
                                        {t('finance.catch_up')}
                                    </Button>
                                    <ButtonLink href="/finance/expenses" variant="secondary">
                                        <Receipt className="h-4 w-4" aria-hidden />
                                        {t('expenses.add')}
                                    </ButtonLink>
                                    <ButtonLink href="/finance/ledger?tab=journal&new=1" variant="secondary">
                                        {t('finance.opening_balances')}
                                    </ButtonLink>
                                </div>
                            ) : null
                        }
                    />
                </Card>
            ) : null}

            {alerts.length ? (
                <ul className="mb-6 grid gap-2 md:grid-cols-2" aria-label={t('finance.attention')}>
                    {alerts.map((a) => (
                        <li key={a.key}>
                            <Link
                                href={alertLink[a.key]}
                                className={cn(
                                    'card flex items-start gap-3 p-4 text-sm transition-colors hover:bg-elevated',
                                    a.tone === 'danger' && 'border-danger/40',
                                )}
                            >
                                <span
                                    className={cn(
                                        'grid h-8 w-8 shrink-0 place-items-center rounded-full',
                                        a.tone === 'danger' ? 'bg-danger-subtle text-danger' : a.tone === 'warning' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info',
                                    )}
                                >
                                    {a.tone === 'info' ? <Info className="h-4 w-4" aria-hidden /> : <AlertTriangle className="h-4 w-4" aria-hidden />}
                                </span>
                                <span className="min-w-0">
                                    <span className="block font-semibold">{t(`finance.alert_${a.key}`)}</span>
                                    <span className="text-ink-muted">
                                        {t(`finance.alert_${a.key}_body`, {
                                            month: a.month ? formatMonth(a.month, locale) : '',
                                            amount: a.amount !== undefined ? money(a.amount) : '',
                                            count: a.count ?? 0,
                                        })}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : null}

            <div className="mb-6 grid grid-cols-2 gap-2 sm:gap-4 xl:grid-cols-4">
                <StatTile icon={TrendingUp} label={t('finance.revenue')} value={money(pl.net_revenue)} hint={<Delta now={pl.net_revenue} before={previous.net_revenue} />} />
                <StatTile icon={Percent} label={t('finance.gross_margin')} value={pl.gross_margin === null ? '—' : `${pl.gross_margin}%`} hint={t('finance.gross_profit_is', { amount: money(pl.gross_profit) })} />
                <StatTile
                    icon={Target}
                    label={t('finance.net_profit')}
                    value={money(pl.net_profit)}
                    tone={pl.net_profit < 0 ? 'danger' : 'default'}
                    hint={<Delta now={pl.net_profit} before={previous.net_profit} />}
                />
                <StatTile icon={Wallet} label={t('finance.cash')} value={money(m.total)} tone={m.total < forecast.safety ? 'warning' : 'default'} hint={t('finance.cash_hint')} />
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                <div className="space-y-6">
                    <Card>
                        <CardHeader title={t('finance.trend')} description={t('finance.trend_hint')} />
                        <CardBody>
                            <RevenueCostChart data={monthly} />
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader
                            title={t('finance.pl_title')}
                            description={t('finance.pl_hint')}
                            action={
                                <Link href={`/finance/reports?report=pl&period=${period.key}${period.key === 'custom' ? `&from=${period.from}&to=${period.to}` : ''}`} className="text-sm text-ink-accent hover:underline">
                                    {t('finance.full_report')}
                                </Link>
                            }
                        />
                        <CardBody>
                            <dl className="divide-y divide-line text-sm">
                                {[
                                    ['net_revenue', pl.net_revenue],
                                    ['cogs', -pl.total_cogs],
                                    ['gross_profit', pl.gross_profit],
                                    ['variable', -pl.total_variable],
                                    ['contribution', pl.contribution],
                                    ['fixed', -pl.total_fixed],
                                ].map(([k, v]) => (
                                    <div key={k as string} className={cn('flex min-h-[44px] items-center justify-between gap-3', (k === 'gross_profit' || k === 'contribution') && 'font-semibold')}>
                                        <dt>{t(`finance.line_${k}`)}</dt>
                                        <dd>
                                            <Amount value={v as number} />
                                        </dd>
                                    </div>
                                ))}
                                <div className="flex min-h-[56px] items-center justify-between gap-3">
                                    <dt className="font-display text-base">{t('finance.net_profit')}</dt>
                                    <dd>
                                        <Amount value={pl.net_profit} strong className={cn('text-lg', pl.net_profit >= 0 ? 'text-success' : 'text-danger')} />
                                    </dd>
                                </div>
                            </dl>
                            <p className="mt-3 text-caption text-ink-muted">{t('finance.source', { from: period.from, to: period.to })}</p>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title={t('finance.where_money_goes')} description={t('finance.where_money_goes_hint', { amount: money(costs) })} />
                        <CardBody>
                            {topCosts.length ? (
                                <ShareBars
                                    total={costs}
                                    rows={[
                                        ...topCosts.map((r) => ({ key: r.account_id, label: name(r), value: r.amount, href: `/finance/ledger?account=${r.account_id}&period=${period.key}` })),
                                        ...(other > 0 ? [{ key: 'other', label: t('finance.other_costs'), value: other }] : []),
                                    ]}
                                />
                            ) : (
                                <p className="text-sm text-ink-muted">{t('finance.no_costs')}</p>
                            )}
                        </CardBody>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader title={t('finance.forecast')} description={t('finance.forecast_hint')} />
                        <CardBody>
                            <CashChart history={forecast.history} forecast={forecast.months} safety={forecast.safety} />
                            <dl className="mt-4 space-y-2 text-sm">
                                {forecast.months.map((f) => (
                                    <div key={f.month} className="flex items-center justify-between gap-3">
                                        <dt className="text-ink-muted">
                                            {formatMonth(f.month, locale)}
                                            {f.bills > 0 ? <span className="ms-1 text-caption">({t('finance.bills_due', { amount: money(f.bills) })})</span> : null}
                                        </dt>
                                        <dd>
                                            <Amount value={f.cash} className={f.cash < forecast.safety ? 'text-warning' : ''} />
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <p className="mt-3 text-caption text-ink-muted">
                                {t('finance.forecast_method', { flow: money(forecast.monthly_flow) })}
                                {forecast.runway_months !== null ? ` ${t('finance.runway', { months: forecast.runway_months })}` : ''}
                            </p>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title={t('finance.money_title')} />
                        <CardBody>
                            <dl className="space-y-2.5 text-sm">
                                {(
                                    [
                                        ['cash', m.cash],
                                        ['bank', m.bank],
                                        ['clearing', m.clearing],
                                        ['inventory', m.inventory],
                                        ['receivable', m.receivable],
                                        ['payable', -m.payable],
                                    ] as Array<[string, number]>
                                ).map(([k, v]) => (
                                    <div key={k} className="flex items-center justify-between gap-3">
                                        <dt className="inline-flex items-center gap-2">
                                            {k === 'bank' ? <Landmark className="h-4 w-4 text-ink-muted" aria-hidden /> : null}
                                            {t(`finance.money_${k}`)}
                                        </dt>
                                        <dd>
                                            <Amount value={v} />
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title={t('finance.breakeven')} description={t('finance.breakeven_hint')} />
                        <CardBody>
                            {breakEven.break_even !== null ? (
                                <>
                                    <p className="text-sm">{t('finance.breakeven_body', { amount: money(breakEven.break_even) })}</p>
                                    <div className="mt-3">
                                        <Meter value={breakEven.progress ?? 0} tone={(breakEven.progress ?? 0) >= 100 ? 'success' : 'accent'} label={t('finance.breakeven')} />
                                    </div>
                                    <p className="mt-2 flex justify-between text-caption text-ink-muted">
                                        <span>{t('finance.sold_this_month', { amount: money(breakEven.month_sales) })}</span>
                                        <span dir="ltr" className="tabular">
                                            {breakEven.progress ?? 0}%
                                        </span>
                                    </p>
                                    {breakEven.orders_needed !== null ? (
                                        <p className="mt-3 text-sm text-ink-muted">
                                            {t('finance.orders_needed', { count: breakEven.orders_needed, aov: money(breakEven.average_order ?? 0) })}
                                        </p>
                                    ) : null}
                                </>
                            ) : (
                                <p className="text-sm text-ink-muted">{t('finance.breakeven_none')}</p>
                            )}
                            <Link href="/finance/budgets" className="mt-3 inline-block text-sm text-ink-accent hover:underline">
                                {t('finance.plan_whatif')}
                            </Link>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardBody className="flex flex-wrap gap-2">
                            <ButtonLink href="/finance/expenses" variant="secondary" size="sm">
                                <Receipt className="h-4 w-4" aria-hidden />
                                {t('nav.finance_expenses')}
                            </ButtonLink>
                            <ButtonLink href="/finance/close" variant="secondary" size="sm">
                                <CalendarCheck className="h-4 w-4" aria-hidden />
                                {t('nav.finance_close')}
                            </ButtonLink>
                            <ButtonLink href="/finance/ledger" variant="secondary" size="sm">
                                <BookOpen className="h-4 w-4" aria-hidden />
                                {t('nav.finance_ledger')}
                            </ButtonLink>
                            {m.clearing > 0 ? <Badge tone="info">{t('finance.payout_pending', { amount: money(m.clearing) })}</Badge> : null}
                        </CardBody>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}
