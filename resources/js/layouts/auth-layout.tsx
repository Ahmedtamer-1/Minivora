import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Logo } from '@/components/logo';
import { Preferences } from '@/components/preferences';
import { GlassPanel } from '@/components/ui/glass-panel';

/** Sign-in, create-store and invitation pages: aurora background with a frosted panel. */
export function AuthLayout({ title, children, aside }: { title: string; children: ReactNode; aside?: ReactNode }) {
    return (
        <div className="aurora min-h-screen">
            <Head title={title} />
            <header className="mx-auto flex max-w-6xl items-center justify-between px-4 py-5">
                <Logo />
                <Preferences />
            </header>
            <main className="mx-auto grid max-w-6xl items-center gap-10 px-4 pb-16 pt-4 lg:grid-cols-2 lg:pt-12">
                {aside ? <div className="hidden lg:block">{aside}</div> : null}
                <GlassPanel className={aside ? 'w-full p-6 sm:p-8' : 'mx-auto w-full max-w-md p-6 sm:p-8 lg:col-span-2'}>{children}</GlassPanel>
            </main>
        </div>
    );
}
