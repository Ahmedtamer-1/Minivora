// Shipping zones per governorate: rate, delivery days, COD, free-shipping threshold. FR-ORD-06, FR-PAY-03. Preview.
import { Plus } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { PlannedNotice, PreviewCard, PreviewTable } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SettingsLayout } from '@/layouts/settings-layout';
import { useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { sampleShippingZones, useBi } from '@/lib/samples';
import type { SharedProps } from '@/types';

export default function ShippingSettings() {
    const t = useT();
    const bi = useBi();
    const { locale } = usePage<SharedProps>().props;
    return (
        <SettingsLayout
            title={t('shipping.title')}
            description={t('shipping.subtitle')}
            action={
                <Button disabled>
                    <Plus className="h-4 w-4" aria-hidden />
                    {t('shipping.add')}
                </Button>
            }
        >
            <PlannedNotice requirements={['FR-ORD-06', 'FR-PAY-03', 'FR-PAY-05']} />
            <PreviewCard title={t('shipping.zones')} description={t('shipping.free_over', { amount: formatPounds(150000, locale) })}>
                <PreviewTable
                    caption={t('shipping.zones')}
                    columns={[
                        { key: 'name', label: t('shipping.col_zone') },
                        { key: 'days', label: t('shipping.col_days') },
                        { key: 'fee', label: t('shipping.col_fee'), align: 'end' },
                        { key: 'cod', label: t('shipping.col_cod'), align: 'end' },
                    ]}
                    rows={sampleShippingZones.map((z) => ({
                        id: z.id,
                        name: <span className="font-medium">{bi(z.name)}</span>,
                        days: <span className="text-ink-muted">{bi(z.days)}</span>,
                        fee: formatPounds(z.fee, locale),
                        cod: <Badge tone={z.cod ? 'success' : 'neutral'}>{z.cod ? t('shipping.cod_yes') : t('shipping.cod_no')}</Badge>,
                    }))}
                />
            </PreviewCard>
        </SettingsLayout>
    );
}
