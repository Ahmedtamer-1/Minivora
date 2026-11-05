// Money is integer piasters everywhere (1 EGP = 100). Formats with Western digits (NFR-USE-05).

export function formatPiasters(piasters: number, locale: 'ar' | 'en' = 'en'): string {
    const sign = piasters < 0 ? '-' : '';
    const abs = Math.abs(Math.trunc(piasters));
    const pounds = Math.floor(abs / 100).toLocaleString('en-US');
    const rest = String(abs % 100).padStart(2, '0');

    return `${sign}${pounds}.${rest} ${locale === 'ar' ? 'ج.م' : 'EGP'}`;
}
