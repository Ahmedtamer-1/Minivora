import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AnimatedBackdrop, ParallaxLayer } from '@/components/animated-backdrop';
import { Logo } from '@/components/logo';
import { Preferences } from '@/components/preferences';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';

export const ONBOARDING_STEPS = 4;

/** "Step 2 of 4" with a dash per step; the current one is long, finished ones are filled. */
export function StepPill({ step }: { step: number }) {
    const t = useT();
    return (
        <div
            className="glass-strong inline-flex h-8 items-center gap-2.5 rounded-full px-3.5 text-[0.6875rem] font-medium text-ink-muted shadow-sm"
            role="progressbar"
            aria-valuemin={1}
            aria-valuemax={ONBOARDING_STEPS}
            aria-valuenow={step}
            aria-label={t('flow.step', { step, total: ONBOARDING_STEPS })}
        >
            <span>{t('flow.step', { step, total: ONBOARDING_STEPS })}</span>
            <span className="flex items-center gap-1" aria-hidden>
                {Array.from({ length: ONBOARDING_STEPS }, (_, i) => (
                    <span
                        key={i}
                        className={cn('h-1 rounded-full transition-all duration-500', i + 1 === step ? 'w-5 bg-primary' : i + 1 < step ? 'w-2.5 bg-primary' : 'w-2.5 bg-line-strong')}
                    />
                ))}
            </span>
        </div>
    );
}

/**
 * Full-screen frame for the first run (Figma "Onboarding 1–5"): wordmark, step pill and
 * language/theme on top, frosted spheres in the corners, content centred below.
 */
export function OnboardingLayout({ title, step, children, className }: { title: string; step: number; children: ReactNode; className?: string }) {
    return (
        <div className="relative isolate flex min-h-screen flex-col">
            <Head title={title} />
            <AnimatedBackdrop>
                <ParallaxLayer depth={0.12}>
                    <div className="sphere absolute -start-[140px] -top-[140px] h-[380px] w-[380px]" />
                    <div className="sphere absolute -end-[120px] -top-[160px] h-[340px] w-[340px]" />
                </ParallaxLayer>
                <ParallaxLayer depth={0.2}>
                    <div className="sphere absolute -bottom-[220px] -start-[160px] h-[420px] w-[420px]" />
                    <div className="sphere absolute -bottom-[260px] -end-[140px] h-[520px] w-[520px]" />
                </ParallaxLayer>
            </AnimatedBackdrop>

            <header className="mx-auto grid w-full max-w-[1440px] grid-cols-[1fr_auto] items-center gap-4 px-5 pt-[max(1.25rem,env(safe-area-inset-top))] sm:grid-cols-[1fr_auto_1fr] sm:px-8 lg:px-12 lg:pt-7">
                <div className="flex">
                    <Logo href="/dashboard" className="text-[1.375rem] sm:text-[1.5rem]" />
                </div>
                <div className="order-last col-span-2 flex justify-center sm:order-none sm:col-span-1">
                    <StepPill step={step} />
                </div>
                <div className="flex justify-end">
                    <Preferences />
                </div>
            </header>

            <main className={cn('vt-main mx-auto flex w-full max-w-[1200px] flex-1 flex-col px-5 pb-16 pt-8 sm:px-8 lg:pt-12', className)}>{children}</main>
        </div>
    );
}
