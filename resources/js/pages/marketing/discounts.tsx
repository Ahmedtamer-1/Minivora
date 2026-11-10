// Discount codes: percentage, fixed or free shipping, with limits. FR-PAY-11, FR-AI-08. Preview.
import { BadgePercent, CalendarClock, Plus, ShieldAlert, Truck } from 'lucide-react';
import { PlannedFeatures, PlannedNotice, PreviewCard, PreviewTable } from '@/components/planned';
import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatNumber, useT } from '@/lib/i18n';
import { sampleDiscounts, useBi } from '@/lib/samples';

const tone: Record<string, Tone> = { active: 'success', scheduled: 'info', ended: 'neutral' };

export default function Discounts() {
    const t = useT();
    const bi = useBi();

    return (
        <DashboardLayout title={t('nav.marketing_discounts')}>
            <PageHeader
                title={t('discounts.title')}
                description={t('discounts.subtitle')}
                action={
                    <Button disabled>
                        <Plus className="h-4 w-4" aria-hidden />
                        {t('discounts.add')}
                    </Button>
                }
            />
            <PlannedNotice requirements={['FR-PAY-11', 'FR-AI-08']} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <PreviewCard title={t('discounts.list')}>
                    <PreviewTable
                        caption={t('discounts.list')}
                        columns={[
                            { key: 'code', label: t('discounts.col_code') },
                            { key: 'type', label: t('discounts.col_type') },
                            { key: 'used', label: t('discounts.col_used'), align: 'end' },
                            { key: 'status', label: t('discounts.col_status'), align: 'end' },
                        ]}
                        rows={sampleDiscounts.map((d) => ({
                            id: d.id,
                            code: (
                                <span dir="ltr" className="font-mono text-sm font-semibold">
                                    {d.code}
                                </span>
                            ),
                            type: bi(d.type),
                            used: formatNumber(d.used),
                            status: <Badge tone={tone[d.status]}>{t(`discounts.status_${d.status}`)}</Badge>,
                        }))}
                    />
                </PreviewCard>
                <PlannedFeatures
                    items={[
                        { icon: BadgePercent, text: t('discounts.f_types'), req: 'FR-PAY-11' },
                        { icon: Truck, text: t('discounts.f_ship') },
                        { icon: CalendarClock, text: t('discounts.f_limits') },
                        { icon: ShieldAlert, text: t('discounts.f_margin'), req: 'FR-AI-08' },
                    ]}
                />
            </div>
        </DashboardLayout>
    );
}
