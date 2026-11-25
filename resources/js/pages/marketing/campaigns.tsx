// Live Campaigns & MI Verdict table (Scale / Keep / Fix / Pause) + Log Ad Spend form.
// UC-12, FR-MI-02 to FR-MI-08.
import { router } from '@inertiajs/react';
import axios from 'axios';
import { Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatDate, formatNumber, useLocale, useT } from '@/lib/i18n';
import { formatPiasters } from '@/lib/money';

interface CampaignVerdictRow {
    campaign_id: number;
    name: string;
    channel: string;
    utm_campaign: string | null;
    status: string;
    sessions: number;
    spend: number;
    impressions: number;
    clicks: number;
    frequency: number;
    store_verified_orders: number;
    revenue: number;
    profit: number;
    roas: number;
    ctr: number;
    verdict: 'Scale' | 'Keep' | 'Fix' | 'Pause' | string;
    rule: string;
}

interface AdSpendRow {
    id: number;
    channel: string;
    campaign_name: string;
    spend_date: string;
    amount: number;
}

interface CampaignsProps {
    campaigns: CampaignVerdictRow[];
    adSpends: AdSpendRow[];
}

const verdictTone: Record<string, Tone> = {
    scale: 'success',
    keep: 'info',
    fix: 'warning',
    pause: 'danger',
};

const ruleExplanation: Record<string, string> = {
    spend_without_orders: 'Spend exceeded threshold with 0 store-verified orders.',
    low_ctr: 'CTR below 1.0% across 1,000+ impressions.',
    profitable_high_roas: 'Profitable with ROAS at or above target and healthy frequency.',
    maintain: 'Performing within normal guardrails — keep monitoring.',
};

export default function Campaigns({ campaigns = [], adSpends = [] }: CampaignsProps) {
    const t = useT();
    const locale = useLocale();

    const todayIso = new Date().toISOString().slice(0, 10);
    const [channel, setChannel] = useState('meta');
    const [campaignName, setCampaignName] = useState('');
    const [spendDate, setSpendDate] = useState(todayIso);
    const [amountEgp, setAmountEgp] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [feedback, setFeedback] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const handleLogSpend = async (e: FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        setFeedback(null);
        setError(null);

        const parsedEgp = Number.parseFloat(amountEgp);
        const amountPiasters = Number.isFinite(parsedEgp) ? Math.round(parsedEgp * 100) : 0;

        if (!campaignName.trim() || amountPiasters < 1) {
            setError('Enter a campaign name and a positive spend amount in EGP.');
            setSubmitting(false);
            return;
        }

        try {
            await axios.post('/marketing/ad-spends', {
                channel,
                campaign_name: campaignName.trim(),
                spend_date: spendDate,
                amount: amountPiasters,
            });
            setCampaignName('');
            setAmountEgp('');
            setFeedback('Ad spend logged.');
            router.reload();
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const msg = (err.response?.data as { message?: string } | undefined)?.message;
                setError(msg ?? 'Failed to log ad spend.');
            } else {
                setError('Failed to log ad spend.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <DashboardLayout title={t('nav.marketing_campaigns')}>
            <PageHeader title={t('campaigns.title')} description={t('campaigns.subtitle')} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="space-y-6">
                    <Card>
                        <CardHeader title={t('campaigns.list')} description={t('campaigns.list_hint')} />
                        <CardBody>
                            {campaigns.length === 0 ? (
                                <p className="py-8 text-center text-sm text-ink-muted">
                                    No tracked campaigns yet. Import Meta campaigns or tag orders with UTM campaign codes to see store-verified verdicts.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-start text-sm">
                                        <caption className="sr-only">{t('campaigns.list')}</caption>
                                        <thead className="border-b border-line text-caption text-ink-muted">
                                            <tr>
                                                <th scope="col" className="pb-3 pe-4 text-start font-medium">
                                                    {t('campaigns.col_name')}
                                                </th>
                                                <th scope="col" className="pb-3 px-3 text-end font-medium">
                                                    {t('campaigns.col_spend')}
                                                </th>
                                                <th scope="col" className="pb-3 px-3 text-end font-medium">
                                                    {t('campaigns.col_sales')}
                                                </th>
                                                <th scope="col" className="pb-3 px-3 text-end font-medium">
                                                    {t('campaigns.col_roas')}
                                                </th>
                                                <th scope="col" className="pb-3 px-3 text-end font-medium">
                                                    Profit
                                                </th>
                                                <th scope="col" className="pb-3 ps-3 text-end font-medium">
                                                    {t('campaigns.col_verdict')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-line">
                                            {campaigns.map((c) => {
                                                const key = c.verdict.toLowerCase();
                                                return (
                                                    <tr key={c.campaign_id}>
                                                        <td className="py-3 pe-4">
                                                            <span className="block font-medium text-ink">{c.name}</span>
                                                            <span className="block text-caption text-ink-muted">
                                                                {ruleExplanation[c.rule] ?? c.rule}
                                                            </span>
                                                        </td>
                                                        <td className="tabular py-3 px-3 text-end text-ink-muted">
                                                            {formatPiasters(c.spend, locale)}
                                                        </td>
                                                        <td className="tabular py-3 px-3 text-end text-ink">
                                                            {formatNumber(c.store_verified_orders)}
                                                        </td>
                                                        <td className="tabular py-3 px-3 text-end font-medium text-ink">
                                                            {c.roas.toFixed(2)}×
                                                        </td>
                                                        <td
                                                            className={`tabular py-3 px-3 text-end font-medium ${
                                                                c.profit < 0 ? 'text-danger' : 'text-success'
                                                            }`}
                                                        >
                                                            {formatPiasters(c.profit, locale)}
                                                        </td>
                                                        <td className="py-3 ps-3 text-end">
                                                            <Badge tone={verdictTone[key] ?? 'neutral'}>
                                                                {t(`campaigns.verdict_${key}`) || c.verdict}
                                                            </Badge>
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

                    <Card>
                        <CardHeader title="Recent Ad Spend Log" description="Manual and imported ad spend entries." />
                        <CardBody>
                            {adSpends.length === 0 ? (
                                <p className="py-6 text-center text-sm text-ink-muted">No ad spend entries logged yet.</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-start text-sm">
                                        <thead className="border-b border-line text-caption text-ink-muted">
                                            <tr>
                                                <th scope="col" className="pb-3 pe-3 text-start font-medium">
                                                    Campaign
                                                </th>
                                                <th scope="col" className="pb-3 px-3 text-start font-medium">
                                                    Channel
                                                </th>
                                                <th scope="col" className="pb-3 px-3 text-start font-medium">
                                                    Date
                                                </th>
                                                <th scope="col" className="pb-3 ps-3 text-end font-medium">
                                                    Amount
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-line">
                                            {adSpends.map((entry) => (
                                                <tr key={entry.id}>
                                                    <td className="py-3 pe-3 font-medium text-ink">{entry.campaign_name}</td>
                                                    <td className="py-3 px-3">
                                                        <Badge tone="neutral">{entry.channel}</Badge>
                                                    </td>
                                                    <td className="py-3 px-3 text-caption text-ink-muted">
                                                        {formatDate(entry.spend_date, locale)}
                                                    </td>
                                                    <td className="tabular py-3 ps-3 text-end font-medium text-ink">
                                                        {formatPiasters(entry.amount, locale)}
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

                <aside>
                    <Card>
                        <CardHeader title="Log Ad Spend" description="Record daily ad spend (posted in EGP, stored in piasters)." />
                        <CardBody>
                            <form onSubmit={handleLogSpend} className="space-y-4">
                                <Field label="Channel" htmlFor="spend_channel">
                                    <Select id="spend_channel" value={channel} onChange={(e) => setChannel(e.target.value)}>
                                        <option value="meta">Meta (Facebook / Instagram)</option>
                                        <option value="tiktok">TikTok</option>
                                        <option value="google">Google Ads</option>
                                        <option value="snap">Snapchat</option>
                                        <option value="other">Other</option>
                                    </Select>
                                </Field>

                                <Field label="Campaign name" htmlFor="spend_campaign_name">
                                    <Input
                                        id="spend_campaign_name"
                                        required
                                        placeholder="e.g. Ramadan Eid Collection"
                                        value={campaignName}
                                        onChange={(e) => setCampaignName(e.target.value)}
                                    />
                                </Field>

                                <Field label="Spend date" htmlFor="spend_date">
                                    <Input
                                        id="spend_date"
                                        type="date"
                                        required
                                        value={spendDate}
                                        onChange={(e) => setSpendDate(e.target.value)}
                                    />
                                </Field>

                                <Field label="Amount (EGP)" htmlFor="spend_amount">
                                    <Input
                                        id="spend_amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        required
                                        placeholder="e.g. 500"
                                        value={amountEgp}
                                        onChange={(e) => setAmountEgp(e.target.value)}
                                    />
                                </Field>

                                {feedback ? <p className="text-sm text-success">{feedback}</p> : null}
                                {error ? (
                                    <p role="alert" className="text-sm text-danger">
                                        {error}
                                    </p>
                                ) : null}

                                <Button type="submit" disabled={submitting} className="w-full">
                                    <Plus className="h-4 w-4" aria-hidden />
                                    {submitting ? 'Logging...' : 'Log ad spend'}
                                </Button>
                            </form>
                        </CardBody>
                    </Card>
                </aside>
            </div>
        </DashboardLayout>
    );
}
