// Media library: images and videos, bulk actions, AI alt text. FR-BLD-04, FR-CAT-08, FR-SEO-13. Placeholder for a later phase.
import { ImageIcon, Upload } from 'lucide-react';
import { PlannedNotice, PreviewCard } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { useT } from '@/lib/i18n';

const tiles = Array.from({ length: 12 }, (_, i) => ({ id: i, alt: i % 5 !== 3 }));

export default function BuilderMedia() {
    const t = useT();

    return (
        <DashboardLayout title={t('nav.builder_media')}>
            <PageHeader
                title={t('media.title')}
                description={t('media.subtitle')}
                action={
                    <Button disabled>
                        <Upload className="h-4 w-4" aria-hidden />
                        {t('media.upload')}
                    </Button>
                }
            />
            <PlannedNotice requirements={['FR-BLD-04', 'FR-CAT-08', 'FR-SEO-13']} body={t('builder.later')} />

            <PreviewCard title={t('media.library')} description={t('media.library_hint')}>
                <ul className="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    {tiles.map((tile) => (
                        <li key={tile.id} className="relative">
                            <div className="grid aspect-square place-items-center rounded-2xl bg-secondary text-ink-muted" aria-hidden>
                                <ImageIcon className="h-6 w-6" strokeWidth={1.5} />
                            </div>
                            {!tile.alt ? (
                                <Badge tone="warning" className="absolute start-1.5 top-1.5 h-6 text-xs">
                                    {t('media.no_alt')}
                                </Badge>
                            ) : null}
                        </li>
                    ))}
                </ul>
            </PreviewCard>
        </DashboardLayout>
    );
}
