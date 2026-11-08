// Money is integer piasters everywhere (1 EGP = 100). Formats with Western digits (NFR-USE-05).

export function formatPiasters(piasters: number, locale: 'ar' | 'en' = 'en'): string {
    const sign = piasters < 0 ? '-' : '';
    const abs = Math.abs(Math.trunc(piasters));
    const pounds = Math.floor(abs / 100).toLocaleString('en-US');
    const rest = String(abs % 100).padStart(2, '0');

    return `${sign}${pounds}.${rest} ${locale === 'ar' ? 'ج.م' : 'EGP'}`;
}

/** "1,250 EGP" for whole pounds, "99.50 EGP" otherwise. Figma shows prices without ".00". */
export function formatPounds(piasters: number, locale: 'ar' | 'en' = 'en'): string {
    const abs = Math.abs(Math.trunc(piasters));
    const whole = abs % 100 === 0;
    const amount = (abs / 100).toLocaleString('en-US', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: 2 });

    return `${piasters < 0 ? '-' : ''}${amount} ${locale === 'ar' ? 'ج.م' : 'EGP'}`;
}

/** Piasters to the plain pounds string a form field shows ("2150", "99.5"). */
export function piastersToInput(piasters: number | null | undefined): string {
    if (piasters === null || piasters === undefined) return '';
    return (piasters / 100).toString();
}
