// Store builder: pages made of sections, AI drafts, versions. FR-BLD-01, -02, -05, -06, -07. Placeholder for a later phase.
import { ExternalLink, FilePlus2, Files, History, LayoutTemplate, MessageSquareText, Sparkles } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { PlannedFeatures, PlannedNotice, PreviewCard } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { useT } from '@/lib/i18n';
import { samplePages, useBi } from '@/lib/samples';
import type { SharedProps } from '@/types';

export default function BuilderPages() {
    const t = useT();
    const bi = useBi();
    const { store } = usePage<SharedProps>().props;

    return (
        <DashboardLayout title={t('nav.builder')}>
            <PageHeader
                title={t('builder.title')}
                description={t('builder.subtitle')}
                action={
                    <>
                        {store ? (
                            <a href="/preview" target="_blank" rel="noopener" className="inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold text-ink-link hover:bg-secondary">
                                <ExternalLink className="h-4 w-4" aria-hidden />
                                {t('nav.view_store')}
                            </a>
                        ) : null}
                        <Button disabled>
                            <FilePlus2 className="h-4 w-4" aria-hidden />
                            {t('builder.add_page')}
                        </Button>
                    </>
                }
            />
            <PlannedNotice requirements={['FR-BLD-01', 'FR-BLD-02', 'FR-BLD-05', 'FR-BLD-06', 'FR-BLD-07']} body={t('builder.later')} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <PreviewCard title={t('builder.pages')}>
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {samplePages.map((p) => (
                            <li key={p.id} className="rounded-2xl border border-line p-3">
                                {/* Wireframe thumbnail: stacked section blocks. */}
                                <div aria-hidden className="mb-3 space-y-1.5 rounded-xl bg-secondary p-2.5">
                                    <div className="h-8 rounded-md bg-chart-track" />
                                    <div className="grid grid-cols-3 gap-1.5">
                                        <div className="h-6 rounded-md bg-chart-track" />
                                        <div className="h-6 rounded-md bg-chart-track" />
                                        <div className="h-6 rounded-md bg-chart-track" />
                                    </div>
                                    <div className="h-3 w-2/3 rounded-md bg-chart-track" />
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="min-w-0 flex-1 truncate text-sm font-medium">{bi(p.name)}</span>
                                    <Badge tone={p.status === 'published' ? 'success' : 'warning'}>{t(`builder.status_${p.status}`)}</Badge>
                                </div>
                                <p className="mt-1 text-caption text-ink-muted">
                                    {t('builder.sections', { count: p.sections })} · {bi(p.updated)}
                                </p>
                            </li>
                        ))}
                    </ul>
                </PreviewCard>
                <PlannedFeatures
                    items={[
                        { icon: LayoutTemplate, text: t('builder.f_sections'), req: 'FR-BLD-01' },
                        { icon: Files, text: t('builder.f_edit'), req: 'FR-BLD-02' },
                        { icon: MessageSquareText, text: t('builder.f_chat'), req: 'FR-BLD-05' },
                        { icon: Sparkles, text: t('builder.f_draft'), req: 'FR-BLD-06' },
                        { icon: History, text: t('builder.f_versions'), req: 'FR-BLD-07' },
                    ]}
                />
            </div>
        </DashboardLayout>
    );
}
