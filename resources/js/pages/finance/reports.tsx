// Financial statements for any period: P&L, balance sheet, cash flow; export. FR-LED-04, FR-LED-08. Preview.
import { usePage } from '@inertiajs/react';
import { Download, FileSpreadsheet } from 'lucide-react';
import { useState } from 'react';
import { FilterTabs } from '@/components/commerce';
import { PlannedNotice, PreviewCard } from '@/components/planned';
import { Button } from '@/components/ui/button';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import type { SharedProps } from '@/types';

type Report = 'pl' | 'balance' | 'cash';

const statements: Record<Report, Array<{ key: string; amount: number; total?: boolean }>> = {
    pl: [
        { key: 'revenue', amount: 9020000 },
        { key: 'cogs', amount: -3610000 },
        { key: 'gross', amount: 5410000, total: true },
        { key: 'opex', amount: -1591000 },
        { key: 'net', amount: 3819000, total: true },
    ],
    balance: [
        { key: 'cash_bank', amount: 3720000 },
        { key: 'inventory', amount: 2140000 },
        { key: 'receivables', amount: 386000 },
        { key: 'assets', amount: 6246000, total: true },
        { key: 'payables', amount: 512000 },
        { key: 'equity', amount: 5734000 },
        { key: 'liab_equity', amount: 6246000, total: true },
    ],
    cash: [
        { key: 'cash_in', amount: 8860000 },
        { key: 'cash_out', amount: -5980000 },
        { key: 'net_cash', amount: 2880000, total: true },
    ],
};

export default function FinanceReports() {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const [report, setReport] = useState<Report>('pl');

    return (
        <DashboardLayout title={t('nav.finance_reports')}>
            <PageHeader
                title={t('reports.title')}
                description={t('reports.subtitle')}
                action={
                    <>
                        <Button variant="secondary" disabled>
                            <FileSpreadsheet className="h-4 w-4" aria-hidden />
                            {t('reports.excel')}
                        </Button>
                        <Button variant="secondary" disabled>
                            <Download className="h-4 w-4" aria-hidden />
                            {t('reports.pdf')}
                        </Button>
                    </>
                }
            />
            <PlannedNotice requirements={['UC-11', 'FR-LED-04', 'FR-LED-08']} />

            <FilterTabs label={t('reports.which')} active={report} onChange={setReport} options={(['pl', 'balance', 'cash'] as Report[]).map((k) => ({ key: k, label: t(`reports.${k}`) }))} />

            <PreviewCard title={t(`reports.${report}`)} description={t('reports.period')} className="max-w-3xl">
                <dl className="divide-y divide-line">
                    {statements[report].map((row) => (
                        <div key={row.key} className="flex min-h-[48px] items-center justify-between gap-3">
                            <dt className={row.total ? 'font-display text-base' : 'text-sm'}>{t(`reports.row_${row.key}`)}</dt>
                            <dd dir="ltr" className={`tabular ${row.total ? 'font-display text-base' : 'text-sm'} ${row.amount < 0 ? 'text-ink-muted' : ''}`}>{formatPounds(row.amount, locale)}</dd>
                        </div>
                    ))}
                </dl>
            </PreviewCard>
        </DashboardLayout>
    );
}
