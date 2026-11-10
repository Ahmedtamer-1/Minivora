// General ledger: journal entries posted automatically, always balanced. FR-LED-01, FR-LED-02, FR-LED-03. Preview.
import { usePage } from '@inertiajs/react';
import { Scale } from 'lucide-react';
import { PlannedNotice, PreviewCard, PreviewTable } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatDate, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { sampleJournal, useBi } from '@/lib/samples';
import type { SharedProps } from '@/types';

export default function FinanceLedger() {
    const t = useT();
    const bi = useBi();
    const { locale } = usePage<SharedProps>().props;

    return (
        <DashboardLayout title={t('nav.finance_ledger')}>
            <PageHeader title={t('ledger.title')} description={t('ledger.subtitle')} />
            <PlannedNotice requirements={['FR-LED-01', 'FR-LED-02', 'FR-LED-03']} />

            <PreviewCard
                title={t('ledger.journal')}
                description={t('ledger.journal_hint')}
                action={
                    <Badge tone="success">
                        <Scale className="h-3.5 w-3.5" aria-hidden />
                        {t('ledger.balanced')}
                    </Badge>
                }
            >
                <PreviewTable
                    caption={t('ledger.journal')}
                    columns={[
                        { key: 'date', label: t('ledger.col_date') },
                        { key: 'memo', label: t('ledger.col_memo') },
                        { key: 'debit', label: t('ledger.col_debit') },
                        { key: 'credit', label: t('ledger.col_credit') },
                        { key: 'amount', label: t('ledger.col_amount'), align: 'end' },
                    ]}
                    rows={sampleJournal.map((j) => ({
                        id: j.id,
                        date: <span className="text-ink-muted">{formatDate(j.date, locale)}</span>,
                        memo: <span className="font-medium">{bi(j.memo)}</span>,
                        debit: bi(j.debit),
                        credit: bi(j.credit),
                        amount: formatPounds(j.amount, locale),
                    }))}
                />
            </PreviewCard>
        </DashboardLayout>
    );
}
