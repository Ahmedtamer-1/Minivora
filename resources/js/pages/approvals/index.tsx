// Live AI Approvals Inbox, Proactive Insights Feed, and Morning Brief summary card.
// UC-04, FR-AI-07, FR-AI-10, FR-PRO-04 to FR-PRO-06.
import { router } from '@inertiajs/react';
import axios from 'axios';
import { BellOff, Check, CircleCheck, Clock, Sparkles, Sun, X } from 'lucide-react';
import { useState } from 'react';
import { StatTile } from '@/components/commerce';
import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatDate, formatNumber, useLocale, useT } from '@/lib/i18n';
import { formatPiasters } from '@/lib/money';

interface ApprovalRow {
    id: number;
    agent: string;
    function_name: string;
    payload: Record<string, unknown>;
    estimated_impact: number;
    status: 'pending' | 'approved' | 'rejected' | 'executed' | string;
    created_at: string;
    decided_at: string | null;
}

interface InsightRow {
    id: number;
    agent: string;
    cadence: string;
    type: string;
    urgency: number;
    estimated_impact: number;
    score: number;
    title: string;
    summary: string;
    status: string;
    is_urgent: boolean;
    recommended_action: Record<string, unknown> | null;
    created_at: string;
}

interface MorningBriefData {
    date: string;
    yesterday: {
        date: string;
        orders_count: number;
        sales_piasters: number;
        sales_egp: number;
    };
    priorities: Array<{
        id: number;
        agent: string;
        type: string;
        title: string;
        summary: string;
        score: number;
        is_urgent: boolean;
    }>;
    pending_approvals_count: number;
    summary: string;
}

interface ApprovalsProps {
    approvals: ApprovalRow[];
    insights: InsightRow[];
    brief: MorningBriefData;
}

const approvalStatusTone: Record<string, Tone> = {
    pending: 'warning',
    approved: 'success',
    executed: 'success',
    rejected: 'danger',
};

export default function Approvals({ approvals = [], insights = [], brief }: ApprovalsProps) {
    const t = useT();
    const locale = useLocale();
    const [busyKey, setBusyKey] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const pendingApprovals = approvals.filter((a) => a.status === 'pending');
    const decidedApprovals = approvals.filter((a) => a.status !== 'pending');

    const handleApprovalAction = async (id: number, action: 'approve' | 'reject') => {
        setBusyKey(`approval-${id}-${action}`);
        setErrorMessage(null);
        try {
            await axios.post(`/approvals/${id}/${action}`);
            router.reload();
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const msg = (err.response?.data as { message?: string } | undefined)?.message;
                setErrorMessage(msg ?? `Failed to ${action} approval.`);
            } else {
                setErrorMessage(`Failed to ${action} approval.`);
            }
        } finally {
            setBusyKey(null);
        }
    };

    const handleInsightAction = async (id: number, action: 'approve' | 'snooze' | 'dismiss') => {
        setBusyKey(`insight-${id}-${action}`);
        setErrorMessage(null);
        try {
            await axios.post(`/insights/${id}/act`, {
                action,
                ...(action === 'snooze' ? { snooze_hours: 24 } : {}),
            });
            router.reload();
        } catch (err) {
            if (axios.isAxiosError(err)) {
                const msg = (err.response?.data as { message?: string } | undefined)?.message;
                setErrorMessage(msg ?? `Failed to ${action} insight.`);
            } else {
                setErrorMessage(`Failed to ${action} insight.`);
            }
        } finally {
            setBusyKey(null);
        }
    };

    return (
        <DashboardLayout title={t('nav.approvals')}>
            <PageHeader title={t('approvals.title')} description={t('approvals.subtitle')} />

            {errorMessage ? (
                <div role="alert" className="mb-4 rounded-xl bg-danger-subtle px-4 py-3 text-sm text-danger">
                    {errorMessage}
                </div>
            ) : null}

            <div className="mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile
                    icon={Clock}
                    label={t('approvals.stat_pending')}
                    value={formatNumber(pendingApprovals.length)}
                    tone={pendingApprovals.length > 0 ? 'warning' : 'default'}
                />
                <StatTile
                    icon={Sparkles}
                    label="Open insights"
                    value={formatNumber(insights.length)}
                    hint="Ranked by urgency & impact"
                />
                <StatTile
                    icon={CircleCheck}
                    label="Decided recently"
                    value={formatNumber(decidedApprovals.length)}
                />
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="min-w-0 space-y-6">
                    <section aria-labelledby="pending-title">
                        <h2 id="pending-title" className="mb-3 font-display text-base">
                            {t('approvals.pending')}
                        </h2>
                        {pendingApprovals.length === 0 ? (
                            <Card className="p-6 text-center text-sm text-ink-muted">
                                No pending AI approvals right now.
                            </Card>
                        ) : (
                            <ul className="space-y-4">
                                {pendingApprovals.map((a) => (
                                    <li key={a.id}>
                                        <Card className="p-5 sm:p-6">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge tone="info">{t(`agents.${a.agent}`) || a.agent.toUpperCase()}</Badge>
                                                <Badge tone={approvalStatusTone[a.status] ?? 'warning'}>{a.status}</Badge>
                                                <span className="ms-auto text-caption text-ink-muted">
                                                    {formatDate(a.created_at, locale, true)}
                                                </span>
                                            </div>
                                            <h3 className="mt-3 font-display text-lg">{a.function_name}</h3>
                                            {a.payload && Object.keys(a.payload).length > 0 ? (
                                                <pre
                                                    dir="ltr"
                                                    className="mt-2 overflow-x-auto rounded-lg bg-secondary p-3 font-mono text-xs text-ink-muted"
                                                >
                                                    {JSON.stringify(a.payload, null, 2)}
                                                </pre>
                                            ) : null}
                                            {a.estimated_impact > 0 ? (
                                                <p className="mt-3 text-sm">
                                                    {t('approvals.impact')}{' '}
                                                    <span className="tabular font-semibold">
                                                        {formatPiasters(a.estimated_impact, locale)}
                                                    </span>
                                                </p>
                                            ) : null}
                                            <div className="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
                                                <Button
                                                    disabled={busyKey !== null}
                                                    onClick={() => handleApprovalAction(a.id, 'approve')}
                                                >
                                                    <Check className="h-4 w-4" aria-hidden />
                                                    {t('approvals.approve')}
                                                </Button>
                                                <Button
                                                    variant="danger"
                                                    disabled={busyKey !== null}
                                                    onClick={() => handleApprovalAction(a.id, 'reject')}
                                                >
                                                    <X className="h-4 w-4" aria-hidden />
                                                    {t('approvals.reject')}
                                                </Button>
                                            </div>
                                        </Card>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section aria-labelledby="insights-title">
                        <h2 id="insights-title" className="mb-3 font-display text-base">
                            Proactive AI Insights
                        </h2>
                        {insights.length === 0 ? (
                            <Card className="p-6 text-center text-sm text-ink-muted">
                                All caught up — no open proactive insights right now.
                            </Card>
                        ) : (
                            <ul className="space-y-4">
                                {insights.map((insight) => (
                                    <li key={insight.id}>
                                        <Card className="p-5 sm:p-6">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge tone="info">{t(`agents.${insight.agent}`) || insight.agent.toUpperCase()}</Badge>
                                                {insight.is_urgent ? <Badge tone="danger">Urgent</Badge> : null}
                                                <Badge tone="neutral">{insight.cadence}</Badge>
                                                <span className="ms-auto text-caption text-ink-muted">
                                                    Score {insight.score.toFixed(1)}
                                                </span>
                                            </div>
                                            <h3 className="mt-3 font-display text-lg">{insight.title}</h3>
                                            <p className="mt-1 text-sm text-ink-muted">{insight.summary}</p>
                                            {insight.estimated_impact !== 0 ? (
                                                <p className="mt-3 text-sm">
                                                    {t('approvals.impact')}{' '}
                                                    <span className="tabular font-semibold">
                                                        {formatPiasters(insight.estimated_impact, locale)}
                                                    </span>
                                                </p>
                                            ) : null}
                                            <div className="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
                                                <Button
                                                    size="sm"
                                                    disabled={busyKey !== null}
                                                    onClick={() => handleInsightAction(insight.id, 'approve')}
                                                >
                                                    <Check className="h-4 w-4" aria-hidden />
                                                    Approve
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    disabled={busyKey !== null}
                                                    onClick={() => handleInsightAction(insight.id, 'snooze')}
                                                >
                                                    <BellOff className="h-4 w-4" aria-hidden />
                                                    Snooze 24h
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={busyKey !== null}
                                                    onClick={() => handleInsightAction(insight.id, 'dismiss')}
                                                >
                                                    <X className="h-4 w-4" aria-hidden />
                                                    Dismiss
                                                </Button>
                                            </div>
                                        </Card>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

                <aside className="space-y-6">
                    <Card>
                        <CardHeader
                            title={
                                <span className="inline-flex items-center gap-2">
                                    <Sun className="h-4 w-4 text-warning" aria-hidden />
                                    Morning Brief
                                </span>
                            }
                            description={brief?.date ?? undefined}
                        />
                        <CardBody className="space-y-4">
                            <p className="text-sm text-ink">{brief?.summary ?? 'No morning brief compiled yet.'}</p>

                            {brief?.yesterday ? (
                                <div className="grid grid-cols-2 gap-3 rounded-xl bg-secondary p-3">
                                    <div>
                                        <p className="text-caption text-ink-muted">Yesterday orders</p>
                                        <p className="tabular font-display text-base font-semibold text-ink">
                                            {formatNumber(brief.yesterday.orders_count)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-caption text-ink-muted">Yesterday sales</p>
                                        <p className="tabular font-display text-base font-semibold text-ink">
                                            {formatPiasters(brief.yesterday.sales_piasters, locale)}
                                        </p>
                                    </div>
                                </div>
                            ) : null}

                            {brief?.priorities && brief.priorities.length > 0 ? (
                                <div>
                                    <p className="mb-2 text-caption font-medium text-ink-muted">Today&apos;s priorities</p>
                                    <ul className="space-y-2 text-sm">
                                        {brief.priorities.map((p) => (
                                            <li key={p.id} className="rounded-lg border border-line p-2.5">
                                                <span className="block font-medium text-ink">{p.title}</span>
                                                <span className="block text-caption text-ink-muted">{p.summary}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null}
                        </CardBody>
                    </Card>

                    {decidedApprovals.length > 0 ? (
                        <Card>
                            <CardHeader title="Recent Decisions" description="Latest approved or rejected AI actions." />
                            <CardBody>
                                <ul className="divide-y divide-line text-sm">
                                    {decidedApprovals.slice(0, 8).map((item) => (
                                        <li key={item.id} className="flex items-center justify-between gap-2 py-2.5">
                                            <div className="min-w-0">
                                                <p className="truncate font-medium text-ink">{item.function_name}</p>
                                                <p className="text-caption text-ink-muted">
                                                    {t(`agents.${item.agent}`) || item.agent.toUpperCase()}
                                                </p>
                                            </div>
                                            <Badge tone={approvalStatusTone[item.status] ?? 'neutral'}>{item.status}</Badge>
                                        </li>
                                    ))}
                                </ul>
                            </CardBody>
                        </Card>
                    ) : null}
                </aside>
            </div>
        </DashboardLayout>
    );
}
