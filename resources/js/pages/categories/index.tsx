// Categories: group products for the storefront menu and filters. FR-CAT-03, FR-CAT-01, FR-SEO-12. Preview.
import { Eye, EyeOff, GripVertical, Languages, Link2, Plus, Tags } from 'lucide-react';
import { PlannedFeatures, PlannedNotice, PreviewCard } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatNumber, useT } from '@/lib/i18n';
import { sampleCategories, useBi } from '@/lib/samples';

export default function Categories() {
    const t = useT();
    const bi = useBi();

    return (
        <DashboardLayout title={t('nav.products_categories')}>
            <PageHeader
                title={t('categories.title')}
                description={t('categories.subtitle')}
                action={
                    <Button disabled>
                        <Plus className="h-4 w-4" aria-hidden />
                        {t('categories.add')}
                    </Button>
                }
            />
            <PlannedNotice requirements={['FR-CAT-03', 'FR-CAT-01', 'FR-SEO-12']} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <PreviewCard title={t('categories.list')} description={t('categories.list_hint')}>
                    <ul className="-mx-2">
                        {sampleCategories.map((c) => (
                            <li key={c.id} className="flex min-h-[56px] items-center gap-3 rounded-2xl px-2 hover:bg-secondary">
                                <GripVertical className="h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                                    <Tags className="h-4 w-4" aria-hidden strokeWidth={1.75} />
                                </span>
                                <span className="min-w-0 flex-1 truncate text-sm font-medium">{bi(c.name)}</span>
                                <span className="tabular text-caption text-ink-muted">{t('categories.count', { count: formatNumber(c.products) })}</span>
                                {c.visible ? (
                                    <Badge tone="success">
                                        <Eye className="h-3.5 w-3.5" aria-hidden />
                                        {t('categories.visible')}
                                    </Badge>
                                ) : (
                                    <Badge tone="neutral">
                                        <EyeOff className="h-3.5 w-3.5" aria-hidden />
                                        {t('categories.hidden')}
                                    </Badge>
                                )}
                            </li>
                        ))}
                    </ul>
                </PreviewCard>

                <PlannedFeatures
                    items={[
                        { icon: GripVertical, text: t('categories.f_order'), req: 'FR-CAT-03' },
                        { icon: Languages, text: t('categories.f_bilingual'), req: 'FR-CAT-01' },
                        { icon: Link2, text: t('categories.f_slug'), req: 'FR-SEO-12' },
                        { icon: EyeOff, text: t('categories.f_hide') },
                    ]}
                />
            </div>
        </DashboardLayout>
    );
}
