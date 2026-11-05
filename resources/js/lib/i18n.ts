// Arabic/English strings for the dashboard. Strings live in lang/ar.json and lang/en.json and arrive
// as the shared `translations` prop for the current language.
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

export type Translate = (key: string, replacements?: Record<string, string | number>) => string;

export function useT(): Translate {
    const { translations } = usePage<SharedProps>().props;

    return (key, replacements = {}) => {
        let text = translations[key] ?? key;
        for (const [name, value] of Object.entries(replacements)) {
            text = text.replaceAll(`{${name}}`, String(value));
        }
        return text;
    };
}

export function useLocale(): SharedProps['locale'] {
    return usePage<SharedProps>().props.locale;
}

/** Dates in the dashboard language, Cairo time, Western digits (NFR-USE-05). */
export function formatDate(iso: string, locale: SharedProps['locale'], withTime = false): string {
    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG-u-nu-latn' : 'en-GB', {
        dateStyle: 'medium',
        ...(withTime ? { timeStyle: 'short' } : {}),
        timeZone: 'Africa/Cairo',
    }).format(new Date(iso));
}
