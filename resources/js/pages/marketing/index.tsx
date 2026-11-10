// Marketing overview: ad spend and return, tracking pixels, newsletter, on-site tools. FR-MKT-03 to FR-MKT-09. Preview.
import { Gift, Mail, MessageCircle, Plug, Radio, Target, TrendingUp, Wallet } from 'lucide-react';
import { StatTile } from '@/components/commerce';
import { PlannedNotice, PreviewCard } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatNumber, useLocale, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';

const pixels = [
    { key: 'meta', connected: true },
    { key: 'tiktok', connected: false },
    { key: 'snap', connected: false },
    { key: 'ga4', connected: true },
];

const tools = [
    { key: 'welcome', icon: Gift, on: true, req: 'FR-MKT-07' },
    { key: 'whatsapp', icon: MessageCircle, on: true, req: 'FR-MKT-09' },
    { key: 'newsletter', icon: Mail, on: false, req: 'FR-MKT-05' },
];

export default function Marketing() {
    const t = useT();
    const locale = useLocale();

    return (
        <DashboardLayout title={t('nav.marketing')}>
            <PageHeader title={t('marketing.title')} description={t('marketing.subtitle')} />
            <PlannedNotice requirements={['FR-MKT-03', 'FR-MKT-05', 'FR-MKT-07', 'FR-MKT-08']} />

            <div className="mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile icon={Wallet} label={t('marketing.stat_spend')} value={formatPounds(341000, locale)} hint={t('marketing.stat_spend_hint')} />
                <StatTile icon={TrendingUp} label={t('marketing.stat_roas')} value="2.4×" hint={t('marketing.stat_roas_hint')} />
                <StatTile icon={Target} label={t('marketing.stat_cpo')} value={formatPounds(21300, locale)} hint={t('marketing.stat_cpo_hint')} />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <PreviewCard title={t('marketing.pixels')} description={t('marketing.pixels_hint')}>
                    <ul className="divide-y divide-line">
                        {pixels.map((p) => (
                            <li key={p.key} className="flex min-h-[56px] items-center gap-3">
                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-secondary text-ink-muted">
                                    <Radio className="h-4 w-4" aria-hidden strokeWidth={1.75} />
                                </span>
                                <span className="min-w-0 flex-1 text-sm font-medium">{t(`marketing.pixel_${p.key}`)}</span>
                                {p.connected ? (
                                    <Badge tone="success">{t('marketing.connected')}</Badge>
                                ) : (
                                    <Button size="sm" variant="secondary" disabled>
                                        <Plug className="h-4 w-4" aria-hidden />
                                        {t('marketing.connect')}
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                </PreviewCard>

                <PreviewCard title={t('marketing.tools')} description={t('marketing.tools_hint')}>
                    <ul className="divide-y divide-line">
                        {tools.map(({ key, icon: Icon, on, req }) => (
                            <li key={key} className="flex min-h-[64px] items-center gap-3 py-2">
                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                                    <Icon className="h-4 w-4" aria-hidden strokeWidth={1.75} />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium">{t(`marketing.tool_${key}`)}</span>
                                    <span className="block text-caption text-ink-muted">
                                        {t(`marketing.tool_${key}_hint`)}{' '}
                                        <span dir="ltr" className="font-mono text-xs">
                                            {req}
                                        </span>
                                    </span>
                                </span>
                                <Badge tone={on ? 'success' : 'neutral'}>{on ? t('marketing.on') : t('marketing.off')}</Badge>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-4 text-caption text-ink-muted">{t('marketing.subscribers', { count: formatNumber(268) })}</p>
                </PreviewCard>
            </div>
        </DashboardLayout>
    );
}
