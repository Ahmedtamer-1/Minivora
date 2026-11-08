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

/** "Thursday, 7 November" in Cairo time. */
export function formatToday(locale: SharedProps['locale']): string {
    const parts = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG-u-nu-latn' : 'en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'Africa/Cairo',
    }).formatToParts(new Date());
    const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '';

    return `${get('weekday')}${locale === 'ar' ? '،' : ','} ${get('day')} ${get('month')}`;
}

/** Part of the day in Cairo, for the greeting. */
export function dayPart(): 'morning' | 'afternoon' | 'evening' {
    const hour = Number(new Intl.DateTimeFormat('en-GB', { hour: 'numeric', hourCycle: 'h23', timeZone: 'Africa/Cairo' }).format(new Date()));
    return hour < 12 ? 'morning' : hour < 18 ? 'afternoon' : 'evening';
}

/** Western-digit number formatting (NFR-USE-05). */
export function formatNumber(value: number): string {
    return value.toLocaleString('en-US');
}
