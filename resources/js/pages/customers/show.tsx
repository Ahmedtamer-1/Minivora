// One customer: contact, totals and their orders.
import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Mail, MapPin, Phone, Plus } from 'lucide-react';
import { ChannelIcon, Initials, joinAddress, OrderStatusBadge, StatTile, type CustomerCard, type OrderRow } from '@/components/commerce';
import { ButtonLink } from '@/components/ui/button';
import { Card, CardHeader } from '@/components/ui/card';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import { formatDate, formatNumber, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';
import { useCan } from '@/lib/permissions';
import type { SharedProps } from '@/types';

interface Props {
    customer: CustomerCard & { notes: string | null; since: string };
    orders: OrderRow[];
}

export default function CustomerShow({ customer, orders }: Props) {
    const t = useT();
    const can = useCan();
    const { locale } = usePage<SharedProps>().props;
    const average = customer.orders_count > 0 ? Math.round(customer.total_spent / customer.orders_count) : 0;

    return (
        <DashboardLayout title={customer.name}>
            <Link href="/customers" className="mb-3 inline-flex items-center gap-1.5 text-sm text-ink-muted hover:text-ink">
                <ArrowLeft className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                {t('customers.back')}
            </Link>

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex min-w-0 items-center gap-4">
                    <Initials name={customer.name} className="h-14 w-14 text-lg" />
                    <div className="min-w-0">
                        <h1 className="text-greeting truncate">{customer.name || t('orders.no_name')}</h1>
                        <p className="mt-1 text-sm text-ink-muted">{t('customers.since', { date: formatDate(customer.since, locale) })}</p>
                    </div>
                </div>
                {can('orders.manage') ? (
                    <ButtonLink href={`/orders/create?${new URLSearchParams({ phone: customer.phone ?? '', customer_name: customer.name }).toString()}`} size="lg" className="shadow-md">
                        <Plus className="h-[18px] w-[18px]" aria-hidden />
                        {t('customers.new_order')}
                    </ButtonLink>
                ) : null}
            </div>

            <div className="mv-stagger mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile label={t('customers.orders')} value={formatNumber(customer.orders_count)} />
                <StatTile label={t('customers.spent')} value={formatPounds(customer.total_spent, locale)} />
                <StatTile label={t('customers.average')} value={formatPounds(average, locale)} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <Card className="mv-rise overflow-hidden">
                    <CardHeader title={t('customers.orders_title')} />
                    <ul className="mt-2">
                        {orders.map((o) => (
                            <li key={o.id} className="relative flex items-center gap-4 border-t border-line px-6 py-3 transition-colors hover:bg-secondary">
                                <div className="min-w-0 flex-1">
                                    <Link href={`/orders/${o.id}`} className="text-sm font-semibold after:absolute after:inset-0 after:content-['']">
                                        <span className="tabular">#{o.number}</span>
                                    </Link>
                                    <p className="flex items-center gap-2 text-caption text-ink-muted">
                                        <ChannelIcon channel={o.channel} />
                                        {formatDate(o.placed_at, locale)}
                                    </p>
                                </div>
                                <OrderStatusBadge status={o.status} />
                                <span className="tabular w-24 text-end text-sm">{formatPounds(o.total, locale)}</span>
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card className="mv-rise h-fit space-y-3 p-6">
                    <h2 className="font-display text-base">{t('customers.contact')}</h2>
                    {customer.phone ? (
                        <a href={`tel:${customer.phone}`} className="flex items-center gap-2 text-sm text-ink-link" dir="ltr">
                            <Phone className="h-4 w-4" aria-hidden />
                            {customer.phone}
                        </a>
                    ) : null}
                    {customer.email ? (
                        <a href={`mailto:${customer.email}`} className="flex items-center gap-2 truncate text-sm text-ink-link">
                            <Mail className="h-4 w-4" aria-hidden />
                            {customer.email}
                        </a>
                    ) : null}
                    {customer.address || customer.governorate ? (
                        <p className="flex items-start gap-2 text-sm">
                            <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
                            {joinAddress([customer.address, customer.city, customer.governorate ? t(`gov.${customer.governorate}`) : null], locale)}
                        </p>
                    ) : null}
                </Card>
            </div>
        </DashboardLayout>
    );
}
