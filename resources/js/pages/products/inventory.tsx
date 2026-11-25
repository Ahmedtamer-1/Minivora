// Inventory: live stock table with low-stock badges and quick stock adjustments. FR-CAT-02, FR-CAT-17
import { router } from '@inertiajs/react';
import { AlertTriangle, Boxes, Minus, Plus, TrendingDown } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatTile } from '@/components/commerce';
import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatNumber, useLocale, useT } from '@/lib/i18n';
import { formatPiasters } from '@/lib/money';

interface InventoryProduct {
    id: number;
    name: string;
    name_ar: string | null;
    sku: string | null;
    stock: number;
    reorder_threshold: number;
    status: string;
    price: number;
}

interface Props {
    products?: InventoryProduct[];
}

export default function Inventory({ products = [] }: Props) {
    const t = useT();
    const locale = useLocale();
    const isAr = locale === 'ar';
    const [busyId, setBusyId] = useState<number | null>(null);
    const [deltas, setDeltas] = useState<Record<number, string>>({});

    const totalUnits = products.reduce((sum, p) => sum + p.stock, 0);
    const lowCount = products.filter((p) => p.stock > 0 && p.stock <= p.reorder_threshold).length;
    const outCount = products.filter((p) => p.stock === 0).length;

    const adjust = (productId: number, delta: number) => {
        if (!Number.isInteger(delta) || delta === 0) return;
        setBusyId(productId);
        router.post(
            `/inventory/${productId}/adjust`,
            { delta },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusyId(null);
                    setDeltas((prev) => ({ ...prev, [productId]: '' }));
                },
            },
        );
    };

    const submitCustom = (e: FormEvent, productId: number) => {
        e.preventDefault();
        const parsed = Number.parseInt(deltas[productId] ?? '', 10);
        if (Number.isNaN(parsed) || parsed === 0) return;
        adjust(productId, parsed);
    };

    const stockBadge = (p: InventoryProduct): { tone: Tone; label: string } => {
        if (p.stock === 0) {
            return { tone: 'danger', label: isAr ? 'نفد المخزون' : 'Out of stock' };
        }
        if (p.stock <= p.reorder_threshold) {
            return { tone: 'warning', label: isAr ? 'مخزون منخفض' : 'Low stock' };
        }
        return { tone: 'success', label: isAr ? 'متوفر' : 'In stock' };
    };

    return (
        <DashboardLayout title={t('nav.products_inventory')}>
            <PageHeader title={t('inventory.title')} description={t('inventory.subtitle')} />

            <div className="mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile icon={Boxes} label={t('inventory.stat_units')} value={formatNumber(totalUnits)} hint={t('inventory.stat_units_hint')} />
                <StatTile icon={AlertTriangle} label={t('inventory.stat_low')} value={formatNumber(lowCount)} hint={t('inventory.stat_low_hint')} tone="warning" />
                <StatTile icon={TrendingDown} label={t('inventory.stat_out')} value={formatNumber(outCount)} hint={t('inventory.stat_out_hint')} tone="danger" />
            </div>

            <Card>
                <CardHeader title={t('inventory.table')} description={t('inventory.table_hint')} />
                <CardBody className="p-0">
                    {products.length === 0 ? (
                        <p className="px-6 py-8 text-center text-sm text-ink-muted">
                            {isAr ? 'لا توجد منتجات في المخزون بعد.' : 'No products in inventory yet.'}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[44rem] text-sm">
                                <caption className="sr-only">{t('inventory.table')}</caption>
                                <thead>
                                    <tr className="border-b border-line text-caption text-ink-muted">
                                        <th scope="col" className="px-6 py-3 text-start font-medium">
                                            {t('inventory.col_product')}
                                        </th>
                                        <th scope="col" className="px-4 py-3 text-end font-medium">
                                            {isAr ? 'السعر' : 'Price'}
                                        </th>
                                        <th scope="col" className="px-4 py-3 text-end font-medium">
                                            {t('inventory.col_stock')}
                                        </th>
                                        <th scope="col" className="px-4 py-3 text-end font-medium">
                                            {t('inventory.col_reorder')}
                                        </th>
                                        <th scope="col" className="px-4 py-3 text-center font-medium">
                                            {isAr ? 'الحالة' : 'Status'}
                                        </th>
                                        <th scope="col" className="px-6 py-3 text-end font-medium">
                                            {t('inventory.adjust')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {products.map((p) => {
                                        const badge = stockBadge(p);
                                        const displayName = isAr ? p.name_ar || p.name : p.name || p.name_ar || '';
                                        const isBusy = busyId === p.id;
                                        return (
                                            <tr key={p.id} className="hover:bg-secondary/40">
                                                <td className="px-6 py-3.5">
                                                    <span className="block truncate font-medium text-ink">{displayName}</span>
                                                    {p.sku ? (
                                                        <span className="block font-mono text-xs text-ink-muted" dir="ltr">
                                                            {p.sku}
                                                        </span>
                                                    ) : null}
                                                </td>
                                                <td className="tabular px-4 py-3.5 text-end text-ink">{formatPiasters(p.price, locale)}</td>
                                                <td className="tabular px-4 py-3.5 text-end font-semibold text-ink">{formatNumber(p.stock)}</td>
                                                <td className="tabular px-4 py-3.5 text-end text-ink-muted">{formatNumber(p.reorder_threshold)}</td>
                                                <td className="px-4 py-3.5 text-center">
                                                    <Badge tone={badge.tone}>{badge.label}</Badge>
                                                </td>
                                                <td className="px-6 py-3.5">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <Button
                                                            size="sm"
                                                            variant="secondary"
                                                            disabled={isBusy || p.stock <= 0}
                                                            onClick={() => adjust(p.id, -1)}
                                                            aria-label={isAr ? `إنقاص 1 من ${displayName}` : `Decrease ${displayName} by 1`}
                                                        >
                                                            <Minus className="h-3.5 w-3.5" aria-hidden />1
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="secondary"
                                                            disabled={isBusy}
                                                            onClick={() => adjust(p.id, 1)}
                                                            aria-label={isAr ? `زيادة 1 إلى ${displayName}` : `Increase ${displayName} by 1`}
                                                        >
                                                            <Plus className="h-3.5 w-3.5" aria-hidden />1
                                                        </Button>
                                                        <form onSubmit={(e) => submitCustom(e, p.id)} className="flex items-center gap-1.5">
                                                            <Input
                                                                type="number"
                                                                dir="ltr"
                                                                value={deltas[p.id] ?? ''}
                                                                onChange={(e) => setDeltas((prev) => ({ ...prev, [p.id]: e.target.value }))}
                                                                placeholder="+10 / -5"
                                                                className="h-8 w-24 text-xs"
                                                                aria-label={isAr ? 'تعديل مخصص' : 'Custom delta'}
                                                            />
                                                            <Button type="submit" size="sm" disabled={isBusy || !deltas[p.id]}>
                                                                {isAr ? 'تطبيق' : 'Apply'}
                                                            </Button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardBody>
            </Card>
        </DashboardLayout>
    );
}
