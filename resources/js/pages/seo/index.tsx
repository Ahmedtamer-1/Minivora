// SEO health: page scores, issues with one-click fixes, sitemaps and feeds. FR-SEO-02, -05, -13, -16, -18, -19. Preview.
import { AlertTriangle, CheckCircle2, FileCode2, Globe2, Rss, Search, XCircle } from 'lucide-react';
import { PlannedNotice, PreviewCard } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { useT } from '@/lib/i18n';
import { sampleSeoIssues, useBi } from '@/lib/samples';

const icons = { danger: XCircle, warning: AlertTriangle, success: CheckCircle2 } as const;
const colors = { danger: 'text-danger', warning: 'text-warning', success: 'text-success' } as const;

const files = [
    { key: 'sitemap', icon: FileCode2, path: '/sitemap.xml', req: 'FR-SEO-05' },
    { key: 'robots', icon: FileCode2, path: '/robots.txt', req: 'FR-SEO-06' },
    { key: 'llms', icon: FileCode2, path: '/llms.txt', req: 'FR-SEO-15' },
    { key: 'feed', icon: Rss, path: '/feeds/google.xml', req: 'FR-SEO-16' },
];

/** Circular score, 0–100. The number carries the meaning; the ring only echoes it. */
function Score({ value, label }: { value: number; label: string }) {
    const r = 52;
    const c = 2 * Math.PI * r;
    return (
        <div className="relative grid h-36 w-36 place-items-center">
            <svg viewBox="0 0 120 120" className="absolute inset-0 -rotate-90" aria-hidden>
                <circle cx="60" cy="60" r={r} fill="none" strokeWidth="10" className="stroke-chart-track" />
                <circle cx="60" cy="60" r={r} fill="none" strokeWidth="10" strokeLinecap="round" className="stroke-success" strokeDasharray={c} strokeDashoffset={c * (1 - value / 100)} />
            </svg>
            <p className="text-center">
                <span className="tabular block font-display text-numeral-sm">{value}</span>
                <span className="block text-caption text-ink-muted">{label}</span>
            </p>
        </div>
    );
}

export default function Seo() {
    const t = useT();
    const bi = useBi();

    return (
        <DashboardLayout title={t('nav.seo')}>
            <PageHeader title={t('seo.title')} description={t('seo.subtitle')} />
            <PlannedNotice requirements={['FR-SEO-02', 'FR-SEO-13', 'FR-SEO-18', 'FR-SEO-19']} />

            <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                <PreviewCard title={t('seo.score')} description={t('seo.score_hint')}>
                    <div className="flex flex-col items-center gap-4 pt-2">
                        <Score value={82} label={t('seo.out_of')} />
                        <dl className="grid w-full grid-cols-2 gap-3 text-center">
                            <div className="rounded-2xl bg-secondary px-3 py-2.5">
                                <dt className="text-caption text-ink-muted">{t('seo.indexed')}</dt>
                                <dd className="tabular font-display text-lg">46</dd>
                            </div>
                            <div className="rounded-2xl bg-secondary px-3 py-2.5">
                                <dt className="text-caption text-ink-muted">{t('seo.clicks')}</dt>
                                <dd className="tabular font-display text-lg">1,204</dd>
                            </div>
                        </dl>
                    </div>
                </PreviewCard>

                <PreviewCard title={t('seo.issues')} description={t('seo.issues_hint')}>
                    <ul className="divide-y divide-line">
                        {sampleSeoIssues.map((i) => {
                            const Icon = icons[i.severity as keyof typeof icons];
                            return (
                                <li key={i.id} className="flex min-h-[60px] flex-wrap items-center gap-3 py-2">
                                    <Icon className={`h-5 w-5 shrink-0 ${colors[i.severity as keyof typeof colors]}`} aria-label={t(`seo.sev_${i.severity}`)} />
                                    <span className="min-w-0 flex-1 text-sm">{bi(i.text)}</span>
                                    {i.fix ? (
                                        <Button size="sm" variant="secondary" disabled>
                                            {bi(i.fix)}
                                        </Button>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ul>
                </PreviewCard>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <PreviewCard title={t('seo.files')} description={t('seo.files_hint')}>
                    <ul className="divide-y divide-line">
                        {files.map(({ key, icon: Icon, path, req }) => (
                            <li key={key} className="flex min-h-[56px] items-center gap-3">
                                <Icon className="h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm">{t(`seo.file_${key}`)}</span>
                                    <span dir="ltr" className="block truncate font-mono text-xs text-ink-muted">
                                        {path}
                                    </span>
                                </span>
                                <span dir="ltr" className="hidden font-mono text-xs text-ink-muted sm:inline">
                                    {req}
                                </span>
                                <Badge tone="success">{t('seo.live')}</Badge>
                            </li>
                        ))}
                    </ul>
                </PreviewCard>

                <PreviewCard title={t('seo.search_console')} description={t('seo.search_console_hint')}>
                    <div className="flex flex-col items-start gap-4">
                        <div className="flex items-center gap-3 text-sm">
                            <span className="grid h-10 w-10 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                                <Search className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                            </span>
                            {t('seo.search_console_body')}
                        </div>
                        <Button variant="secondary" disabled>
                            <Globe2 className="h-4 w-4" aria-hidden />
                            {t('seo.verify')}
                        </Button>
                    </div>
                </PreviewCard>
            </div>
        </DashboardLayout>
    );
}
