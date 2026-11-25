// Live Abandoned Checkouts & Checkout Leads with one-click WhatsApp recovery.
// FR-PAY-10, FR-MKT-01, FR-MKT-02.
import { MessageCircle, Phone, ShoppingCart, Wallet } from 'lucide-react';
import { StatTile } from '@/components/commerce';
import { Badge } from '@/components/ui/badge';
import { buttonClasses } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatDate, formatNumber, useLocale, useT } from '@/lib/i18n';
import { formatPiasters } from '@/lib/money';

interface AbandonedRow {
    id: number;
    lead_id: number | null;
    customer_name: string | null;
    phone: string;
    items: Array<Record<string, unknown>>;
    subtotal: number;
    reminded_at: string | null;
    created_at: string | null;
    whatsapp_url: string;
}

interface LeadRow {
    id: number;
    phone: string;
    name: string | null;
    email: string | null;
    governorate: string | null;
    captured_at: string | null;
    created_at: string | null;
    whatsapp_url: string;
}

interface AbandonedProps {
    abandoned: AbandonedRow[];
    leads: LeadRow[];
}

export default function Abandoned({ abandoned = [], leads = [] }: AbandonedProps) {
    const t = useT();
    const locale = useLocale();

    const totalValue = abandoned.reduce((sum, item) => sum + (item.subtotal ?? 0), 0);

    return (
        <DashboardLayout title={t('nav.marketing_abandoned')}>
            <PageHeader title={t('abandoned.title')} description={t('abandoned.subtitle')} />

            <div className="mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile
                    icon={ShoppingCart}
                    label={t('abandoned.stat_open')}
                    value={formatNumber(abandoned.length)}
                    hint={t('abandoned.stat_open_hint')}
                    tone={abandoned.length > 0 ? 'warning' : 'default'}
                />
                <StatTile
                    icon={Wallet}
                    label={t('abandoned.stat_value')}
                    value={formatPiasters(totalValue, locale)}
                    hint={t('abandoned.stat_value_hint')}
                />
                <StatTile
                    icon={Phone}
                    label="Open checkout leads"
                    value={formatNumber(leads.length)}
                    hint="Phone captured, not yet converted"
                />
            </div>

            <div className="space-y-6">
                <Card>
                    <CardHeader title={t('abandoned.list')} description={t('abandoned.list_hint')} />
                    <CardBody>
                        {abandoned.length === 0 ? (
                            <p className="py-8 text-center text-sm text-ink-muted">
                                No open abandoned checkouts right now.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-start text-sm">
                                    <caption className="sr-only">{t('abandoned.list')}</caption>
                                    <thead className="border-b border-line text-caption text-ink-muted">
                                        <tr>
                                            <th scope="col" className="pb-3 pe-3 text-start font-medium">
                                                {t('abandoned.col_customer')}
                                            </th>
                                            <th scope="col" className="pb-3 px-3 text-end font-medium">
                                                {t('abandoned.col_items')}
                                            </th>
                                            <th scope="col" className="pb-3 px-3 text-end font-medium">
                                                {t('abandoned.col_value')}
                                            </th>
                                            <th scope="col" className="pb-3 px-3 text-start font-medium">
                                                Status
                                            </th>
                                            <th scope="col" className="pb-3 ps-3 text-end font-medium">
                                                {t('abandoned.col_action')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {abandoned.map((a) => (
                                            <tr key={a.id}>
                                                <td className="py-3 pe-3">
                                                    <span className="block font-medium text-ink">
                                                        {a.customer_name || 'Guest shopper'}
                                                    </span>
                                                    <span dir="ltr" className="block text-caption text-ink-muted">
                                                        {a.phone}
                                                        {a.created_at ? ` · ${formatDate(a.created_at, locale, true)}` : ''}
                                                    </span>
                                                </td>
                                                <td className="tabular py-3 px-3 text-end text-ink">
                                                    {formatNumber(Array.isArray(a.items) ? a.items.length : 0)}
                                                </td>
                                                <td className="tabular py-3 px-3 text-end font-medium text-ink">
                                                    {formatPiasters(a.subtotal, locale)}
                                                </td>
                                                <td className="py-3 px-3">
                                                    {a.reminded_at ? (
                                                        <Badge tone="info">Reminded</Badge>
                                                    ) : (
                                                        <Badge tone="warning">Needs follow-up</Badge>
                                                    )}
                                                </td>
                                                <td className="py-3 ps-3 text-end">
                                                    <a
                                                        href={a.whatsapp_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className={buttonClasses('secondary', 'sm')}
                                                    >
                                                        <MessageCircle className="h-4 w-4" aria-hidden />
                                                        {t('abandoned.whatsapp')}
                                                    </a>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader
                        title="Checkout Leads"
                        description="Shoppers who entered their phone number at checkout without placing an order yet."
                    />
                    <CardBody>
                        {leads.length === 0 ? (
                            <p className="py-8 text-center text-sm text-ink-muted">
                                No unconverted checkout leads yet.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-start text-sm">
                                    <thead className="border-b border-line text-caption text-ink-muted">
                                        <tr>
                                            <th scope="col" className="pb-3 pe-3 text-start font-medium">
                                                Shopper
                                            </th>
                                            <th scope="col" className="pb-3 px-3 text-start font-medium">
                                                Governorate
                                            </th>
                                            <th scope="col" className="pb-3 px-3 text-start font-medium">
                                                Captured
                                            </th>
                                            <th scope="col" className="pb-3 ps-3 text-end font-medium">
                                                {t('abandoned.col_action')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {leads.map((lead) => (
                                            <tr key={lead.id}>
                                                <td className="py-3 pe-3">
                                                    <span className="block font-medium text-ink">
                                                        {lead.name || 'Checkout lead'}
                                                    </span>
                                                    <span dir="ltr" className="block text-caption text-ink-muted">
                                                        {lead.phone}
                                                        {lead.email ? ` · ${lead.email}` : ''}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-3 text-ink-muted">{lead.governorate || '—'}</td>
                                                <td className="py-3 px-3 text-caption text-ink-muted">
                                                    {lead.captured_at || lead.created_at
                                                        ? formatDate((lead.captured_at ?? lead.created_at) as string, locale, true)
                                                        : '—'}
                                                </td>
                                                <td className="py-3 ps-3 text-end">
                                                    <a
                                                        href={lead.whatsapp_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className={buttonClasses('secondary', 'sm')}
                                                    >
                                                        <MessageCircle className="h-4 w-4" aria-hidden />
                                                        {t('abandoned.whatsapp')}
                                                    </a>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardBody>
                </Card>
            </div>
        </DashboardLayout>
    );
}
