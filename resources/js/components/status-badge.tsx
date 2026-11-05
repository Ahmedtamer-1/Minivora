import { Badge, type Tone } from '@/components/ui/badge';
import { useT } from '@/lib/i18n';
import type { StoreStatus } from '@/types';

const tone: Record<StoreStatus, Tone> = { live: 'success', opening_soon: 'warning', suspended: 'danger' };

export function StoreStatusBadge({ status }: { status: StoreStatus }) {
    const t = useT();
    return <Badge tone={tone[status]}>{t(`status.${status}`)}</Badge>;
}
