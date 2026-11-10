// Onboarding 3 · Building. Placeholder: a scripted build of a sample draft; no AI calls yet. FR-ONB-05
import { router, usePage } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import { useEffect, useState } from 'react';
import { StorePreview } from '@/components/onboarding/store-preview';
import { buttonClasses } from '@/components/ui/button';
import { OnboardingLayout } from '@/layouts/onboarding-layout';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';
import { draftFor, draftPalette } from '@/lib/onboarding';
import type { SharedProps } from '@/types';

const TICK_MS = 1100;

export default function Building({ description }: { description: string | null }) {
    const t = useT();
    const { store, domain, locale } = usePage<SharedProps>().props;
    const draft = draftFor(description, locale);
    const address = `${store?.subdomain}.${domain}`;
    const total = draft.products.length;
    // Ticks: 1 name, 2 homepage, 3…(2+total) one product each, then colours, then shipping.
    const lastTick = total + 4;
    const [tick, setTick] = useState(0);
    const done = tick >= lastTick;

    useEffect(() => {
        if (done) return;
        const timer = setTimeout(() => setTick((n) => n + 1), tick === 0 ? 500 : TICK_MS);
        return () => clearTimeout(timer);
    }, [tick, done]);

    useEffect(() => {
        if (!done) return;
        const timer = setTimeout(() => router.visit('/onboarding/theme'), 2200);
        return () => clearTimeout(timer);
    }, [done]);

    const productsDone = Math.max(0, Math.min(total, tick - 2));
    const tasks = [
        { label: t('flow.task_name', { address }), state: tick >= 1 ? 'done' : 'active' },
        { label: t('flow.task_homepage'), state: tick >= 2 ? 'done' : tick >= 1 ? 'active' : 'todo' },
        {
            label: productsDone >= total ? t('flow.task_products_done', { total }) : t('flow.task_products', { total, done: productsDone }),
            state: productsDone >= total ? 'done' : tick >= 2 ? 'active' : 'todo',
        },
        { label: t('flow.task_colours', { noun: draft.noun }), state: tick >= total + 3 ? 'done' : tick >= total + 2 ? 'active' : 'todo' },
        { label: t('flow.task_shipping', { city: draft.city }), state: tick >= total + 4 ? 'done' : tick >= total + 3 ? 'active' : 'todo' },
    ] as const;
    const percent = Math.round((Math.min(tick, lastTick) / lastTick) * 100);

    return (
        <OnboardingLayout title={t('flow.building_card')} step={2}>
            <h1 className="mv-rise text-center font-display text-4xl font-bold tracking-[-0.03em] sm:text-5xl lg:text-6xl">
                {t('flow.building_title_a')} <span className="text-ink-accent">{t('flow.building_title_b')}</span>
            </h1>

            <div className="mt-10 grid items-start gap-6 lg:mt-14 lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-8">
                <StorePreview
                    className="mv-rise"
                    draft={draft}
                    palette={draftPalette}
                    storeName={store?.name ?? ''}
                    address={address}
                    progress={{ header: tick >= 1, hero: tick >= 2, products: productsDone }}
                />

                <section className="glass-float mv-rise mv-rise-delay rounded-2xl p-5 shadow-lg" aria-labelledby="building-title">
                    <div className="flex items-baseline justify-between gap-3">
                        <h2 id="building-title" className="text-sm font-semibold">
                            {done ? t('flow.building_done') : t('flow.building_card')}
                        </h2>
                        <span className="font-display text-2xl font-light tabular-nums" aria-hidden>
                            {percent}%
                        </span>
                    </div>
                    <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-chart-track" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={percent} aria-labelledby="building-title">
                        <div className="h-full rounded-full bg-primary transition-[width] duration-700 ease-out" style={{ width: `${percent}%` }} />
                    </div>

                    <ul className="mt-5 space-y-3.5" aria-live="polite">
                        {tasks.map((task) => (
                            <li key={task.label.slice(0, 12)} className={cn('flex items-start gap-3 text-sm transition-colors', task.state === 'todo' ? 'text-ink-muted' : 'text-ink')}>
                                <span
                                    className={cn(
                                        'mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full border transition-colors',
                                        task.state === 'done' && 'border-success bg-success text-white',
                                        task.state === 'active' && 'border-primary',
                                        task.state === 'todo' && 'border-line-strong',
                                    )}
                                    aria-hidden
                                >
                                    {task.state === 'done' ? <Check className="h-3 w-3" strokeWidth={3} /> : null}
                                    {task.state === 'active' ? <span className="h-2 w-2 animate-pulse rounded-full bg-primary" /> : null}
                                </span>
                                <span className="leading-snug">{task.label}</span>
                            </li>
                        ))}
                    </ul>

                    <p className="mt-5 text-caption text-ink-muted">{t('flow.building_note')}</p>
                    <p className="mt-1 text-caption text-ink-muted">{t('flow.placeholder_note')}</p>

                    {done ? (
                        <button type="button" onClick={() => router.visit('/onboarding/theme')} className={buttonClasses('primary', 'lg', 'mv-rise mt-5 w-full')}>
                            {t('flow.continue_theme')}
                            <ArrowRight className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                        </button>
                    ) : null}
                </section>
            </div>
        </OnboardingLayout>
    );
}
