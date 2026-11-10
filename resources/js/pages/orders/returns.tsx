// Returns and refunds: shopper requests, receive, refund, restock. UC-08, FR-ORD-08, FR-ORD-03, FR-LED-02. Preview.
import { BookOpen, PackageOpen, RotateCcw, Undo2, Wallet } from 'lucide-react';
import { StatTile } from '@/components/commerce';
import { PlannedFeatures, PlannedNotice, PreviewCard, PreviewTable } from '@/components/planned';
import { Badge, type Tone } from '@/components/ui/badge';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { useLocale, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { sampleReturns, useBi } from '@/lib/samples';

const tone: Record<string, Tone> = { requested: 'warning', received: 'info', refunded: 'success' };

export default function Returns() {
    const t = useT();
    const bi = useBi();
    const locale = useLocale();

    return (
        <DashboardLayout title={t('nav.orders_returns')}>
            <PageHeader title={t('returns.title')} description={t('returns.subtitle')} />
            <PlannedNotice requirements={['UC-08', 'FR-ORD-08', 'FR-ORD-03', 'FR-LED-02']} />

            <div className="mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile icon={Undo2} label={t('returns.stat_open')} value="2" hint={t('returns.stat_open_hint')} tone="warning" />
                <StatTile icon={RotateCcw} label={t('returns.stat_rate')} value="3.4%" hint={t('returns.stat_rate_hint')} />
                <StatTile icon={Wallet} label={t('returns.stat_refunded')} value={formatPounds(45000, locale)} hint={t('returns.stat_refunded_hint')} />
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <PreviewCard title={t('returns.list')}>
                    <PreviewTable
                        caption={t('returns.list')}
                        columns={[
                            { key: 'order', label: t('returns.col_order') },
                            { key: 'customer', label: t('returns.col_customer') },
                            { key: 'reason', label: t('returns.col_reason') },
                            { key: 'amount', label: t('returns.col_amount'), align: 'end' },
                            { key: 'status', label: t('returns.col_status'), align: 'end' },
                        ]}
                        rows={sampleReturns.map((r) => ({
                            id: r.id,
                            order: <span dir="ltr" className="font-medium">{r.order}</span>,
                            customer: bi(r.customer),
                            reason: <span className="text-ink-muted">{bi(r.reason)}</span>,
                            amount: formatPounds(r.amount, locale),
                            status: <Badge tone={tone[r.status]}>{t(`returns.status_${r.status}`)}</Badge>,
                        }))}
                    />
                </PreviewCard>
                <PlannedFeatures
                    items={[
                        { icon: Undo2, text: t('returns.f_request'), req: 'FR-ORD-08' },
                        { icon: PackageOpen, text: t('returns.f_restock'), req: 'FR-ORD-03' },
                        { icon: Wallet, text: t('returns.f_refund') },
                        { icon: BookOpen, text: t('returns.f_ledger'), req: 'FR-LED-02' },
                    ]}
                />
            </div>
        </DashboardLayout>
    );
}
