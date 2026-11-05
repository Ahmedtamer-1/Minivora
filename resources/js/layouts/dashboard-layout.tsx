import { Head, Link, usePage } from '@inertiajs/react';
import { MailWarning } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Flash } from '@/components/flash';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';
import { Sidebar } from './sidebar';
import { Topbar } from './topbar';

/** Dashboard shell: sidebar, top bar with store switcher, email-verification banner, flash. */
export function DashboardLayout({ title, children }: { title: string; children: ReactNode }) {
    const t = useT();
    const { auth } = usePage<SharedProps>().props;
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <div className="min-h-screen bg-canvas">
            <Head title={title} />

            <aside className="fixed inset-y-0 start-0 z-40 hidden w-64 border-e border-line bg-elevated lg:block">
                <Sidebar />
            </aside>

            {menuOpen ? (
                <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
                    <button type="button" className="absolute inset-0 bg-black/40" aria-label="Close" onClick={() => setMenuOpen(false)} />
                    <aside className="absolute inset-y-0 start-0 w-72 max-w-[85vw] bg-elevated shadow-lg">
                        <Sidebar onNavigate={() => setMenuOpen(false)} />
                    </aside>
                </div>
            ) : null}

            <div className="lg:ps-64">
                <Topbar onMenu={() => setMenuOpen(true)} />

                {auth.user && !auth.user.email_verified ? (
                    <div className="flex flex-wrap items-center gap-2 border-b border-line bg-warning-subtle px-4 py-2.5 text-sm text-warning">
                        <MailWarning className="h-4 w-4" aria-hidden />
                        <span>{t('verify.banner')}</span>
                        <Link href="/email/verify" className="font-medium underline">
                            {t('checklist.go')}
                        </Link>
                    </div>
                ) : null}

                <main className="mx-auto max-w-5xl px-4 py-6 sm:py-8">{children}</main>
            </div>

            <Flash />
        </div>
    );
}

export function PageHeader({ title, description, action }: { title: string; description?: string; action?: ReactNode }) {
    return (
        <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 className="text-2xl font-semibold">{title}</h1>
                {description ? <p className="mt-1 text-ink-muted">{description}</p> : null}
            </div>
            {action}
        </div>
    );
}
