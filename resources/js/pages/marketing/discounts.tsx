// Live Discount codes management: table, Create Discount Code form (POST /marketing/discounts),
// and Delete button (DELETE /marketing/discounts/{id}). FR-PAY-11, FR-AI-08.
import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatDate, formatNumber, useLocale, useT } from '@/lib/i18n';
import { formatPiasters } from '@/lib/money';

type DiscountType = 'percentage' | 'fixed' | 'free_shipping';

interface DiscountCodeRow {
    id: number;
    code: string;
    type: DiscountType;
    value: number;
    min_order_amount: number;
    max_uses: number | null;
    uses_count: number;
    starts_at: string | null;
    expires_at: string | null;
    is_active: boolean;
}

interface DiscountsProps {
    discounts: DiscountCodeRow[];
}

function discountStatus(d: DiscountCodeRow): { key: 'active' | 'scheduled' | 'ended'; tone: Tone } {
    const now = Date.now();
    if (!d.is_active) return { key: 'ended', tone: 'neutral' };
    if (d.expires_at && new Date(d.expires_at).getTime() < now) return { key: 'ended', tone: 'neutral' };
    if (d.max_uses !== null && d.uses_count >= d.max_uses) return { key: 'ended', tone: 'neutral' };
    if (d.starts_at && new Date(d.starts_at).getTime() > now) return { key: 'scheduled', tone: 'info' };
    return { key: 'active', tone: 'success' };
}

function formatOffer(d: DiscountCodeRow, locale: 'ar' | 'en'): string {
    if (d.type === 'percentage') return `${d.value}% off`;
    if (d.type === 'fixed') return `${formatPiasters(d.value, locale)} off`;
    return 'Free shipping';
}

export default function Discounts({ discounts = [] }: DiscountsProps) {
    const t = useT();
    const locale = useLocale();

    const form = useForm({
        code: '',
        type: 'percentage' as DiscountType,
        value: '10',
        min_order_amount: '',
        max_uses: '',
        expires_at: '',
    });

    const handleCreate = (e: FormEvent) => {
        e.preventDefault();

        form.transform((data) => {
            let numericValue = 0;
            if (data.type === 'percentage') {
                numericValue = Math.max(0, Math.round(Number.parseFloat(data.value || '0') || 0));
            } else if (data.type === 'fixed') {
                numericValue = Math.max(0, Math.round((Number.parseFloat(data.value || '0') || 0) * 100));
            }

            const minOrderPiasters = data.min_order_amount.trim() !== ''
                ? Math.max(0, Math.round((Number.parseFloat(data.min_order_amount) || 0) * 100))
                : 0;

            const maxUses = data.max_uses.trim() !== ''
                ? Math.max(1, Number.parseInt(data.max_uses, 10) || 1)
                : null;

            return {
                code: data.code.trim().toUpperCase(),
                type: data.type,
                value: numericValue,
                min_order_amount: minOrderPiasters,
                max_uses: maxUses,
                expires_at: data.expires_at.trim() !== '' ? data.expires_at : null,
            };
        });

        form.post('/marketing/discounts', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
            },
        });
    };

    const handleDelete = (discount: DiscountCodeRow) => {
        router.delete(`/marketing/discounts/${discount.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <DashboardLayout title={t('nav.marketing_discounts')}>
            <PageHeader title={t('discounts.title')} description={t('discounts.subtitle')} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card>
                    <CardHeader title={t('discounts.list')} />
                    <CardBody>
                        {discounts.length === 0 ? (
                            <p className="py-8 text-center text-sm text-ink-muted">
                                No discount codes yet. Create your first code using the form on the right.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-start text-sm">
                                    <caption className="sr-only">{t('discounts.list')}</caption>
                                    <thead className="border-b border-line text-caption text-ink-muted">
                                        <tr>
                                            <th scope="col" className="pb-3 pe-3 text-start font-medium">
                                                {t('discounts.col_code')}
                                            </th>
                                            <th scope="col" className="pb-3 px-3 text-start font-medium">
                                                {t('discounts.col_type')}
                                            </th>
                                            <th scope="col" className="pb-3 px-3 text-end font-medium">
                                                Min order
                                            </th>
                                            <th scope="col" className="pb-3 px-3 text-end font-medium">
                                                {t('discounts.col_used')}
                                            </th>
                                            <th scope="col" className="pb-3 px-3 text-end font-medium">
                                                {t('discounts.col_status')}
                                            </th>
                                            <th scope="col" className="pb-3 ps-3 text-end font-medium">
                                                <span className="sr-only">Delete</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {discounts.map((d) => {
                                            const status = discountStatus(d);
                                            return (
                                                <tr key={d.id}>
                                                    <td className="py-3 pe-3">
                                                        <span dir="ltr" className="block font-mono text-sm font-semibold text-ink">
                                                            {d.code}
                                                        </span>
                                                        {d.expires_at ? (
                                                            <span className="block text-caption text-ink-muted">
                                                                Expires {formatDate(d.expires_at, locale)}
                                                            </span>
                                                        ) : null}
                                                    </td>
                                                    <td className="py-3 px-3 text-ink">{formatOffer(d, locale)}</td>
                                                    <td className="tabular py-3 px-3 text-end text-ink-muted">
                                                        {d.min_order_amount > 0 ? formatPiasters(d.min_order_amount, locale) : '—'}
                                                    </td>
                                                    <td className="tabular py-3 px-3 text-end text-ink">
                                                        {formatNumber(d.uses_count)}
                                                        {d.max_uses !== null ? ` / ${formatNumber(d.max_uses)}` : ''}
                                                    </td>
                                                    <td className="py-3 px-3 text-end">
                                                        <Badge tone={status.tone}>{t(`discounts.status_${status.key}`)}</Badge>
                                                    </td>
                                                    <td className="py-3 ps-3 text-end">
                                                        <Button
                                                            variant="danger"
                                                            size="sm"
                                                            onClick={() => handleDelete(d)}
                                                            aria-label={`Delete ${d.code}`}
                                                        >
                                                            <Trash2 className="h-4 w-4" aria-hidden />
                                                        </Button>
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

                <aside>
                    <Card>
                        <CardHeader title={t('discounts.add')} description="Create a percentage, fixed EGP, or free shipping code." />
                        <CardBody>
                            <form onSubmit={handleCreate} className="space-y-4">
                                <Field label="Code" htmlFor="discount_code" error={form.errors.code}>
                                    <Input
                                        id="discount_code"
                                        dir="ltr"
                                        required
                                        placeholder="e.g. WELCOME10"
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                                    />
                                </Field>

                                <Field label="Type" htmlFor="discount_type" error={form.errors.type}>
                                    <Select
                                        id="discount_type"
                                        value={form.data.type}
                                        onChange={(e) => form.setData('type', e.target.value as DiscountType)}
                                    >
                                        <option value="percentage">Percentage (%)</option>
                                        <option value="fixed">Fixed amount (EGP)</option>
                                        <option value="free_shipping">Free shipping</option>
                                    </Select>
                                </Field>

                                {form.data.type !== 'free_shipping' ? (
                                    <Field
                                        label={form.data.type === 'percentage' ? 'Discount (%)' : 'Discount amount (EGP)'}
                                        htmlFor="discount_value"
                                        error={form.errors.value}
                                    >
                                        <Input
                                            id="discount_value"
                                            type="number"
                                            step={form.data.type === 'percentage' ? '1' : '0.01'}
                                            min="1"
                                            max={form.data.type === 'percentage' ? '100' : undefined}
                                            required
                                            value={form.data.value}
                                            onChange={(e) => form.setData('value', e.target.value)}
                                        />
                                    </Field>
                                ) : null}

                                <Field
                                    label="Minimum order (EGP)"
                                    htmlFor="discount_min_order"
                                    hint="Optional. Leave blank for no minimum."
                                    error={form.errors.min_order_amount}
                                >
                                    <Input
                                        id="discount_min_order"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="e.g. 500"
                                        value={form.data.min_order_amount}
                                        onChange={(e) => form.setData('min_order_amount', e.target.value)}
                                    />
                                </Field>

                                <Field label="Max uses" htmlFor="discount_max_uses" error={form.errors.max_uses}>
                                    <Input
                                        id="discount_max_uses"
                                        type="number"
                                        min="1"
                                        placeholder="Unlimited"
                                        value={form.data.max_uses}
                                        onChange={(e) => form.setData('max_uses', e.target.value)}
                                    />
                                </Field>

                                <Field label="Expires at" htmlFor="discount_expires_at" error={form.errors.expires_at}>
                                    <Input
                                        id="discount_expires_at"
                                        type="date"
                                        value={form.data.expires_at}
                                        onChange={(e) => form.setData('expires_at', e.target.value)}
                                    />
                                </Field>

                                <Button type="submit" disabled={form.processing} className="w-full">
                                    <Plus className="h-4 w-4" aria-hidden />
                                    {form.processing ? 'Creating...' : t('discounts.add')}
                                </Button>
                            </form>
                        </CardBody>
                    </Card>
                </aside>
            </div>
        </DashboardLayout>
    );
}
