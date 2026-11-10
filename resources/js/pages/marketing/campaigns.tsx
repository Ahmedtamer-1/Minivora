// Meta campaigns with store-verified results and a verdict. UC-12, FR-MI-02 to FR-MI-08. Preview.
import { Link2, ListChecks, Plug, Scale, Sparkles } from 'lucide-react';
import { PlannedFeatures, PlannedNotice, PreviewCard, PreviewTable } from '@/components/planned';
import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { useLocale, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { sampleCampaigns, useBi } from '@/lib/samples';

const verdictTone: Record<string, Tone> = { scale: 'success', keep: 'info', fix: 'warning', pause: 'danger' };

export default function Campaigns() {
    const t = useT();
    const bi = useBi();
    const locale = useLocale();

    return (
        <DashboardLayout title={t('nav.marketing_campaigns')}>
            <PageHeader
                title={t('campaigns.title')}
                description={t('campaigns.subtitle')}
                action={
                    <Button variant="secondary" disabled>
                        <Plug className="h-4 w-4" aria-hidden />
                        {t('campaigns.connect')}
                    </Button>
                }
            />
            <PlannedNotice requirements={['UC-12', 'FR-MI-03', 'FR-MI-04', 'FR-MI-05', 'FR-MI-08']} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <PreviewCard title={t('campaigns.list')} description={t('campaigns.list_hint')}>
                    <PreviewTable
                        caption={t('campaigns.list')}
                        columns={[
                            { key: 'name', label: t('campaigns.col_name') },
                            { key: 'spend', label: t('campaigns.col_spend'), align: 'end' },
                            { key: 'sales', label: t('campaigns.col_sales'), align: 'end' },
                            { key: 'roas', label: t('campaigns.col_roas'), align: 'end' },
                            { key: 'verdict', label: t('campaigns.col_verdict'), align: 'end' },
                        ]}
                        rows={sampleCampaigns.map((c) => ({
                            id: c.id,
                            name: <span className="font-medium">{bi(c.name)}</span>,
                            spend: formatPounds(c.spend, locale),
                            sales: c.sales,
                            roas: `${c.roas}×`,
                            verdict: <Badge tone={verdictTone[c.verdict]}>{t(`campaigns.verdict_${c.verdict}`)}</Badge>,
                        }))}
                    />
                </PreviewCard>
                <PlannedFeatures
                    items={[
                        { icon: Plug, text: t('campaigns.f_connect'), req: 'FR-MI-02' },
                        { icon: Link2, text: t('campaigns.f_match'), req: 'FR-MI-04' },
                        { icon: Scale, text: t('campaigns.f_verdict'), req: 'FR-MI-05' },
                        { icon: Sparkles, text: t('campaigns.f_propose'), req: 'FR-MI-07' },
                        { icon: ListChecks, text: t('campaigns.f_approve'), req: 'FR-MI-08' },
                    ]}
                />
            </div>
        </DashboardLayout>
    );
}
