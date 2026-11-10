// Onboarding 4 · Make it yours: colours, logo and corners, with the draft updating live.
import { useForm, usePage } from '@inertiajs/react';
import { Check, Loader2, Upload } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StorePreview } from '@/components/onboarding/store-preview';
import { Button } from '@/components/ui/button';
import { OnboardingLayout } from '@/layouts/onboarding-layout';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';
import { draftFor, paletteFor, palettes } from '@/lib/onboarding';
import type { SharedProps } from '@/types';

interface Props {
    description: string | null;
    theme: { palette: string; radius: number } | null;
}

export default function Theme({ description, theme }: Props) {
    const t = useT();
    const { store, domain, locale } = usePage<SharedProps>().props;
    const { data, setData, post, processing } = useForm({ palette: theme?.palette ?? 'emerald', radius: theme?.radius ?? 14 });
    // Placeholder: the file stays in the browser until the media library ships.
    const [logo, setLogo] = useState<string | null>(null);
    const palette = paletteFor(data.palette);

    const surprise = () => {
        const others = palettes.filter((p) => p.key !== data.palette);
        const next = others[Math.floor(Math.random() * others.length)];
        setData({ palette: next.key, radius: [0, 6, 14, 20][Math.floor(Math.random() * 4)] });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/onboarding/theme');
    };

    return (
        <OnboardingLayout title={t('flow.theme')} step={3}>
            <h1 className="mv-rise text-center font-display text-4xl font-bold tracking-[-0.03em] sm:text-5xl lg:text-6xl">
                {t('flow.theme_title_a')} <span className="text-ink-accent">{t('flow.theme_title_b')}</span>
            </h1>

            <div className="mt-10 grid items-start gap-6 lg:mt-14 lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-8">
                <StorePreview className="mv-rise" draft={draftFor(description, locale)} palette={palette} radius={data.radius} storeName={store?.name ?? ''} address={`${store?.subdomain}.${domain}`} />

                <form onSubmit={submit} className="glass-float mv-rise mv-rise-delay rounded-2xl p-5 shadow-lg sm:p-6">
                    <h2 className="font-display text-2xl font-light">{t('flow.theme')}</h2>
                    <p className="mt-1 text-caption text-ink-muted">{t('flow.theme_body')}</p>

                    <fieldset className="mt-6">
                        <legend className="text-sm font-semibold">{t('flow.colours')}</legend>
                        <div className="mt-3 flex flex-wrap gap-2.5">
                            {palettes.map((p) => {
                                const on = p.key === data.palette;
                                return (
                                    <label key={p.key} className="cursor-pointer">
                                        <input type="radio" name="palette" value={p.key} checked={on} onChange={() => setData('palette', p.key)} className="peer sr-only" />
                                        <span
                                            className={cn(
                                                'relative grid h-11 w-11 place-items-center rounded-full border border-line shadow-sm transition-transform peer-focus-visible:ring-2 peer-focus-visible:ring-line-focus peer-focus-visible:ring-offset-2',
                                                on ? 'scale-105 ring-2 ring-primary ring-offset-2 ring-offset-surface' : 'hover:scale-105',
                                            )}
                                            style={{ background: `linear-gradient(135deg, ${p.bg} 0 50%, ${p.accent} 50% 100%)` }}
                                        >
                                            {on ? <Check className="h-4 w-4 drop-shadow" style={{ color: '#fff' }} aria-hidden strokeWidth={3} /> : null}
                                            <span className="sr-only">{t(`flow.palette_${p.key}`)}</span>
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                        <p className="mt-2 text-sm font-semibold" aria-live="polite">
                            {t(`flow.palette_${palette.key}`)}
                        </p>
                    </fieldset>

                    <div className="mt-6">
                        <p className="text-sm font-semibold">{t('flow.logo')}</p>
                        <label className="mt-3 flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-line-strong p-4 transition-colors hover:bg-secondary focus-within:ring-2 focus-within:ring-line-focus">
                            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                                {logo ? <Check className="h-4 w-4" aria-hidden /> : <Upload className="h-4 w-4" aria-hidden />}
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate text-sm font-semibold">{logo ?? t('flow.logo_upload')}</span>
                                <span className="block text-caption text-ink-muted">{t('flow.logo_hint')}</span>
                            </span>
                            <input type="file" accept="image/png,image/svg+xml" className="sr-only" onChange={(e) => setLogo(e.target.files?.[0]?.name ?? null)} />
                        </label>
                    </div>

                    <div className="mt-6">
                        <div className="flex items-center justify-between text-sm">
                            <label htmlFor="radius" className="font-semibold">
                                {t('flow.corners')}
                            </label>
                            <span className="text-caption text-ink-muted">{data.radius < 8 ? t('flow.corners_sharp') : t('flow.corners_round')}</span>
                        </div>
                        <input
                            id="radius"
                            type="range"
                            min={0}
                            max={24}
                            step={1}
                            value={data.radius}
                            onChange={(e) => setData('radius', Number(e.target.value))}
                            className="mt-3 w-full accent-[var(--mv-action-primary)]"
                        />
                    </div>

                    <Button type="submit" size="lg" className="mt-7 w-full" disabled={processing}>
                        {processing ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden /> : null}
                        {t('flow.theme_continue')}
                    </Button>
                    <button type="button" onClick={surprise} className="mt-2 w-full rounded-full py-2 text-sm font-medium text-ink-link hover:underline">
                        {t('flow.surprise')}
                    </button>
                </form>
            </div>
        </OnboardingLayout>
    );
}
