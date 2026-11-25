// Shipping zones per governorate: live zone table, upsert form and default courier rates. FR-ORD-06
import { router } from '@inertiajs/react';
import { Plus, Trash2, Truck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/input';
import { SettingsLayout } from '@/layouts/settings-layout';
import { formatNumber, useLocale, useT } from '@/lib/i18n';
import { formatPiasters } from '@/lib/money';

interface ShippingZoneRow {
    id: number;
    governorate: string;
    rate: number;
    delivery_days: number;
    sort_order: number;
}

interface Props {
    zones?: ShippingZoneRow[];
    rates?: Record<string, { rate: number; days: number }>;
    governorates?: string[];
}

const DEFAULT_GOVERNORATES = [
    'cairo',
    'giza',
    'alexandria',
    'qalyubia',
    'sharqia',
    'dakahlia',
    'gharbia',
    'monufia',
    'beheira',
    'kafr_el_sheikh',
    'damietta',
    'port_said',
    'ismailia',
    'suez',
    'faiyum',
    'beni_suef',
    'minya',
    'asyut',
    'sohag',
    'qena',
    'luxor',
    'aswan',
    'red_sea',
    'new_valley',
    'matrouh',
    'north_sinai',
    'south_sinai',
];

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function formatGovernorate(slug: string): string {
    return slug
        .split('_')
        .map((part) => (part ? part[0].toUpperCase() + part.slice(1) : ''))
        .join(' ');
}

export default function ShippingSettings({ zones = [], rates = {}, governorates = DEFAULT_GOVERNORATES }: Props) {
    const t = useT();
    const locale = useLocale();
    const isAr = locale === 'ar';

    const [governorate, setGovernorate] = useState<string>(governorates[0] ?? 'cairo');
    const [rateEgp, setRateEgp] = useState<string>('60');
    const [deliveryDays, setDeliveryDays] = useState<string>('2');
    const [saving, setSaving] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [feedback, setFeedback] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);

    const handleSaveZone = async (e: FormEvent) => {
        e.preventDefault();
        const parsedRate = Number.parseFloat(rateEgp);
        const parsedDays = Number.parseInt(deliveryDays, 10);
        if (Number.isNaN(parsedRate) || parsedRate < 0 || Number.isNaN(parsedDays) || parsedDays < 1) return;

        setSaving(true);
        setFeedback(null);
        try {
            const res = await fetch('/settings/shipping/zones', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    governorate,
                    rate: Math.round(parsedRate * 100),
                    delivery_days: parsedDays,
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error((data as { message?: string }).message ?? (isAr ? 'تعذر حفظ منطقة الشحن.' : 'Could not save shipping zone.'));
            }
            setFeedback({ kind: 'success', text: isAr ? 'تم حفظ منطقة الشحن بنجاح.' : 'Shipping zone saved.' });
            router.reload();
        } catch (err) {
            setFeedback({ kind: 'error', text: err instanceof Error ? err.message : 'Error' });
        } finally {
            setSaving(false);
        }
    };

    const handleDeleteZone = async (id: number) => {
        setDeletingId(id);
        setFeedback(null);
        try {
            const res = await fetch(`/settings/shipping/zones/${id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            });
            if (!res.ok) {
                throw new Error(isAr ? 'تعذر حذف المنطقة.' : 'Could not delete zone.');
            }
            setFeedback({ kind: 'success', text: isAr ? 'تم حذف منطقة الشحن.' : 'Shipping zone removed.' });
            router.reload();
        } catch (err) {
            setFeedback({ kind: 'error', text: err instanceof Error ? err.message : 'Error' });
        } finally {
            setDeletingId(null);
        }
    };

    const customByGov = new Set(zones.map((z) => z.governorate));
    const defaultRateEntries = Object.entries(rates).filter(([gov]) => !customByGov.has(gov));

    return (
        <SettingsLayout title={t('shipping.title')} description={t('shipping.subtitle')}>
            {feedback ? (
                <div
                    role="status"
                    className={`rounded-2xl px-4 py-3 text-sm ${
                        feedback.kind === 'success' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'
                    }`}
                >
                    {feedback.text}
                </div>
            ) : null}

            <Card>
                <CardHeader
                    title={isAr ? 'إضافة أو تحديث منطقة شحن' : 'Add or update governorate zone'}
                    description={
                        isAr
                            ? 'تتجاوز مناطق الشحن المخصصة الأسعار الافتراضية للمحافظات.'
                            : 'Custom governorate zones override the default courier rates.'
                    }
                />
                <CardBody>
                    <form onSubmit={handleSaveZone} className="grid gap-4 sm:grid-cols-3">
                        <Field label={isAr ? 'المحافظة' : 'Governorate'} htmlFor="ship_gov">
                            <Select id="ship_gov" value={governorate} onChange={(e) => setGovernorate(e.target.value)}>
                                {governorates.map((gov) => (
                                    <option key={gov} value={gov}>
                                        {formatGovernorate(gov)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={isAr ? 'سعر الشحن (ج.م)' : 'Shipping fee (EGP)'} htmlFor="ship_rate">
                            <Input
                                id="ship_rate"
                                type="number"
                                dir="ltr"
                                min="0"
                                step="0.01"
                                value={rateEgp}
                                onChange={(e) => setRateEgp(e.target.value)}
                                required
                            />
                        </Field>
                        <Field label={isAr ? 'أيام التوصيل' : 'Delivery days'} htmlFor="ship_days">
                            <Input
                                id="ship_days"
                                type="number"
                                dir="ltr"
                                min="1"
                                max="60"
                                value={deliveryDays}
                                onChange={(e) => setDeliveryDays(e.target.value)}
                                required
                            />
                        </Field>
                        <div className="sm:col-span-3">
                            <Button type="submit" disabled={saving}>
                                <Plus className="h-4 w-4" aria-hidden />
                                {saving ? t('common.saving') : isAr ? 'حفظ المنطقة' : 'Save zone'}
                            </Button>
                        </div>
                    </form>
                </CardBody>
            </Card>

            <Card>
                <CardHeader
                    title={t('shipping.zones')}
                    description={isAr ? 'المناطق المخصصة لمتجرك حالياً.' : 'Custom governorate rates configured for your store.'}
                    action={<Badge tone="neutral">{formatNumber(zones.length)}</Badge>}
                />
                <CardBody className="p-0">
                    {zones.length === 0 ? (
                        <p className="px-6 py-6 text-sm text-ink-muted">
                            {isAr
                                ? 'لا توجد مناطق مخصصة بعد — تُستخدم الأسعار الافتراضية أدناه.'
                                : 'No custom zones yet — default Egyptian courier rates below apply.'}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <caption className="sr-only">{t('shipping.zones')}</caption>
                                <thead>
                                    <tr className="border-b border-line text-caption text-ink-muted">
                                        <th scope="col" className="px-6 py-3 text-start font-medium">
                                            {t('shipping.col_zone')}
                                        </th>
                                        <th scope="col" className="px-4 py-3 text-start font-medium">
                                            {t('shipping.col_days')}
                                        </th>
                                        <th scope="col" className="px-4 py-3 text-end font-medium">
                                            {t('shipping.col_fee')}
                                        </th>
                                        <th scope="col" className="px-6 py-3 text-end font-medium">
                                            {isAr ? 'حذف' : 'Remove'}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {zones.map((z) => (
                                        <tr key={z.id} className="hover:bg-secondary/40">
                                            <td className="px-6 py-3 font-medium text-ink">{formatGovernorate(z.governorate)}</td>
                                            <td className="tabular px-4 py-3 text-ink-muted">
                                                {z.delivery_days} {isAr ? 'يوم' : z.delivery_days === 1 ? 'day' : 'days'}
                                            </td>
                                            <td className="tabular px-4 py-3 text-end text-ink">{formatPiasters(z.rate, locale)}</td>
                                            <td className="px-6 py-3 text-end">
                                                <Button
                                                    variant="danger"
                                                    size="icon-sm"
                                                    disabled={deletingId === z.id}
                                                    onClick={() => handleDeleteZone(z.id)}
                                                    aria-label={isAr ? `حذف ${z.governorate}` : `Delete ${z.governorate}`}
                                                >
                                                    <Trash2 className="h-4 w-4" aria-hidden />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardBody>
            </Card>

            {defaultRateEntries.length > 0 ? (
                <Card>
                    <CardHeader
                        title={isAr ? 'الأسعار الافتراضية لباقي المحافظات' : 'Default governorate rates'}
                        description={
                            isAr
                                ? 'تُطبق تلقائياً على المحافظات التي ليس لها منطقة مخصصة.'
                                : 'Applied automatically to governorates without a custom zone.'
                        }
                        action={
                            <Badge tone="info">
                                <Truck className="h-3.5 w-3.5" aria-hidden />
                                {formatNumber(defaultRateEntries.length)}
                            </Badge>
                        }
                    />
                    <CardBody className="p-0">
                        <div className="max-h-72 overflow-y-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-line text-caption text-ink-muted">
                                        <th scope="col" className="px-6 py-2.5 text-start font-medium">
                                            {t('shipping.col_zone')}
                                        </th>
                                        <th scope="col" className="px-4 py-2.5 text-start font-medium">
                                            {t('shipping.col_days')}
                                        </th>
                                        <th scope="col" className="px-6 py-2.5 text-end font-medium">
                                            {t('shipping.col_fee')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {defaultRateEntries.map(([gov, info]) => (
                                        <tr key={gov}>
                                            <td className="px-6 py-2.5 text-ink">{formatGovernorate(gov)}</td>
                                            <td className="tabular px-4 py-2.5 text-ink-muted">
                                                {info.days} {isAr ? 'أيام' : 'days'}
                                            </td>
                                            <td className="tabular px-6 py-2.5 text-end text-ink">{formatPiasters(info.rate, locale)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardBody>
                </Card>
            ) : null}
        </SettingsLayout>
    );
}
