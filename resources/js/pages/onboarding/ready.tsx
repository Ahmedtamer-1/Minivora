// Onboarding 5 · You're ready. The store stays "opening soon" until the owner publishes.
import { usePage } from '@inertiajs/react';
import { ArrowUpRight, BarChart3, Bell, Check, ExternalLink, type LucideIcon } from 'lucide-react';
import { StoreStatusBadge } from '@/components/status-badge';
import { ButtonLink, buttonClasses } from '@/components/ui/button';
import { OnboardingLayout } from '@/layouts/onboarding-layout';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

export default function Ready() {
    const t = useT();
    const { store, domain, auth } = usePage<SharedProps>().props;
    const firstName = (auth.user?.name ?? '').trim().split(/\s+/)[0] ?? '';
    const cards: Array<{ icon: LucideIcon; a: string; b: string; body: string }> = [
        { icon: Bell, a: t('flow.card1_a'), b: t('flow.card1_b'), body: t('flow.card1_body') },
        { icon: Check, a: t('flow.card2_a'), b: t('flow.card2_b'), body: t('flow.card2_body') },
        { icon: BarChart3, a: t('flow.card3_a'), b: t('flow.card3_b'), body: t('flow.card3_body') },
    ];

    return (
        <OnboardingLayout title={t('flow.ready_title')} step={4} className="items-center justify-center text-center">
            <h1 className="mv-rise font-display text-4xl font-bold tracking-[-0.03em] sm:text-5xl lg:text-6xl">
                {t('flow.ready_title')} <span className="text-ink-accent">{firstName}.</span>
            </h1>
            <p className="mv-rise mx-auto mt-4 max-w-lg text-sm leading-relaxed text-ink-muted sm:text-base">{t('flow.ready_body', { store: store?.name ?? '' })}</p>

            <a
                href="/preview"
                target="_blank"
                rel="noopener"
                className="glass-float mv-rise mv-rise-delay mt-7 inline-flex max-w-full items-center gap-3 rounded-full py-2.5 pe-3 ps-6 shadow-md transition-transform hover:-translate-y-0.5"
                aria-label={t('flow.open_store')}
            >
                <span dir="ltr" className="truncate font-display text-xl sm:text-2xl">
                    <span className="font-semibold">{store?.subdomain}</span>
                    <span className="text-ink-muted">.{domain}</span>
                </span>
                {store ? <StoreStatusBadge status={store.status} /> : null}
                <ExternalLink className="h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
            </a>

            <ul className="mv-stagger mt-10 grid w-full max-w-4xl gap-4 text-start sm:grid-cols-3">
                {cards.map(({ icon: Icon, a, b, body }) => (
                    <li key={a} className="card p-5">
                        <span className="grid h-9 w-9 place-items-center rounded-full bg-accent-subtle text-ink-accent" aria-hidden>
                            <Icon className="h-4 w-4" />
                        </span>
                        <h2 className="mt-4 font-display text-lg leading-snug">
                            {a} <span className="text-ink-accent">{b}</span>
                        </h2>
                        <p className="mt-2 text-caption text-ink-muted">{body}</p>
                    </li>
                ))}
            </ul>

            <div className="mt-10 flex flex-wrap justify-center gap-3">
                <a href="/preview" target="_blank" rel="noopener" className={buttonClasses('glass', 'lg')}>
                    <ExternalLink className="h-4 w-4" aria-hidden />
                    {t('flow.preview_store')}
                </a>
                <ButtonLink href="/dashboard" size="lg" className="shadow-md">
                    <ArrowUpRight className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                    {t('flow.open_dashboard')}
                </ButtonLink>
            </div>
        </OnboardingLayout>
    );
}
