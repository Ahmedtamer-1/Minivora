// Approvals inbox: AI actions waiting for the owner. UC-04, FR-AI-07, FR-AI-10, FR-PRO-05. Preview with sample data.
import { Check, CircleCheck, Clock, History, Pencil, ShieldCheck, X } from 'lucide-react';
import { StatTile } from '@/components/commerce';
import { PlannedFeatures, PlannedNotice, SampleBadge } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { useLocale, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { sampleApprovals, useBi } from '@/lib/samples';

export default function Approvals() {
    const t = useT();
    const bi = useBi();
    const locale = useLocale();

    return (
        <DashboardLayout title={t('nav.approvals')}>
            <PageHeader title={t('approvals.title')} description={t('approvals.subtitle')} />
            <PlannedNotice requirements={['UC-04', 'FR-AI-07', 'FR-AI-10', 'FR-PRO-05']} />

            <div className="mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile icon={Clock} label={t('approvals.stat_pending')} value="3" hint={t('approvals.stat_pending_hint')} tone="warning" />
                <StatTile icon={CircleCheck} label={t('approvals.stat_week')} value="8" hint={t('approvals.stat_week_hint')} />
                <StatTile icon={ShieldCheck} label={t('approvals.stat_limit')} value={formatPounds(500000, locale)} hint={t('approvals.stat_limit_hint')} />
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <section aria-labelledby="pending-title" className="min-w-0">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <h2 id="pending-title" className="font-display text-base">
                            {t('approvals.pending')}
                        </h2>
                        <SampleBadge />
                    </div>
                    <ul className="space-y-4">
                        {sampleApprovals.map((a) => (
                            <li key={a.id}>
                                <Card className="p-5 sm:p-6">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge tone="info">{t(`agents.${a.agent}`)}</Badge>
                                        <Badge tone={a.risk === 'act' ? 'warning' : 'neutral'}>{t(`approvals.risk_${a.risk}`)}</Badge>
                                        <span className="ms-auto text-caption text-ink-muted">{bi(a.age)}</span>
                                    </div>
                                    <h3 className="mt-3 font-display text-lg">{bi(a.title)}</h3>
                                    <p className="mt-1 text-sm text-ink-muted">{bi(a.why)}</p>
                                    {a.impact > 0 ? (
                                        <p className="mt-3 text-sm">
                                            {t('approvals.impact')} <span className="tabular font-semibold">{formatPounds(a.impact, locale)}</span>
                                        </p>
                                    ) : null}
                                    <div className="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
                                        <Button disabled>
                                            <Check className="h-4 w-4" aria-hidden />
                                            {t('approvals.approve')}
                                        </Button>
                                        <Button variant="secondary" disabled>
                                            <Pencil className="h-4 w-4" aria-hidden />
                                            {t('approvals.edit')}
                                        </Button>
                                        <Button variant="ghost" disabled>
                                            <X className="h-4 w-4" aria-hidden />
                                            {t('approvals.reject')}
                                        </Button>
                                    </div>
                                </Card>
                            </li>
                        ))}
                    </ul>
                </section>

                <aside className="space-y-6">
                    <PlannedFeatures
                        title={t('approvals.rules_title')}
                        items={[
                            { icon: ShieldCheck, text: t('approvals.rule_act'), req: 'FR-AI-07' },
                            { icon: Pencil, text: t('approvals.rule_edit'), req: 'FR-PRO-05' },
                            { icon: Clock, text: t('approvals.rule_limit'), req: 'FR-ACC-03' },
                            { icon: History, text: t('approvals.rule_log'), req: 'FR-AI-10' },
                        ]}
                    />
                </aside>
            </div>
        </DashboardLayout>
    );
}
