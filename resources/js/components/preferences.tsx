import { Link, usePage } from '@inertiajs/react';
import { Globe, Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/cn';
import { PREFS_EVENT, readPrefs, savePrefs } from '@/lib/display-prefs';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

// Figma "Round": 44px glass circle with an 18px icon.
export const roundButton =
    'glass-strong inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-ink shadow-sm transition-colors hover:bg-elevated';

/** Switch the dashboard language (saved on the account). */
export function LanguageButton({ className }: { className?: string }) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const other = locale === 'ar' ? 'en' : 'ar';
    const label = other === 'ar' ? t('common.arabic') : t('common.english');

    return (
        <Link href={`/locale/${other}`} preserveScroll className={cn(roundButton, className)} aria-label={`${t('common.language')}: ${label}`} title={label}>
            <Globe className="h-[18px] w-[18px]" aria-hidden />
        </Link>
    );
}

/**
 * Light/dark override, saved on this device. The OS setting applies until someone picks one here or
 * in Settings › Accessibility; both stay in step through the display-prefs change event.
 */
export function ThemeButton({ className }: { className?: string }) {
    const t = useT();
    const [theme, setTheme] = useState<'light' | 'dark'>('light');

    useEffect(() => {
        const sync = () => {
            const current = document.documentElement.dataset.theme;
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            setTheme(current === 'dark' || (!current && prefersDark) ? 'dark' : 'light');
        };
        sync();
        window.addEventListener(PREFS_EVENT, sync);
        return () => window.removeEventListener(PREFS_EVENT, sync);
    }, []);

    const toggle = () => savePrefs({ ...readPrefs(), theme: theme === 'dark' ? 'light' : 'dark' });

    return (
        <button type="button" onClick={toggle} className={cn(roundButton, className)} aria-label={theme === 'dark' ? t('common.theme_light') : t('common.theme_dark')}>
            {theme === 'dark' ? <Sun className="h-[18px] w-[18px]" aria-hidden /> : <Moon className="h-[18px] w-[18px]" aria-hidden />}
        </button>
    );
}

export function Preferences() {
    return (
        <div className="flex items-center gap-3">
            <LanguageButton />
            <ThemeButton />
        </div>
    );
}
