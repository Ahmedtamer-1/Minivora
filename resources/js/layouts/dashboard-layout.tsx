import { Head, Link, usePage } from '@inertiajs/react';
import { MailWarning } from 'lucide-react';
import type { ReactNode } from 'react';
import { Flash } from '@/components/flash';
import { LogoMark } from '@/components/logo';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';
import { Rail, TabBar } from './rail';
import { StoreLine, TopActions } from './topbar';

/** Figma "Backdrop / Spheres": soft glows plus three large frosted spheres. Decorative only. */
function Backdrop() {
    return (
        <div aria-hidden className="backdrop-glow pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div className="sphere absolute -start-[120px] -top-[220px] h-[480px] w-[480px]" />
            <div className="sphere absolute -end-[160px] -top-[120px] h-[380px] w-[380px]" />
            <div className="sphere absolute -bottom-[260px] -start-[200px] h-[480px] w-[480px]" />
            <div className="sphere absolute -bottom-[300px] -end-[140px] h-[560px] w-[560px]" />
        </div>
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

            <header className="flex flex-wrap items-start gap-x-4 gap-y-4 px-4 pt-6 sm:px-6 lg:flex-nowrap lg:pe-8 lg:ps-0 lg:pt-10">
                <Link href="/dashboard" className="mt-1 hidden w-32 shrink-0 justify-center text-brand-mark lg:flex" aria-label={t('nav.home')}>
                    <LogoMark className="h-[33px] w-10" />
                </Link>
                <Link href="/dashboard" className="me-auto mt-2 shrink-0 text-brand-mark lg:hidden" aria-label={t('nav.home')}>
                    <LogoMark className="h-7 w-[34px]" />
                </Link>
                {/* Phones: logo and actions on the first row, heading on its own row below. */}
                <div className="order-last min-w-0 basis-full lg:order-none lg:flex-1 lg:basis-auto">{heading ?? <div className="lg:pt-3"><StoreLine /></div>}</div>
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

            <main id="main" className="px-4 pb-28 pt-6 sm:px-6 lg:pb-10 lg:pe-8 lg:ps-32">
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
        <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div className="min-w-0">
                {eyebrow}
                <h1 className="text-greeting">{title}</h1>
                {description ? <p className="mt-1 text-sm text-ink-muted">{description}</p> : null}
            </div>
            {action ? <div className="flex flex-wrap items-center gap-3">{action}</div> : null}
        </div>
    );
}
