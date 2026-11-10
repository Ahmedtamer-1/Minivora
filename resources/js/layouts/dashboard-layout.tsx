import { Head, Link, usePage } from '@inertiajs/react';
import { MailWarning } from 'lucide-react';
import type { ReactNode } from 'react';
import { AnimatedBackdrop, ParallaxLayer } from '@/components/animated-backdrop';
import { Flash } from '@/components/flash';
import { LogoMark } from '@/components/logo';
import { SectionNav } from '@/components/section-nav';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';
import { Rail, TabBar } from './rail';
import { StoreLine, TopActions } from './topbar';

/** Figma "Backdrop / Spheres": slowly drifting glows plus large frosted spheres, with scroll parallax. Decorative only. */
function Backdrop() {
    return (
        // The frosted spheres sit nearest the viewer, so they move most when scrolling.
        <AnimatedBackdrop variant="dashboard">
            <ParallaxLayer depth={0.1}>
                <div className="sphere absolute -end-[160px] -top-[120px] h-[380px] w-[380px]" />
                <div className="sphere absolute -bottom-[300px] -end-[140px] h-[560px] w-[560px]" />
            </ParallaxLayer>
            <ParallaxLayer depth={0.2}>
                <div className="sphere absolute -start-[120px] -top-[220px] h-[480px] w-[480px]" />
                <div className="sphere absolute -bottom-[260px] -start-[200px] h-[480px] w-[480px]" />
            </ParallaxLayer>
        </AnimatedBackdrop>
    );
}

interface LayoutProps {
    title: string;
    /** Left side of the header. Defaults to the store line (name + status). */
    heading?: ReactNode;
    children: ReactNode;
}

/**
 * Dashboard shell from Figma "Dashboard · HIG pass": logo over a floating glass rail on desktop,
 * a labelled tab bar on phones, header actions top-right, content on frosted cards.
 */
export function DashboardLayout({ title, heading, children }: LayoutProps) {
    const t = useT();
    const { auth } = usePage<SharedProps>().props;

    return (
        <div className="relative isolate min-h-screen">
            <Head title={title} />
            <a href="#main" className="sr-only z-50 rounded-full bg-elevated px-4 py-2 focus:not-sr-only focus:fixed focus:start-4 focus:top-4">
                {t('nav.skip')}
            </a>
            <Backdrop />

            {/*
              Desktop: the mark sits in the rail's column (start-8, 56px wide) so the two share a centre
              line, and the heading starts at 128px, exactly where the page content starts below it.
              Phones: mark and actions share the first row, centred on the 44px buttons; heading below.
            */}
            <header className="flex flex-wrap items-center gap-x-3 gap-y-5 px-4 pt-[max(1.25rem,env(safe-area-inset-top))] sm:px-6 sm:pt-6 lg:flex-nowrap lg:items-start lg:gap-0 lg:pe-8 lg:ps-8 lg:pt-10">
                <Link
                    href="/dashboard"
                    className="hidden h-11 w-14 shrink-0 items-center justify-center rounded-full text-brand-mark transition-opacity hover:opacity-80 lg:flex"
                    aria-label={t('nav.home')}
                >
                    <LogoMark className="h-[30px] w-9" />
                </Link>
                <Link href="/dashboard" className="me-auto flex h-11 shrink-0 items-center text-brand-mark lg:hidden" aria-label={t('nav.home')}>
                    <LogoMark className="h-7 w-[34px]" />
                </Link>
                <div className="order-last min-w-0 basis-full lg:order-none lg:ms-10 lg:flex-1 lg:basis-auto lg:pe-6">
                    {heading ?? (
                        <div className="lg:flex lg:min-h-11 lg:items-center">
                            <StoreLine />
                        </div>
                    )}
                </div>
                <TopActions />
            </header>

            <Rail />

            {auth.user && !auth.user.email_verified ? (
                <div className="px-4 pt-4 sm:px-6 lg:pe-8 lg:ps-32">
                    <div role="status" className="flex flex-wrap items-center gap-2 rounded-full bg-warning-subtle px-4 py-2.5 text-sm text-warning">
                        <MailWarning className="h-4 w-4" aria-hidden />
                        <span>{t('verify.banner')}</span>
                        <Link href="/email/verify" className="font-semibold underline underline-offset-2">
                            {t('verify.banner_action')}
                        </Link>
                    </div>
                </div>
            ) : null}

            <main id="main" className="vt-main px-4 pb-32 pt-6 sm:px-6 lg:pb-12 lg:pe-8 lg:ps-32 lg:pt-8">
                <SectionNav />
                {children}
            </main>

            <TabBar />
            <Flash />
        </div>
    );
}

/** Page title row: title + subtitle on the leading side, actions on the trailing side. */
export function PageHeader({ title, description, action, eyebrow }: { title: ReactNode; description?: ReactNode; action?: ReactNode; eyebrow?: ReactNode }) {
    return (
        <div className="mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-4 lg:mb-8">
            <div className="min-w-0 max-w-2xl">
                {eyebrow}
                <h1 className="text-[1.75rem] font-light leading-tight tracking-[-0.03em] sm:text-greeting">{title}</h1>
                {description ? <p className="mt-1.5 text-sm leading-relaxed text-ink-muted">{description}</p> : null}
            </div>
            {action ? <div className="flex w-full flex-wrap items-center gap-3 sm:w-auto">{action}</div> : null}
        </div>
    );
}
