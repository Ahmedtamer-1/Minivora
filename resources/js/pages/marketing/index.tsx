// Live Marketing overview: Pixels & CAPI settings form (PUT /marketing/pixels)
// and Newsletter Subscribers list with CSV export. FR-MKT-03 to FR-MKT-09.
import { router } from '@inertiajs/react';
import axios from 'axios';
import { Download, Mail, Radio, Save } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatTile } from '@/components/commerce';
import { Badge } from '@/components/ui/badge';
import { Button, buttonClasses } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatDate, formatNumber, useLocale, useT } from '@/lib/i18n';

interface PixelConfig {
    id?: number;
    meta_pixel_id: string | null;
    has_meta_capi_token?: boolean;
    meta_ad_account_id: string | null;
    tiktok_pixel_id: string | null;
    snap_pixel_id: string | null;
    ga4_measurement_id: string | null;
}

interface SubscriberRow {
    id: number;
    email: string;
    name: string | null;
    source: string;
    subscribed_at: string | null;
    created_at: string;
}

interface MarketingProps {
    pixel: PixelConfig;
    subscribers: SubscriberRow[];
}

export default function Marketing({ pixel, subscribers = [] }: MarketingProps) {
    const t = useT();
    const locale = useLocale();

    const [form, setForm] = useState({
        meta_pixel_id: pixel?.meta_pixel_id ?? '',
        meta_capi_token: '',
        meta_ad_account_id: pixel?.meta_ad_account_id ?? '',
        tiktok_pixel_id: pixel?.tiktok_pixel_id ?? '',
        snap_pixel_id: pixel?.snap_pixel_id ?? '',
        ga4_measurement_id: pixel?.ga4_measurement_id ?? '',
    });
    const [saving, setSaving] = useState(false);
    const [savedMessage, setSavedMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const connectedCount = [pixel?.meta_pixel_id, pixel?.tiktok_pixel_id, pixel?.snap_pixel_id, pixel?.ga4_measurement_id].filter(Boolean).length;

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setSavedMessage(null);
        setErrorMessage(null);

        const payload: Record<string, string | null> = {
            meta_pixel_id: form.meta_pixel_id.trim() || null,
            meta_ad_account_id: form.meta_ad_account_id.trim() || null,
            tiktok_pixel_id: form.tiktok_pixel_id.trim() || null,
            snap_pixel_id: form.snap_pixel_id.trim() || null,
            ga4_measurement_id: form.ga4_measurement_id.trim() || null,
        };

        if (form.meta_capi_token.trim() !== '') {
            payload.meta_capi_token = form.meta_capi_token.trim();
        }

        try {
            await axios.put('/marketing/pixels', payload);
            setSavedMessage('Pixel settings saved.');
            setForm((prev) => ({ ...prev, meta_capi_token: '' }));
            router.reload();
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const msg = (err.response?.data as { message?: string } | undefined)?.message;
                setErrorMessage(msg ?? 'Failed to save pixel settings.');
            } else {
                setErrorMessage('Failed to save pixel settings.');
            }
        } finally {
            setSaving(false);
        }
    };

    return (
        <DashboardLayout title={t('nav.marketing')}>
            <PageHeader title={t('marketing.title')} description={t('marketing.subtitle')} />

            <div className="mb-6 grid grid-cols-2 gap-2 sm:gap-4">
                <StatTile icon={Radio} label={t('marketing.pixels')} value={`${connectedCount} / 4`} hint={t('marketing.connected')} />
                <StatTile
                    icon={Mail}
                    label="Newsletter subscribers"
                    value={formatNumber(subscribers.length)}
                    hint={t('marketing.subscribers', { count: formatNumber(subscribers.length) })}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader title={t('marketing.pixels')} description={t('marketing.pixels_hint')} />
                    <CardBody>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="flex flex-wrap gap-2">
                                <Badge tone={pixel?.meta_pixel_id ? 'success' : 'neutral'}>
                                    Meta {pixel?.meta_pixel_id ? '✓' : '—'}
                                </Badge>
                                <Badge tone={pixel?.has_meta_capi_token ? 'success' : 'neutral'}>
                                    CAPI {pixel?.has_meta_capi_token ? '✓' : '—'}
                                </Badge>
                                <Badge tone={pixel?.tiktok_pixel_id ? 'success' : 'neutral'}>
                                    TikTok {pixel?.tiktok_pixel_id ? '✓' : '—'}
                                </Badge>
                                <Badge tone={pixel?.snap_pixel_id ? 'success' : 'neutral'}>
                                    Snap {pixel?.snap_pixel_id ? '✓' : '—'}
                                </Badge>
                                <Badge tone={pixel?.ga4_measurement_id ? 'success' : 'neutral'}>
                                    GA4 {pixel?.ga4_measurement_id ? '✓' : '—'}
                                </Badge>
                            </div>

                            <Field label={t('marketing.pixel_meta')} htmlFor="meta_pixel_id">
                                <Input
                                    id="meta_pixel_id"
                                    dir="ltr"
                                    placeholder="e.g. 123456789012345"
                                    value={form.meta_pixel_id}
                                    onChange={(e) => setForm({ ...form, meta_pixel_id: e.target.value })}
                                />
                            </Field>

                            <Field
                                label="Meta Conversions API (CAPI) Token"
                                htmlFor="meta_capi_token"
                                hint={pixel?.has_meta_capi_token ? 'A CAPI token is currently saved. Leave blank to keep it.' : 'Optional server-side token for Meta CAPI events.'}
                            >
                                <Input
                                    id="meta_capi_token"
                                    type="password"
                                    dir="ltr"
                                    autoComplete="off"
                                    placeholder={pixel?.has_meta_capi_token ? '••••••••••••••••' : 'EAA...'}
                                    value={form.meta_capi_token}
                                    onChange={(e) => setForm({ ...form, meta_capi_token: e.target.value })}
                                />
                            </Field>

                            <Field label="Meta Ad Account ID" htmlFor="meta_ad_account_id">
                                <Input
                                    id="meta_ad_account_id"
                                    dir="ltr"
                                    placeholder="e.g. act_123456789"
                                    value={form.meta_ad_account_id}
                                    onChange={(e) => setForm({ ...form, meta_ad_account_id: e.target.value })}
                                />
                            </Field>

                            <Field label={t('marketing.pixel_tiktok')} htmlFor="tiktok_pixel_id">
                                <Input
                                    id="tiktok_pixel_id"
                                    dir="ltr"
                                    placeholder="e.g. CXXXXXXXXXXXX"
                                    value={form.tiktok_pixel_id}
                                    onChange={(e) => setForm({ ...form, tiktok_pixel_id: e.target.value })}
                                />
                            </Field>

                            <Field label={t('marketing.pixel_snap')} htmlFor="snap_pixel_id">
                                <Input
                                    id="snap_pixel_id"
                                    dir="ltr"
                                    placeholder="e.g. xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                    value={form.snap_pixel_id}
                                    onChange={(e) => setForm({ ...form, snap_pixel_id: e.target.value })}
                                />
                            </Field>

                            <Field label={t('marketing.pixel_ga4')} htmlFor="ga4_measurement_id">
                                <Input
                                    id="ga4_measurement_id"
                                    dir="ltr"
                                    placeholder="e.g. G-XXXXXXXXXX"
                                    value={form.ga4_measurement_id}
                                    onChange={(e) => setForm({ ...form, ga4_measurement_id: e.target.value })}
                                />
                            </Field>

                            {savedMessage ? <p className="text-sm text-success">{savedMessage}</p> : null}
                            {errorMessage ? (
                                <p role="alert" className="text-sm text-danger">
                                    {errorMessage}
                                </p>
                            ) : null}

                            <div className="pt-2">
                                <Button type="submit" disabled={saving}>
                                    <Save className="h-4 w-4" aria-hidden />
                                    {saving ? 'Saving...' : 'Save pixel settings'}
                                </Button>
                            </div>
                        </form>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader
                        title="Newsletter Subscribers"
                        description={t('marketing.subscribers', { count: formatNumber(subscribers.length) })}
                        action={
                            <a href="/marketing/subscribers/export" className={buttonClasses('secondary', 'sm')}>
                                <Download className="h-4 w-4" aria-hidden />
                                Export CSV
                            </a>
                        }
                    />
                    <CardBody>
                        {subscribers.length === 0 ? (
                            <p className="py-8 text-center text-sm text-ink-muted">
                                No newsletter subscribers yet. Shoppers who sign up on your storefront will appear here.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-start text-sm">
                                    <thead className="border-b border-line text-caption text-ink-muted">
                                        <tr>
                                            <th scope="col" className="pb-3 pe-3 text-start font-medium">
                                                Email
                                            </th>
                                            <th scope="col" className="pb-3 px-3 text-start font-medium">
                                                Source
                                            </th>
                                            <th scope="col" className="pb-3 ps-3 text-end font-medium">
                                                Joined
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {subscribers.map((sub) => (
                                            <tr key={sub.id}>
                                                <td className="py-3 pe-3">
                                                    <span dir="ltr" className="block font-medium text-ink">
                                                        {sub.email}
                                                    </span>
                                                    {sub.name ? <span className="block text-caption text-ink-muted">{sub.name}</span> : null}
                                                </td>
                                                <td className="py-3 px-3">
                                                    <Badge tone="neutral">{sub.source}</Badge>
                                                </td>
                                                <td className="py-3 ps-3 text-end text-caption text-ink-muted">
                                                    {formatDate(sub.subscribed_at ?? sub.created_at, locale)}
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
