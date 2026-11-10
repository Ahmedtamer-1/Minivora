// Month close: the accountant's checklist, then lock the month so its numbers can't change.
// UC-10, FR-LED-07
import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, CircleDashed, Lock, LockOpen, RefreshCw, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Amount, formatMonth, type ProfitAndLoss } from '@/components/finance';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Checkbox, Input } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { formatDate, useLocale, useT } from '@/lib/i18n';
import { useMoney } from '@/components/finance';
import { useCan } from '@/lib/permissions';

interface Check {
    key: 'orders' | 'balanced' | 'previous' | 'bank' | 'inventory' | 'bills';
    status: 'ok' | 'blocker' | 'warning' | 'confirm';
    value?: number;
    detail?: number;
}

interface Props {
    checklist: { month: string; closed: boolean; closed_at: string | null; can_close: boolean; checks: Check[] };
    closed: Array<{ month: string; closed_at: string }>;
    summary: ProfitAndLoss;
}

const icons = { ok: CheckCircle2, blocker: XCircle, warning: AlertTriangle, confirm: CircleDashed };
const tones = { ok: 'text-success', blocker: 'text-danger', warning: 'text-warning', confirm: 'text-ink-muted' };

export default function FinanceClose({ checklist, closed, summary }: Props) {
    const t = useT();
    const locale = useLocale();
    const money = useMoney();
    const can = useCan();
    const manage = can('ledger.manage');
    const [month, setMonth] = useState(checklist.month);
    const form = useForm({ month: checklist.month, bank_confirmed: false });
    const latestClosed = closed[0]?.month;

    const fix = (check: Check) => {
        if (!manage) return null;
        if (check.key === 'orders' && check.status !== 'ok')
            return (
                <Button size="sm" variant="secondary" onClick={() => router.post('/finance/catch-up', {}, { preserveScroll: true })}>
                    <RefreshCw className="h-3.5 w-3.5" aria-hidden />
                    {t('close.catch_up')}
                </Button>
            );
        if (check.key === 'inventory' && check.status !== 'ok')
            return (
                <Button size="sm" variant="secondary" onClick={() => window.confirm(t('close.inventory_confirm')) && router.post('/finance/inventory-sync', {}, { preserveScroll: true })}>
                    {t('close.inventory_fix')}
                </Button>
            );
        return null;
    };

    return (
        <DashboardLayout title={t('nav.finance_close')}>
            <PageHeader
                title={t('close.title')}
                description={t('close.subtitle')}
                action={
                    <form
                        className="flex items-center gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get('/finance/close', { month }, { preserveScroll: true });
                        }}
                    >
                        <Input type="month" aria-label={t('close.month')} value={month} onChange={(e) => setMonth(e.target.value)} className="!w-auto rounded-full" />
                        <Button type="submit" variant="secondary">
                            {t('fin.apply')}
                        </Button>
                    </form>
                }
            />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card>
                    <CardHeader
                        title={t('close.checklist', { month: formatMonth(checklist.month, locale) })}
                        description={t('close.checklist_hint')}
                        action={checklist.closed ? <Badge tone="success"><Lock className="h-3.5 w-3.5" aria-hidden />{t('close.closed')}</Badge> : <Badge tone="warning"><LockOpen className="h-3.5 w-3.5" aria-hidden />{t('close.open')}</Badge>}
                    />
                    <CardBody>
                        <ol className="divide-y divide-line">
                            {checklist.checks.map((c) => {
                                const Icon = icons[c.status];
                                return (
                                    <li key={c.key} className="flex flex-wrap items-start gap-3 py-4 first:pt-0">
                                        <Icon className={cn('mt-0.5 h-5 w-5 shrink-0', tones[c.status])} aria-hidden />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-semibold">
                                                {t(`close.check_${c.key}`)}
                                                <span className="sr-only"> — {t(`close.status_${c.status}`)}</span>
                                            </p>
                                            <p className="mt-0.5 text-sm text-ink-muted">
                                                {t(`close.check_${c.key}_${c.status}`, { count: c.value ?? 0, amount: money(Math.abs(c.value ?? 0)), missing: c.detail ?? 0 })}
                                            </p>
                                        </div>
                                        {fix(c)}
                                    </li>
                                );
                            })}
                        </ol>

                        {manage && !checklist.closed ? (
                            <form
                                className="mt-4 space-y-4 rounded-2xl bg-secondary p-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.transform((d) => ({ ...d, month: checklist.month }));
                                    form.post('/finance/close', { preserveScroll: true });
                                }}
                            >
                                <Checkbox label={t('close.confirm_bank')} checked={form.data.bank_confirmed} onChange={(e) => form.setData('bank_confirmed', e.target.checked)} />
                                {form.errors.bank_confirmed || form.errors.month ? (
                                    <p role="alert" className="text-sm text-danger">
                                        {form.errors.bank_confirmed ?? form.errors.month}
                                    </p>
                                ) : null}
                                <div className="flex flex-wrap items-center gap-3">
                                    <Button type="submit" disabled={!checklist.can_close || !form.data.bank_confirmed || form.processing}>
                                        <Lock className="h-4 w-4" aria-hidden />
                                        {t('close.close_month', { month: formatMonth(checklist.month, locale) })}
                                    </Button>
                                    {!checklist.can_close ? <span className="text-caption text-ink-muted">{t('close.cannot_yet')}</span> : null}
                                </div>
                            </form>
                        ) : null}
                        {manage && checklist.closed && latestClosed === checklist.month ? (
                            <Button
                                variant="secondary"
                                className="mt-4"
                                onClick={() => window.confirm(t('close.reopen_confirm')) && router.post('/finance/reopen', { month: checklist.month }, { preserveScroll: true })}
                            >
                                <LockOpen className="h-4 w-4" aria-hidden />
                                {t('close.reopen')}
                            </Button>
                        ) : null}
                    </CardBody>
                </Card>

                <div className="space-y-6">
                    <Card>
                        <CardHeader title={t('close.month_result')} />
                        <CardBody>
                            <dl className="space-y-2 text-sm">
                                {(
                                    [
                                        ['net_revenue', summary.net_revenue],
                                        ['gross_profit', summary.gross_profit],
                                        ['costs', -(summary.total_variable + summary.total_fixed)],
                                    ] as const
                                ).map(([k, v]) => (
                                    <div key={k} className="flex justify-between gap-3">
                                        <dt className="text-ink-muted">{t(`finance.line_${k}`)}</dt>
                                        <dd>
                                            <Amount value={v} />
                                        </dd>
                                    </div>
                                ))}
                                <div className="flex justify-between gap-3 border-t border-line pt-2 font-semibold">
                                    <dt>{t('finance.net_profit')}</dt>
                                    <dd>
                                        <Amount value={summary.net_profit} className={summary.net_profit >= 0 ? 'text-success' : 'text-danger'} />
                                    </dd>
                                </div>
                            </dl>
                        </CardBody>
                    </Card>
                    <Card>
                        <CardHeader title={t('close.history')} />
                        <CardBody>
                            {closed.length ? (
                                <ul className="space-y-2 text-sm">
                                    {closed.map((p) => (
                                        <li key={p.month} className="flex items-center justify-between gap-3">
                                            <button type="button" className="hover:underline" onClick={() => router.get('/finance/close', { month: p.month })}>
                                                {formatMonth(p.month, locale)}
                                            </button>
                                            <span className="text-caption text-ink-muted">{formatDate(p.closed_at, locale)}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-ink-muted">{t('close.none_closed')}</p>
                            )}
                        </CardBody>
                    </Card>
                    <Card>
                        <CardBody>
                            <p className="text-sm font-semibold">{t('close.why_title')}</p>
                            <p className="mt-1 text-sm text-ink-muted">{t('close.why_body')}</p>
                        </CardBody>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}
