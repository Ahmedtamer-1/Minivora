import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AnimatedBackdrop } from '@/components/animated-backdrop';
import { Logo } from '@/components/logo';
import { Preferences } from '@/components/preferences';
import { GlassPanel } from '@/components/ui/glass-panel';

/** Sign-in, create-store and invitation pages: living aurora background with a frosted panel, filling the screen. */
export function AuthLayout({ title, children, aside }: { title: string; children: ReactNode; aside?: ReactNode }) {
    return (
        <div className="relative isolate flex min-h-screen flex-col">
            <Head title={title} />
            <AnimatedBackdrop />
            <header className="mx-auto flex w-full max-w-[1440px] items-center justify-between px-5 py-5 sm:px-8 lg:px-12 lg:py-7">
                <Logo />
                <Preferences />
            </header>
            <main className="vt-main mx-auto grid w-full max-w-[1440px] flex-1 items-center gap-12 px-5 pb-16 pt-4 sm:px-8 lg:grid-cols-2 lg:gap-20 lg:px-12 xl:gap-28">
                {aside ? <div className="mv-rise hidden lg:block">{aside}</div> : null}
                <GlassPanel
                    className={
                        aside
                            ? 'mv-rise mv-rise-delay mx-auto w-full max-w-xl p-6 sm:p-10'
                            : 'mv-rise mx-auto w-full max-w-[480px] p-6 sm:p-10 lg:col-span-2'
                    }
                >
                    {children}
                </GlassPanel>
            </main>
        </div>
    );
}
