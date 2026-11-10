// Storefront theme: colours, fonts, corners, light or dark. FR-BLD-03, FR-BLD-08. Placeholder for a later phase.
import { Moon, Sun } from 'lucide-react';
import { PlannedNotice, PreviewCard } from '@/components/planned';
import { cn } from '@/lib/cn';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { useT } from '@/lib/i18n';

const palettes = [
    { key: 'nile', colors: ['#1D7AA0', '#7FE0C8', '#0B1A26'] },
    { key: 'desert', colors: ['#B5651D', '#F2D7B6', '#2B1A10'] },
    { key: 'olive', colors: ['#5B6B2F', '#DCE5B9', '#1E2410'] },
    { key: 'rose', colors: ['#B23A5B', '#F6D3DC', '#2A0F18'] },
];

const fonts = ['Outfit', 'Cairo', 'Tajawal', 'IBM Plex Sans Arabic'];

export default function BuilderTheme() {
    const t = useT();

    return (
        <DashboardLayout title={t('nav.builder_theme')}>
            <PageHeader title={t('theme.title')} description={t('theme.subtitle')} />
            <PlannedNotice requirements={['FR-BLD-03', 'FR-BLD-08']} body={t('builder.later')} />

            <div className="grid gap-6 lg:grid-cols-2">
                <PreviewCard title={t('theme.colors')}>
                    <ul className="grid grid-cols-2 gap-3">
                        {palettes.map((p, i) => (
                            <li key={p.key} className={cn('rounded-2xl border p-3', i === 0 ? 'border-line-focus ring-1 ring-line-focus' : 'border-line')}>
                                <div className="flex gap-1.5" aria-hidden>
                                    {p.colors.map((c) => (
                                        <span key={c} className="h-8 flex-1 rounded-lg" style={{ background: c }} />
                                    ))}
                                </div>
                                <p className="mt-2 text-sm">{t(`theme.palette_${p.key}`)}</p>
                            </li>
                        ))}
                    </ul>
                </PreviewCard>

                <PreviewCard title={t('theme.type')}>
                    <ul className="divide-y divide-line">
                        {fonts.map((f, i) => (
                            <li key={f} className="flex min-h-[56px] items-center justify-between gap-3">
                                <span className="text-sm" dir="ltr">
                                    {f}
                                </span>
                                <span className={cn('text-lg', i === 0 ? 'text-ink' : 'text-ink-muted')} style={{ fontFamily: `'${f}', system-ui` }} aria-hidden>
                                    Aa أبجد
                                </span>
                            </li>
                        ))}
                    </ul>
                </PreviewCard>

                <PreviewCard title={t('theme.shape')}>
                    <div className="grid grid-cols-3 gap-3" aria-hidden>
                        {['rounded-none', 'rounded-xl', 'rounded-full'].map((r) => (
                            <div key={r} className={cn('grid h-12 place-items-center bg-secondary text-caption', r)}>
                                Aa
                            </div>
                        ))}
                    </div>
                    <p className="mt-3 text-caption text-ink-muted">{t('theme.shape_hint')}</p>
                </PreviewCard>

                <PreviewCard title={t('theme.mode')}>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="flex items-center gap-2 rounded-2xl border border-line-focus p-3 text-sm ring-1 ring-line-focus">
                            <Sun className="h-4 w-4" aria-hidden />
                            {t('common.theme_light')}
                        </div>
                        <div className="flex items-center gap-2 rounded-2xl border border-line p-3 text-sm">
                            <Moon className="h-4 w-4" aria-hidden />
                            {t('common.theme_dark')}
                        </div>
                    </div>
                </PreviewCard>
            </div>
        </DashboardLayout>
    );
}
