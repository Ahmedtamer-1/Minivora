// Manual order for a sale taken on Instagram, WhatsApp, by phone or in person. FR-ORD-04
// Product picker, returning-customer lookup by phone, shipping fee filled from the governorate,
// and a live total.
import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ImageIcon, Minus, PackagePlus, Plus, Search, Sparkles, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { ChannelIcon, TagInput, type Channel, type CustomerCard, type PaymentMethod } from '@/components/commerce';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input, Select, Textarea } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';
import { formatPounds, piastersToInput } from '@/lib/money';
import type { SharedProps } from '@/types';

interface PickableProduct {
    id: number;
    name: string;
    name_ar: string | null;
    price: number;
    cost: number | null;
    stock: number;
    cover: string | null;
}

interface Props {
    products: PickableProduct[];
    rates: Record<string, { rate: number; days: number }>;
    governorates: string[];
    channels: Channel[];
    paymentMethods: PaymentMethod[];
    prefill: { phone?: string; customer_name?: string };
}

interface Line {
    key: string;
    product_id: number | null;
    name: string;
    unit_price: string; // pounds, as typed
    quantity: number;
}

/** "1,250.5" → 125050 piasters; anything unreadable → 0. */
function toPiasters(pounds: string): number {
    const clean = pounds.replace(/[,\s]/g, '');
    if (!/^\d+(\.\d{0,2})?$/.test(clean)) return 0;
    const [whole, frac = ''] = clean.split('.');
    return Number(whole) * 100 + Number((frac + '00').slice(0, 2));
}

let lineSeq = 0;

export default function OrderCreate({ products, rates, governorates, channels, paymentMethods, prefill }: Props) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const money = (p: number) => formatPounds(p, locale);

    const form = useForm({
        channel: 'instagram' as Channel,
        payment_method: 'cod' as PaymentMethod,
        status: 'pending' as 'pending' | 'paid' | 'processing',
        customer_name: prefill.customer_name ?? '',
        phone: prefill.phone ?? '',
        email: '',
        governorate: '',
        city: '',
        address: '',
        shipping_fee: '',
        discount: '',
        notes: '',
        tags: [] as string[],
        items: [] as Line[],
    });
    const { data, setData, errors } = form;

    // Shipping follows the governorate until someone types their own fee.
    const [feeTouched, setFeeTouched] = useState(false);
    useEffect(() => {
        if (!feeTouched) setData('shipping_fee', data.governorate ? piastersToInput(rates[data.governorate]?.rate ?? 0) : '');
    }, [data.governorate]); // eslint-disable-line react-hooks/exhaustive-deps

    // Returning customer lookup by phone.
    const [returning, setReturning] = useState<CustomerCard | null>(null);
    const lookupFor = useRef('');
    useEffect(() => {
        const digits = data.phone.replace(/\D/g, '');
        if (digits.length < 8) {
            setReturning(null);
            return;
        }
        const timer = setTimeout(() => {
            lookupFor.current = data.phone;
            fetch(`/customers/lookup?phone=${encodeURIComponent(data.phone)}`, { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : { customer: null }))
                .then((res: { customer: CustomerCard | null }) => {
                    if (lookupFor.current !== data.phone) return;
                    setReturning(res.customer);
                })
                .catch(() => setReturning(null));
        }, 350);
        return () => clearTimeout(timer);
    }, [data.phone]);

    const useReturning = () => {
        if (!returning) return;
        form.setData((d) => ({
            ...d,
            customer_name: returning.name || d.customer_name,
            email: returning.email ?? d.email,
            governorate: returning.governorate ?? d.governorate,
            city: returning.city ?? d.city,
            address: returning.address ?? d.address,
        }));
    };

    // Product picker
    const [pick, setPick] = useState('');
    const matches = useMemo(() => {
        const q = pick.trim().toLowerCase();
        const list = q ? products.filter((p) => p.name.toLowerCase().includes(q) || (p.name_ar ?? '').includes(q)) : products;
        return list.slice(0, 8);
    }, [pick, products]);

    const addProduct = (p: PickableProduct) => {
        const existing = data.items.find((l) => l.product_id === p.id);
        if (existing) {
            setData('items', data.items.map((l) => (l === existing ? { ...l, quantity: l.quantity + 1 } : l)));
        } else {
            setData('items', [...data.items, { key: `l${++lineSeq}`, product_id: p.id, name: p.name, unit_price: piastersToInput(p.price), quantity: 1 }]);
        }
    };
    const addCustom = () => setData('items', [...data.items, { key: `l${++lineSeq}`, product_id: null, name: '', unit_price: '', quantity: 1 }]);
    const updateLine = (key: string, patch: Partial<Line>) => setData('items', data.items.map((l) => (l.key === key ? { ...l, ...patch } : l)));
    const removeLine = (key: string) => setData('items', data.items.filter((l) => l.key !== key));

    const byId = useMemo(() => new Map(products.map((p) => [p.id, p])), [products]);
    const subtotal = data.items.reduce((sum, l) => sum + toPiasters(l.unit_price) * l.quantity, 0);
    const shipping = toPiasters(data.shipping_fee);
    const discount = Math.min(subtotal + shipping, toPiasters(data.discount));
    const total = subtotal + shipping - discount;
    const units = data.items.reduce((n, l) => n + l.quantity, 0);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            phone: d.phone || null,
            email: d.email || null,
            governorate: d.governorate || null,
            items: d.items.map((l) => ({ product_id: l.product_id, name: l.product_id ? null : l.name, unit_price: l.unit_price, quantity: l.quantity })),
        }));
        form.post('/orders', { preserveScroll: true });
    };

    const lineError = (i: number, field: string) => errors[`items.${i}.${field}` as keyof typeof errors];

    return (
        <DashboardLayout title={t('orders.new')}>
            <Link href="/orders" className="mb-3 inline-flex items-center gap-1.5 text-sm text-ink-muted hover:text-ink">
                <ArrowLeft className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                {t('orders.back')}
            </Link>
            <PageHeader title={t('orders.new_title')} description={t('orders.new_subtitle')} />

            <form onSubmit={submit} className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="mv-stagger space-y-6">
                    <Card>
                        <CardHeader title={t('orders.where_title')} />
                        <CardBody className="pt-2">
                            <div role="radiogroup" aria-label={t('orders.channel_label')} className="flex flex-wrap gap-2">
                                {channels.map((c) => {
                                    const on = data.channel === c;
                                    return (
                                        <button
                                            key={c}
                                            type="button"
                                            role="radio"
                                            aria-checked={on}
                                            onClick={() => setData('channel', c)}
                                            className={cn(
                                                'inline-flex h-10 items-center gap-2 rounded-full border px-4 text-sm transition-all',
                                                on ? 'border-transparent bg-primary text-primary-fg shadow-md [&_svg]:text-primary-fg' : 'border-line-control bg-elevated hover:border-line-focus',
                                            )}
                                        >
                                            <ChannelIcon channel={c} className={on ? 'text-primary-fg' : undefined} />
                                            {t(`orders.channel.${c}`)}
                                        </button>
                                    );
                                })}
                            </div>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title={t('orders.items_heading')} description={t('orders.items_hint')} />
                        <CardBody className="space-y-4 pt-2">
                            <div className="relative">
                                <label className="flex h-11 items-center gap-2 rounded-full border border-line-control bg-elevated px-4 focus-within:border-line-focus focus-within:ring-1 focus-within:ring-line-focus">
                                    <Search className="h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
                                    <span className="sr-only">{t('orders.find_product')}</span>
                                    <input
                                        type="search"
                                        value={pick}
                                        onChange={(e) => setPick(e.target.value)}
                                        placeholder={t('orders.find_product')}
                                        className="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-sm placeholder:text-ink-muted focus:ring-0"
                                    />
                                </label>
                            </div>
                            {products.length === 0 ? (
                                <p className="text-sm text-ink-muted">{t('orders.no_products')}</p>
                            ) : (
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    {matches.map((p) => {
                                        const inOrder = data.items.find((l) => l.product_id === p.id)?.quantity ?? 0;
                                        const out = p.stock - inOrder <= 0;
                                        return (
                                            <button
                                                key={p.id}
                                                type="button"
                                                disabled={out}
                                                onClick={() => addProduct(p)}
                                                className="group relative flex flex-col overflow-hidden rounded-2xl border border-line bg-elevated text-start transition-all hover:-translate-y-0.5 hover:border-line-focus hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0"
                                            >
                                                <span className="relative block aspect-[4/3] bg-accent-subtle">
                                                    {p.cover ? (
                                                        <img src={p.cover} alt="" className="h-full w-full object-cover" loading="lazy" />
                                                    ) : (
                                                        <span className="grid h-full place-items-center">
                                                            <ImageIcon className="h-5 w-5 text-ink-muted" aria-hidden strokeWidth={1.5} />
                                                        </span>
                                                    )}
                                                    {inOrder > 0 ? <Badge tone="highlight" className="absolute end-2 top-2">×{inOrder}</Badge> : null}
                                                    <span className="absolute inset-0 grid place-items-center bg-black/0 opacity-0 transition-all group-hover:bg-black/20 group-hover:opacity-100 group-disabled:hidden">
                                                        <span className="grid h-9 w-9 place-items-center rounded-full bg-white text-[#0B1A26] shadow-md">
                                                            <Plus className="h-4 w-4" aria-hidden />
                                                        </span>
                                                    </span>
                                                </span>
                                                <span className="block px-3 py-2">
                                                    <span className="block truncate text-sm font-medium">{locale === 'ar' ? p.name_ar || p.name : p.name}</span>
                                                    <span className="flex items-baseline justify-between gap-2">
                                                        <span className="tabular text-caption">{money(p.price)}</span>
                                                        <span className={cn('text-[0.6875rem]', p.stock === 0 ? 'text-danger' : p.stock <= 5 ? 'text-warning' : 'text-ink-muted')}>
                                                            {p.stock === 0 ? t('low.sold_out') : t('low.left', { count: p.stock })}
                                                        </span>
                                                    </span>
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                            <Button variant="ghost" size="sm" onClick={addCustom}>
                                <PackagePlus className="h-4 w-4" aria-hidden />
                                {t('orders.add_custom')}
                            </Button>

                            {data.items.length > 0 ? (
                                <ul className="divide-y divide-line rounded-2xl border border-line">
                                    {data.items.map((l, i) => {
                                        const product = l.product_id ? byId.get(l.product_id) : undefined;
                                        const over = product ? l.quantity > product.stock : false;
                                        return (
                                            <li key={l.key} className="mv-rise flex flex-wrap items-center gap-3 p-3">
                                                <div className="min-w-0 flex-1 basis-48">
                                                    {product ? (
                                                        <p className="truncate text-sm font-medium">{l.name}</p>
                                                    ) : (
                                                        <Input
                                                            aria-label={t('orders.item_name')}
                                                            placeholder={t('orders.item_name')}
                                                            value={l.name}
                                                            onChange={(e) => updateLine(l.key, { name: e.target.value })}
                                                            invalid={!!lineError(i, 'name')}
                                                            maxLength={255}
                                                        />
                                                    )}
                                                    {over ? <p className="mt-1 text-caption text-danger">{t('orders.only_left', { count: product!.stock })}</p> : null}
                                                    {lineError(i, 'name') || lineError(i, 'product_id') ? (
                                                        <p role="alert" className="mt-1 text-caption text-danger">
                                                            {lineError(i, 'name') || lineError(i, 'product_id')}
                                                        </p>
                                                    ) : null}
                                                </div>
                                                <div className="w-28">
                                                    <Input
                                                        aria-label={t('orders.unit_price')}
                                                        dir="ltr"
                                                        inputMode="decimal"
                                                        value={l.unit_price}
                                                        onChange={(e) => updateLine(l.key, { unit_price: e.target.value })}
                                                        invalid={!!lineError(i, 'unit_price')}
                                                        placeholder="0"
                                                    />
                                                </div>
                                                <div className="flex items-center rounded-full border border-line-control bg-elevated">
                                                    <button
                                                        type="button"
                                                        className="grid h-9 w-9 place-items-center rounded-full hover:bg-secondary"
                                                        onClick={() => (l.quantity > 1 ? updateLine(l.key, { quantity: l.quantity - 1 }) : removeLine(l.key))}
                                                        aria-label={t('orders.less')}
                                                    >
                                                        <Minus className="h-4 w-4" aria-hidden />
                                                    </button>
                                                    <span className="tabular w-8 text-center text-sm" aria-live="polite">
                                                        {l.quantity}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        className="grid h-9 w-9 place-items-center rounded-full hover:bg-secondary"
                                                        onClick={() => updateLine(l.key, { quantity: Math.min(999, l.quantity + 1) })}
                                                        aria-label={t('orders.more')}
                                                    >
                                                        <Plus className="h-4 w-4" aria-hidden />
                                                    </button>
                                                </div>
                                                <span className="tabular w-24 text-end text-sm font-semibold">{money(toPiasters(l.unit_price) * l.quantity)}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => removeLine(l.key)}
                                                    className="grid h-9 w-9 place-items-center rounded-full text-ink-muted hover:bg-danger-subtle hover:text-danger"
                                                    aria-label={t('orders.remove_item', { name: l.name || t('orders.item_name') })}
                                                >
                                                    <Trash2 className="h-4 w-4" aria-hidden />
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            ) : null}
                            {errors.items ? (
                                <p role="alert" className="text-sm text-danger">
                                    {errors.items}
                                </p>
                            ) : null}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title={t('orders.customer_title')} />
                        <CardBody className="space-y-4 pt-2">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('orders.phone')} htmlFor="phone" error={errors.phone}>
                                    <Input id="phone" dir="ltr" type="tel" autoComplete="off" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="010 0000 0000" invalid={!!errors.phone} />
                                </Field>
                                <Field label={t('orders.name')} htmlFor="customer_name" error={errors.customer_name}>
                                    <Input id="customer_name" value={data.customer_name} onChange={(e) => setData('customer_name', e.target.value)} required maxLength={255} invalid={!!errors.customer_name} />
                                </Field>
                            </div>
                            {returning ? (
                                <div role="status" className="mv-rise flex flex-wrap items-center gap-3 rounded-2xl bg-accent-subtle px-4 py-3">
                                    <Sparkles className="h-4 w-4 text-ink-accent" aria-hidden />
                                    <p className="min-w-0 flex-1 text-sm">
                                        {t('orders.returning', { name: returning.name, count: returning.orders_count, spent: money(returning.total_spent) })}
                                    </p>
                                    <Button size="sm" variant="secondary" onClick={useReturning}>
                                        {t('orders.use_details')}
                                    </Button>
                                </div>
                            ) : null}
                            <Field label={t('orders.email')} htmlFor="email" error={errors.email} hint={t('orders.email_hint')}>
                                <Input id="email" dir="ltr" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} invalid={!!errors.email} />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('orders.governorate')} htmlFor="governorate" error={errors.governorate}>
                                    <Select id="governorate" value={data.governorate} onChange={(e) => setData('governorate', e.target.value)}>
                                        <option value="">{t('orders.choose_governorate')}</option>
                                        {governorates.map((g) => (
                                            <option key={g} value={g}>
                                                {t(`gov.${g}`)} · {money(rates[g]?.rate ?? 0)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('orders.city')} htmlFor="city" error={errors.city}>
                                    <Input id="city" value={data.city} onChange={(e) => setData('city', e.target.value)} maxLength={80} />
                                </Field>
                            </div>
                            <Field label={t('orders.address')} htmlFor="address" error={errors.address}>
                                <Textarea id="address" rows={2} value={data.address} onChange={(e) => setData('address', e.target.value)} maxLength={500} />
                            </Field>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title={t('orders.notes_title')} description={t('orders.notes_hint')} />
                        <CardBody className="space-y-4 pt-2">
                            <Field label={t('orders.tags')} htmlFor="tags" hint={t('orders.tags_hint')}>
                                <TagInput id="tags" value={data.tags} onChange={(tags) => setData('tags', tags)} placeholder={t('orders.tags_placeholder')} />
                            </Field>
                            <Field label={t('orders.notes')} htmlFor="notes" error={errors.notes}>
                                <Textarea id="notes" rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} maxLength={2000} />
                            </Field>
                        </CardBody>
                    </Card>
                </div>

                <aside className="xl:sticky xl:top-6 xl:self-start">
                    <Card className="mv-rise">
                        <CardHeader title={t('orders.summary')} description={units > 0 ? t('orders.items', { count: units }) : t('orders.summary_empty')} />
                        <CardBody className="space-y-4 pt-2">
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('orders.payment_label')} htmlFor="payment_method">
                                    <Select id="payment_method" value={data.payment_method} onChange={(e) => setData('payment_method', e.target.value as PaymentMethod)}>
                                        {paymentMethods.map((m) => (
                                            <option key={m} value={m}>
                                                {t(`orders.payment.${m}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('orders.start_as')} htmlFor="status">
                                    <Select id="status" value={data.status} onChange={(e) => setData('status', e.target.value as typeof data.status)}>
                                        {(['pending', 'paid', 'processing'] as const).map((s) => (
                                            <option key={s} value={s}>
                                                {t(`orders.status.${s}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('orders.shipping')} htmlFor="shipping_fee" error={errors.shipping_fee} hint={data.governorate && rates[data.governorate] ? t('orders.days', { count: rates[data.governorate].days }) : undefined}>
                                    <Input
                                        id="shipping_fee"
                                        dir="ltr"
                                        inputMode="decimal"
                                        value={data.shipping_fee}
                                        onChange={(e) => {
                                            setFeeTouched(true);
                                            setData('shipping_fee', e.target.value);
                                        }}
                                        placeholder="0"
                                    />
                                </Field>
                                <Field label={t('orders.discount')} htmlFor="discount" error={errors.discount}>
                                    <Input id="discount" dir="ltr" inputMode="decimal" value={data.discount} onChange={(e) => setData('discount', e.target.value)} placeholder="0" />
                                </Field>
                            </div>
                            <dl className="space-y-2 border-t border-line pt-4 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-ink-muted">{t('orders.subtotal')}</dt>
                                    <dd className="tabular">{money(subtotal)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-ink-muted">{t('orders.shipping')}</dt>
                                    <dd className="tabular">{shipping ? money(shipping) : t('orders.free')}</dd>
                                </div>
                                {discount > 0 ? (
                                    <div className="flex justify-between">
                                        <dt className="text-ink-muted">{t('orders.discount')}</dt>
                                        <dd className="tabular text-success">-{money(discount)}</dd>
                                    </div>
                                ) : null}
                                <div className="flex items-baseline justify-between border-t border-line pt-3">
                                    <dt className="font-semibold">{t('orders.total')}</dt>
                                    <dd key={total} className="tabular mv-rise font-display text-numeral-sm">
                                        {money(total)}
                                    </dd>
                                </div>
                            </dl>
                            <Button type="submit" size="lg" className="w-full shadow-md" disabled={form.processing || data.items.length === 0 || !data.customer_name.trim()}>
                                {form.processing ? t('common.saving') : t('orders.save_order')}
                            </Button>
                        </CardBody>
                    </Card>
                </aside>
            </form>
        </DashboardLayout>
    );
}
