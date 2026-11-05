import { useLocale } from '@/lib/i18n';
import { formatPiasters } from '@/lib/money';

/** Renders integer piasters as EGP. */
export function Money({ piasters }: { piasters: number }) {
    const locale = useLocale();
    return <span dir="ltr">{formatPiasters(piasters, locale)}</span>;
}
