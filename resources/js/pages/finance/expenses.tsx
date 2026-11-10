// Expenses and month close. UC-10, FR-LED-05, FR-LED-07. Preview.
import { usePage } from '@inertiajs/react';
import { CalendarCheck, Lock, Paperclip, Plus } from 'lucide-react';
import { PlannedNotice, PreviewCard, PreviewTable } from '@/components/planned';
import { Button } from '@/components/ui/button';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatDate, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { sampleExpenses, useBi } from '@/lib/samples';
import type { SharedProps } from '@/types';

export default function FinanceExpenses() {
    const t = useT();
    const bi = useBi();
    const { locale } = usePage<SharedProps>().props;
    const total = sampleExpenses.reduce((s, e) => s + e.amount, 0);

    return (
        <DashboardLayout title={t('nav.finance_expenses')}>
            <PageHeader
                title={t('expenses.title')}
                description={t('expenses.subtitle')}
                action={
                    <Button disabled>
                        <Plus className="h-4 w-4" aria-hidden />
                        {t('expenses.add')}
                    </Button>
                }
            />
            <PlannedNotice requirements={['UC-10', 'FR-LED-05', 'FR-LED-07']} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <PreviewCard title={t('expenses.list')} description={t('expenses.total', { amount: formatPounds(total, locale) })}>
                    <PreviewTable
                        caption={t('expenses.list')}
                        columns={[
                            { key: 'date', label: t('expenses.col_date') },
                            { key: 'what', label: t('expenses.col_what') },
                            { key: 'account', label: t('expenses.col_account') },
                            { key: 'amount', label: t('expenses.col_amount'), align: 'end' },
                        ]}
                        rows={sampleExpenses.map((e) => ({
                            id: e.id,
                            date: <span className="text-ink-muted">{formatDate(e.date, locale)}</span>,
                            what: (
                                <span className="inline-flex items-center gap-2 font-medium">
                                    {bi(e.what)}
                                    <Paperclip className="h-3.5 w-3.5 text-ink-muted" aria-label={t('expenses.receipt')} />
                                </span>
                            ),
                            account: bi(e.account),
                            amount: formatPounds(e.amount, locale),
                        }))}
                    />
                </PreviewCard>

                <PreviewCard title={t('expenses.close_title')} description={t('expenses.close_hint')}>
                    <ul className="space-y-3 text-sm">
                        {['bank', 'cod', 'stock'].map((k, i) => (
                            <li key={k} className="flex items-center gap-3">
                                <CalendarCheck className={`h-4 w-4 shrink-0 ${i < 2 ? 'text-success' : 'text-ink-muted'}`} aria-hidden />
                                <span className={i < 2 ? '' : 'text-ink-muted'}>{t(`expenses.check_${k}`)}</span>
                            </li>
                        ))}
                    </ul>
                    <Button variant="secondary" className="mt-5" disabled>
                        <Lock className="h-4 w-4" aria-hidden />
                        {t('expenses.close')}
                    </Button>
                </PreviewCard>
            </div>
        </DashboardLayout>
    );
}
