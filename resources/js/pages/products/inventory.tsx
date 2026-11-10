// Inventory: stock per variant, days of cover and reorder points. FR-CAT-02, FR-ANA-04, FR-PRO-02 (AI COO). Preview.
import { AlertTriangle, Bell, Boxes, PackageCheck, TrendingDown, Upload } from 'lucide-react';
import { StatTile } from '@/components/commerce';
import { Meter, PlannedFeatures, PlannedNotice, PreviewCard, PreviewTable } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatNumber, useT } from '@/lib/i18n';
import { sampleStock, useBi } from '@/lib/samples';

export default function Inventory() {
    const t = useT();
    const bi = useBi();

    const rows = sampleStock.map((s) => {
        const days = Math.floor(s.stock / s.daily);
        const tone = days <= 7 ? 'danger' : days <= 14 ? 'warning' : 'success';
        return {
            id: s.id,
            name: (
                <span className="block min-w-0">
                    <span className="block truncate font-medium">{bi(s.name)}</span>
                    <span className="block font-mono text-xs text-ink-muted" dir="ltr">
                        {s.sku}
                    </span>
                </span>
            ),
            stock: formatNumber(s.stock),
            cover: (
                <span className="flex items-center justify-end gap-3">
                    <span className="hidden w-24 sm:block">
                        <Meter value={(days / 60) * 100} tone={tone} label={t('inventory.cover')} />
                    </span>
                    <Badge tone={tone}>{t('inventory.days', { count: days })}</Badge>
                </span>
            ),
            reorder: formatNumber(s.reorder),
        };
    });

    return (
        <DashboardLayout title={t('nav.products_inventory')}>
            <PageHeader
                title={t('inventory.title')}
                description={t('inventory.subtitle')}
                action={
                    <Button variant="secondary" disabled>
                        <Upload className="h-4 w-4" aria-hidden />
                        {t('inventory.adjust')}
                    </Button>
                }
            />
            <PlannedNotice requirements={['FR-CAT-02', 'FR-ANA-04', 'FR-PRO-02']} />

            <div className="mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile icon={Boxes} label={t('inventory.stat_units')} value="72" hint={t('inventory.stat_units_hint')} />
                <StatTile icon={AlertTriangle} label={t('inventory.stat_low')} value="2" hint={t('inventory.stat_low_hint')} tone="warning" />
                <StatTile icon={TrendingDown} label={t('inventory.stat_out')} value="0" hint={t('inventory.stat_out_hint')} />
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <PreviewCard title={t('inventory.table')} description={t('inventory.table_hint')}>
                    <PreviewTable
                        caption={t('inventory.table')}
                        columns={[
                            { key: 'name', label: t('inventory.col_product') },
                            { key: 'stock', label: t('inventory.col_stock'), align: 'end' },
                            { key: 'cover', label: t('inventory.col_cover'), align: 'end' },
                            { key: 'reorder', label: t('inventory.col_reorder'), align: 'end' },
                        ]}
                        rows={rows}
                    />
                </PreviewCard>
                <PlannedFeatures
                    items={[
                        { icon: PackageCheck, text: t('inventory.f_variants'), req: 'FR-CAT-02' },
                        { icon: TrendingDown, text: t('inventory.f_cover'), req: 'FR-ANA-04' },
                        { icon: Bell, text: t('inventory.f_alert'), req: 'FR-PRO-02' },
                        { icon: Upload, text: t('inventory.f_adjust') },
                    ]}
                />
            </div>
        </DashboardLayout>
    );
}
