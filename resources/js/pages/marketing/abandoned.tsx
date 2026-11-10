// Abandoned checkouts: leads saved once a phone is entered, recovered by WhatsApp or email. FR-PAY-10, FR-MKT-01, FR-MKT-02. Preview.
import { Clock, Mail, MessageCircle, ShoppingCart, Wallet } from 'lucide-react';
import { StatTile } from '@/components/commerce';
import { PlannedNotice, PreviewCard, PreviewTable } from '@/components/planned';
import { Button } from '@/components/ui/button';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatNumber, useLocale, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { sampleAbandoned, useBi } from '@/lib/samples';

export default function Abandoned() {
    const t = useT();
    const bi = useBi();
    const locale = useLocale();

    return (
        <DashboardLayout title={t('nav.marketing_abandoned')}>
            <PageHeader title={t('abandoned.title')} description={t('abandoned.subtitle')} />
            <PlannedNotice requirements={['FR-PAY-10', 'FR-MKT-01', 'FR-MKT-02']} />

            <div className="mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile icon={ShoppingCart} label={t('abandoned.stat_open')} value="3" hint={t('abandoned.stat_open_hint')} tone="warning" />
                <StatTile icon={Wallet} label={t('abandoned.stat_value')} value={formatPounds(410000, locale)} hint={t('abandoned.stat_value_hint')} />
                <StatTile icon={Clock} label={t('abandoned.stat_recovered')} value="18%" hint={t('abandoned.stat_recovered_hint')} />
            </div>

            <PreviewCard title={t('abandoned.list')} description={t('abandoned.list_hint')}>
                <PreviewTable
                    caption={t('abandoned.list')}
                    columns={[
                        { key: 'customer', label: t('abandoned.col_customer') },
                        { key: 'items', label: t('abandoned.col_items'), align: 'end' },
                        { key: 'value', label: t('abandoned.col_value'), align: 'end' },
                        { key: 'step', label: t('abandoned.col_step') },
                        { key: 'action', label: <span className="sr-only">{t('abandoned.col_action')}</span>, align: 'end' },
                    ]}
                    rows={sampleAbandoned.map((a) => ({
                        id: a.id,
                        customer: (
                            <span className="block">
                                <span className="block font-medium">{bi(a.customer)}</span>
                                <span className="block text-caption text-ink-muted">{bi(a.age)}</span>
                            </span>
                        ),
                        items: formatNumber(a.items),
                        value: formatPounds(a.value, locale),
                        step: <span className="text-ink-muted">{bi(a.step)}</span>,
                        action: (
                            <span className="inline-flex gap-2">
                                <Button size="sm" variant="secondary" disabled>
                                    <MessageCircle className="h-4 w-4" aria-hidden />
                                    {t('abandoned.whatsapp')}
                                </Button>
                                <Button size="sm" variant="ghost" disabled aria-label={t('abandoned.email')}>
                                    <Mail className="h-4 w-4" aria-hidden />
                                </Button>
                            </span>
                        ),
                    }))}
                />
            </PreviewCard>
        </DashboardLayout>
    );
}
