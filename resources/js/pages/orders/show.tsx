// Order detail: progress, items and totals, next-step actions, courier and tracking, internal notes
// and tags, customer card and the full status history. FR-ORD-01, FR-ORD-02, FR-ORD-07, FR-ORD-11
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, CircleX, ExternalLink, MapPin, Phone, Mail, Truck, Undo2, User } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ChannelIcon, Initials, joinAddress, OrderStatusBadge, TagInput, type Channel, type CustomerCard, type OrderStatus, type PaymentMethod } from '@/components/commerce';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input, Textarea } from '@/components/ui/input';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { formatDate, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { useCan } from '@/lib/permissions';
import type { SharedProps } from '@/types';

interface Props {
    order: {
        id: number;
        number: number;
        status: OrderStatus;
        channel: Channel;
        payment_method: PaymentMethod;
        customer_name: string;
        phone: string | null;
        email: string | null;
        governorate: string | null;
        city: string | null;
        address: string | null;
        subtotal: number;
        shipping_fee: number;
        discount: number;
        total: number;
        courier_name: string | null;
        tracking_url: string | null;
        notes: string | null;
        tags: string[];
        paid: boolean;
        placed_at: string;
        next: OrderStatus[];
        items: { id: number; product_id: number | null; name: string; variant: string | null; unit_price: number; quantity: number; line_total: number }[];
        history: { id: number; from: OrderStatus | null; to: OrderStatus; actor_type: string; actor_name: string | null; note: string | null; at: string }[];
        customer: CustomerCard | null;
    };
}

const flow: OrderStatus[] = ['pending', 'processing', 'shipped', 'delivered'];

/** Where the order is on the happy path: pending → processing → shipped → delivered. */
function Progress({ status, history }: { status: OrderStatus; history: Props['order']['history'] }) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const closed = ['cancelled', 'refunded', 'returned'].includes(status);
    const reached = (s: OrderStatus) => history.some((h) => h.to === s) || (s === 'processing' && history.some((h) => h.to === 'paid' || h.to === 'shipped'));
    const current = status === 'paid' ? 'processing' : status;

    return (
        <ol className="grid grid-cols-4 gap-2" aria-label={t('orders.progress')}>
            {flow.map((step, i) => {
                const ci = flow.indexOf(current);
                const done = closed ? reached(step) : i < ci || (step === 'delivered' && current === 'delivered');
                const active = !closed && i === ci && current !== 'delivered';
                const at = history.find((h) => h.to === step)?.at;
                return (
                    <li key={step} className="relative flex flex-col items-center text-center">
                        {i > 0 ? (
                            <span className="absolute end-1/2 top-4 -z-10 h-0.5 w-full -translate-y-1/2 rounded-full bg-line" aria-hidden>
                                <span className={cn('block h-full rounded-full bg-brand-accent transition-all duration-700', (closed ? reached(step) : i <= flow.indexOf(current)) ? 'w-full' : 'w-0')} />
                            </span>
                        ) : null}
                        <span
                            className={cn(
                                'grid h-8 w-8 place-items-center rounded-full border-2 text-xs transition-colors',
                                done && 'border-brand-accent bg-brand-accent text-white',
                                active && 'border-brand-accent bg-elevated text-ink-accent shadow-[0_0_0_6px_var(--mv-bg-accent-subtle)]',
                                !done && !active && 'border-line-control bg-elevated text-ink-muted',
                            )}
                        >
                            {done ? <Check className="h-4 w-4" aria-hidden strokeWidth={2.5} /> : i + 1}
                        </span>
                        <span className={cn('mt-2 text-caption', active ? 'text-ink' : 'text-ink-muted')}>
                            {t(`orders.status.${step}`)}
                            {active ? <span className="sr-only"> ({t('orders.current')})</span> : null}
                        </span>
                        {at ? <span className="text-[0.6875rem] text-ink-muted">{formatDate(at, locale)}</span> : null}
                    </li>
                );
            })}
        </ol>
    );
}

const actionIcon: Partial<Record<OrderStatus, typeof Check>> = { shipped: Truck, cancelled: CircleX, returned: Undo2, refunded: Undo2 };

function NextSteps({ order }: { order: Props['order'] }) {
    const t = useT();
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState<OrderStatus | null>(null);
    const { errors } = usePage<SharedProps>().props;

    const move = (status: OrderStatus) => {
        if (['cancelled', 'returned', 'refunded'].includes(status) && !window.confirm(t(`orders.confirm_${status}`, { number: order.number }))) return;
        setBusy(status);
        router.put(`/orders/${order.id}/status`, { status, note: note || null }, { preserveScroll: true, onSuccess: () => setNote(''), onFinish: () => setBusy(null) });
    };

    if (order.next.length === 0) {
        return (
            <Card>
                <CardBody>
                    <p className="text-sm text-ink-muted">{t('orders.closed_note', { status: t(`orders.status.${order.status}`) })}</p>
                </CardBody>
            </Card>
        );
    }

    const primary = order.next.filter((s) => !['cancelled', 'returned', 'refunded'].includes(s));
    const secondary = order.next.filter((s) => ['cancelled', 'returned', 'refunded'].includes(s));

    return (
        <Card>
            <CardHeader title={t('orders.next_title')} description={t(`orders.next_hint_${order.status}`)} />
            <CardBody className="space-y-3 pt-2">
                {primary.map((s, i) => {
                    const Icon = actionIcon[s] ?? Check;
                    return (
                        <Button key={s} variant={i === 0 ? 'primary' : 'secondary'} className="w-full" disabled={busy !== null} onClick={() => move(s)}>
                            <Icon className="h-4 w-4" aria-hidden />
                            {t(`orders.mark_${s}`)}
                        </Button>
                    );
                })}
                <Field label={t('orders.note_label')} htmlFor="status-note">
                    <Input id="status-note" value={note} onChange={(e) => setNote(e.target.value)} maxLength={500} placeholder={t('orders.note_placeholder')} />
                </Field>
                {errors.status ? (
                    <p role="alert" className="text-sm text-danger">
                        {errors.status}
                    </p>
                ) : null}
                {secondary.length > 0 ? (
                    <div className="flex flex-wrap gap-2 border-t border-line pt-3">
                        {secondary.map((s) => {
                            const Icon = actionIcon[s] ?? CircleX;
                            return (
                                <Button key={s} size="sm" variant="danger" disabled={busy !== null} onClick={() => move(s)}>
                                    <Icon className="h-4 w-4" aria-hidden />
                                    {t(`orders.mark_${s}`)}
                                </Button>
                            );
                        })}
                    </div>
                ) : null}
            </CardBody>
        </Card>
    );
}

function Courier({ order, manage }: { order: Props['order']; manage: boolean }) {
    const t = useT();
    const form = useForm({ courier_name: order.courier_name ?? '', tracking_url: order.tracking_url ?? '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/orders/${order.id}/fulfilment`, { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader title={t('orders.courier_title')} description={t('orders.courier_hint')} />
            <CardBody className="pt-2">
                {manage ? (
                    <form onSubmit={submit} className="space-y-3">
                        <Field label={t('orders.courier_name')} htmlFor="courier_name" error={form.errors.courier_name}>
                            <Input id="courier_name" value={form.data.courier_name} onChange={(e) => form.setData('courier_name', e.target.value)} maxLength={80} placeholder="Bosta, Aramex, J&T…" list="couriers" />
                            <datalist id="couriers">
                                {['Bosta', 'Aramex', 'J&T Express', 'Mylerz', 'R2S', 'Egypt Post'].map((c) => (
                                    <option key={c} value={c} />
                                ))}
                            </datalist>
                        </Field>
                        <Field label={t('orders.tracking_url')} htmlFor="tracking_url" error={form.errors.tracking_url}>
                            <Input id="tracking_url" dir="ltr" type="url" value={form.data.tracking_url} onChange={(e) => form.setData('tracking_url', e.target.value)} placeholder="https://" />
                        </Field>
                        <Button type="submit" variant="secondary" disabled={form.processing || !form.isDirty}>
                            {t('common.save')}
                        </Button>
                    </form>
                ) : (
                    <p className="text-sm">{order.courier_name ?? t('orders.no_courier')}</p>
                )}
                {order.tracking_url ? (
                    <a href={order.tracking_url} target="_blank" rel="noopener noreferrer" className="mt-3 inline-flex items-center gap-1.5 text-sm text-ink-link">
                        <ExternalLink className="h-4 w-4" aria-hidden />
                        {t('orders.open_tracking')}
                    </a>
                ) : null}
            </CardBody>
        </Card>
    );
}

function Notes({ order, manage }: { order: Props['order']; manage: boolean }) {
    const t = useT();
    const form = useForm({ notes: order.notes ?? '', tags: order.tags });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/orders/${order.id}/notes`, { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader title={t('orders.notes_title')} description={t('orders.notes_hint')} />
            <CardBody className="pt-2">
                {manage ? (
                    <form onSubmit={submit} className="space-y-3">
                        <Field label={t('orders.tags')} htmlFor="tags" hint={t('orders.tags_hint')} error={form.errors.tags}>
                            <TagInput id="tags" value={form.data.tags} onChange={(tags) => form.setData('tags', tags)} placeholder={t('orders.tags_placeholder')} />
                        </Field>
                        <Field label={t('orders.notes')} htmlFor="notes" error={form.errors.notes}>
                            <Textarea id="notes" rows={3} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} maxLength={2000} />
                        </Field>
                        <Button type="submit" variant="secondary" disabled={form.processing || !form.isDirty}>
                            {t('common.save')}
                        </Button>
                    </form>
                ) : (
                    <p className="whitespace-pre-line text-sm">{order.notes ?? '—'}</p>
                )}
            </CardBody>
        </Card>
    );
}

export default function OrderShow({ order }: Props) {
    const t = useT();
    const can = useCan();
    const { locale } = usePage<SharedProps>().props;
    const manage = can('orders.manage');
    const money = (p: number) => formatPounds(p, locale);

    return (
        <DashboardLayout title={t('orders.order_number', { number: order.number })}>
            <Link href="/orders" className="mb-3 inline-flex items-center gap-1.5 text-sm text-ink-muted hover:text-ink">
                <ArrowLeft className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                {t('orders.back')}
            </Link>
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="text-greeting flex flex-wrap items-center gap-3">
                        <span className="tabular">{t('orders.order_number', { number: order.number })}</span>
                        <OrderStatusBadge status={order.status} />
                    </h1>
                    <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-muted">
                        <span>{formatDate(order.placed_at, locale, true)}</span>
                        <ChannelIcon channel={order.channel} withLabel />
                        <span>
                            {t(`orders.payment.${order.payment_method}`)} · {order.paid ? t('orders.paid') : t('orders.unpaid')}
                        </span>
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="mv-stagger space-y-6">
                    <Card className="p-6">
                        <Progress status={order.status} history={order.history} />
                        {['cancelled', 'refunded', 'returned'].includes(order.status) ? (
                            <p className="mt-5 rounded-full bg-secondary px-4 py-2 text-center text-sm text-ink-muted">{t(`orders.closed_${order.status}`)}</p>
                        ) : null}
                    </Card>

                    <Card>
                        <CardHeader title={t('orders.items_title', { count: order.items.reduce((n, i) => n + i.quantity, 0) })} />
                        <ul className="px-6">
                            {order.items.map((item) => (
                                <li key={item.id} className="flex items-center gap-4 border-b border-line py-3 last:border-b-0">
                                    <span className="tabular grid h-9 min-w-9 place-items-center rounded-full bg-accent-subtle px-2 text-caption text-ink-accent">×{item.quantity}</span>
                                    <div className="min-w-0 flex-1">
                                        {item.product_id && can('products.manage') ? (
                                            <Link href={`/products/${item.product_id}/edit`} className="block truncate text-sm font-medium hover:underline">
                                                {item.name}
                                            </Link>
                                        ) : (
                                            <p className="truncate text-sm font-medium">{item.name}</p>
                                        )}
                                        <p className="tabular text-caption text-ink-muted">
                                            {money(item.unit_price)}
                                            {item.variant ? ` · ${item.variant}` : ''}
                                        </p>
                                    </div>
                                    <span className="tabular text-sm">{money(item.line_total)}</span>
                                </li>
                            ))}
                        </ul>
                        <dl className="space-y-2 border-t border-line px-6 py-5 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-ink-muted">{t('orders.subtotal')}</dt>
                                <dd className="tabular">{money(order.subtotal)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-ink-muted">{t('orders.shipping')}</dt>
                                <dd className="tabular">{order.shipping_fee ? money(order.shipping_fee) : t('orders.free')}</dd>
                            </div>
                            {order.discount > 0 ? (
                                <div className="flex justify-between">
                                    <dt className="text-ink-muted">{t('orders.discount')}</dt>
                                    <dd className="tabular text-success">-{money(order.discount)}</dd>
                                </div>
                            ) : null}
                            <div className="flex justify-between border-t border-line pt-3 text-base font-semibold">
                                <dt>{t('orders.total')}</dt>
                                <dd className="tabular">{money(order.total)}</dd>
                            </div>
                        </dl>
                    </Card>

                    <Notes order={order} manage={manage} />

                    <Card>
                        <CardHeader title={t('orders.history_title')} />
                        <CardBody className="pt-2">
                            <ol className="relative space-y-5 border-s-2 border-line ps-6">
                                {[...order.history].reverse().map((h, i) => (
                                    <li key={h.id} className="relative">
                                        <span
                                            className={cn('absolute -start-[31px] top-1 h-3 w-3 rounded-full border-2 border-elevated', i === 0 ? 'bg-brand-accent' : 'bg-line-strong')}
                                            aria-hidden
                                        />
                                        <p className="text-sm">
                                            {h.from ? t('orders.history_moved', { status: t(`orders.status.${h.to}`) }) : t('orders.history_created', { status: t(`orders.status.${h.to}`) })}
                                        </p>
                                        <p className="text-caption text-ink-muted">
                                            {formatDate(h.at, locale, true)} · {h.actor_name ?? t(`orders.actor_${h.actor_type}`)}
                                        </p>
                                        {h.note ? <p className="mt-1 rounded-lg bg-secondary px-3 py-2 text-sm">{h.note}</p> : null}
                                    </li>
                                ))}
                            </ol>
                        </CardBody>
                    </Card>
                </div>

                <aside className="mv-stagger space-y-6">
                    {manage ? <NextSteps order={order} /> : null}

                    <Card>
                        <CardHeader title={t('orders.customer_title')} />
                        <CardBody className="space-y-3 pt-2">
                            <div className="flex items-center gap-3">
                                <Initials name={order.customer_name} />
                                <div className="min-w-0">
                                    {order.customer ? (
                                        <Link href={`/customers/${order.customer.id}`} className="block truncate text-sm font-semibold hover:underline">
                                            {order.customer_name}
                                        </Link>
                                    ) : (
                                        <p className="truncate text-sm font-semibold">{order.customer_name || t('orders.no_name')}</p>
                                    )}
                                    {order.customer ? (
                                        <p className="text-caption text-ink-muted">
                                            {order.customer.orders_count > 1 ? t('customers.returning', { count: order.customer.orders_count }) : t('customers.first_order')}
                                        </p>
                                    ) : null}
                                </div>
                            </div>
                            {order.phone ? (
                                <a href={`tel:${order.phone}`} className="flex items-center gap-2 text-sm text-ink-link" dir="ltr">
                                    <Phone className="h-4 w-4 shrink-0" aria-hidden />
                                    {order.phone}
                                </a>
                            ) : null}
                            {order.email ? (
                                <a href={`mailto:${order.email}`} className="flex items-center gap-2 truncate text-sm text-ink-link">
                                    <Mail className="h-4 w-4 shrink-0" aria-hidden />
                                    {order.email}
                                </a>
                            ) : null}
                            {order.address || order.governorate ? (
                                <p className="flex items-start gap-2 text-sm">
                                    <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
                                    <span>{joinAddress([order.address, order.city, order.governorate ? t(`gov.${order.governorate}`) : null], locale)}</span>
                                </p>
                            ) : null}
                            {!order.phone && !order.email && !order.address ? (
                                <p className="flex items-center gap-2 text-sm text-ink-muted">
                                    <User className="h-4 w-4" aria-hidden />
                                    {t('orders.no_contact')}
                                </p>
                            ) : null}
                        </CardBody>
                    </Card>

                    <Courier order={order} manage={manage} />
                </aside>
            </div>
        </DashboardLayout>
    );
}
