import { Link, usePage } from '@inertiajs/react';
import { Languages, Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

/** Language switch (saved on the account) and light/dark toggle (saved in this browser). */
export function Preferences() {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const [theme, setTheme] = useState<'light' | 'dark'>('light');

    useEffect(() => {
        const current = document.documentElement.dataset.theme;
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        setTheme(current === 'dark' || (!current && prefersDark) ? 'dark' : 'light');
    }, []);

    const toggleTheme = () => {
        const next = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try {
            localStorage.setItem('mv-theme', next);
        } catch {
            // Storage blocked: the theme still applies for this page view.
        }
        setTheme(next);
    };

    const other = locale === 'ar' ? 'en' : 'ar';

    return (
        <div className="flex items-center gap-1">
            <Link
                href={`/locale/${other}`}
                className="inline-flex h-9 items-center gap-1.5 rounded-md px-2.5 text-sm text-ink-muted hover:bg-secondary hover:text-ink"
                preserveScroll
            >
                <Languages className="h-4 w-4" aria-hidden />
                {other === 'ar' ? t('common.arabic') : t('common.english')}
            </Link>
            <button
                type="button"
                onClick={toggleTheme}
                className="inline-flex h-9 w-9 items-center justify-center rounded-md text-ink-muted hover:bg-secondary hover:text-ink"
                aria-label={theme === 'dark' ? t('common.theme_light') : t('common.theme_dark')}
            >
                {theme === 'dark' ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
            </button>
        </div>
    );
}
