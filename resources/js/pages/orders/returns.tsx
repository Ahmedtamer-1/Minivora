// Returns and refunds: live return requests with approve/reject actions and create form. UC-08, FR-ORD-08
import { router } from '@inertiajs/react';
import { Check, Plus, RotateCcw, Undo2, Wallet, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatTile } from '@/components/commerce';
import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input, Select, Textarea } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatDate, formatNumber, useLocale, useT } from '@/lib/i18n';
import { formatPiasters } from '@/lib/money';

interface ReturnRow {
    id: number;
    order_id: number;
    order_number: number | null;
    customer_name: string | null;
    type: 'return' | 'exchange';
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
    refund_amount: number;
    created_at: string;
    reviewed_at: string | null;
}

interface DeliveredOrder {
    id: number;
    number: number;
    customer_name: string;
    total: number;
    delivered_at: string | null;
}

interface Props {
    returns?: ReturnRow[];
    deliveredOrders?: DeliveredOrder[];
}

const statusTone: Record<ReturnRow['status'], Tone> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export default function Returns({ returns = [], deliveredOrders = [] }: Props) {
    const t = useT();
    const locale = useLocale();
    const isAr = locale === 'ar';

    const [orderId, setOrderId] = useState<string>(deliveredOrders[0] ? String(deliveredOrders[0].id) : '');
    const [type, setType] = useState<'return' | 'exchange'>('return');
    const [reason, setReason] = useState('');
    const [refundEgp, setRefundEgp] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [feedback, setFeedback] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);

    const openCount = returns.filter((r) => r.status === 'pending').length;
    const approvedTotal = returns.filter((r) => r.status === 'approved').reduce((sum, r) => sum + r.refund_amount, 0);

    const statusLabel = (status: ReturnRow['status']) => {
        if (status === 'pending') return isAr ? 'قيد المراجعة' : 'Pending';
        if (status === 'approved') return isAr ? 'موافق عليه' : 'Approved';
        return isAr ? 'مرفوض' : 'Rejected';
    };

    const callJson = async (url: string, body?: Record<string, unknown>) => {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error((data as { message?: string }).message ?? (isAr ? 'حدث خطأ أثناء تنفيذ الطلب.' : 'Request failed.'));
        }
        return data;
    };

    const handleAction = async (id: number, action: 'approve' | 'reject') => {
        setBusyId(id);
        setFeedback(null);
        try {
            await callJson(`/returns/${id}/${action}`);
            setFeedback({
                kind: 'success',
                text:
                    action === 'approve'
                        ? isAr
                            ? 'تمت الموافقة على طلب الإرجاع.'
                            : 'Return request approved.'
                        : isAr
                          ? 'تم رفض طلب الإرجاع.'
                          : 'Return request rejected.',
            });
            router.reload();
        } catch (err) {
            setFeedback({ kind: 'error', text: err instanceof Error ? err.message : 'Error' });
        } finally {
            setBusyId(null);
        }
    };

    const handleCreate = async (e: FormEvent) => {
        e.preventDefault();
        if (!orderId || !reason.trim()) return;
        setSubmitting(true);
        setFeedback(null);
        try {
            const payload: Record<string, unknown> = {
                order_id: Number(orderId),
                type,
                reason: reason.trim(),
            };
            if (refundEgp.trim() !== '') {
                const parsed = Number.parseFloat(refundEgp);
                if (!Number.isNaN(parsed) && parsed >= 0) {
                    payload.refund_amount = Math.round(parsed * 100);
                }
            }
            await callJson('/returns', payload);
            setReason('');
            setRefundEgp('');
            setFeedback({ kind: 'success', text: isAr ? 'تم إنشاء طلب الإرجاع بنجاح.' : 'Return request created.' });
            router.reload();
        } catch (err) {
            setFeedback({ kind: 'error', text: err instanceof Error ? err.message : 'Error' });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <DashboardLayout title={t('nav.orders_returns')}>
            <PageHeader title={t('returns.title')} description={t('returns.subtitle')} />

            {feedback ? (
                <div
                    role="status"
                    className={`mb-6 rounded-2xl px-4 py-3 text-sm ${
                        feedback.kind === 'success' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'
                    }`}
                >
                    {feedback.text}
                </div>
            ) : null}

            <div className="mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile icon={Undo2} label={t('returns.stat_open')} value={formatNumber(openCount)} hint={t('returns.stat_open_hint')} tone="warning" />
                <StatTile icon={RotateCcw} label={isAr ? 'إجمالي الطلبات' : 'Total requests'} value={formatNumber(returns.length)} />
                <StatTile
                    icon={Wallet}
                    label={t('returns.stat_refunded')}
                    value={formatPiasters(approvedTotal, locale)}
                    hint={t('returns.stat_refunded_hint')}
                />
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card>
                    <CardHeader title={t('returns.list')} />
                    <CardBody className="p-0">
                        {returns.length === 0 ? (
                            <p className="px-6 py-8 text-center text-sm text-ink-muted">
                                {isAr ? 'لا توجد طلبات إرجاع أو استبدال حالياً.' : 'No return or exchange requests yet.'}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[40rem] text-sm">
                                    <caption className="sr-only">{t('returns.list')}</caption>
                                    <thead>
                                        <tr className="border-b border-line text-caption text-ink-muted">
                                            <th scope="col" className="px-6 py-3 text-start font-medium">
                                                {t('returns.col_order')}
                                            </th>
                                            <th scope="col" className="px-4 py-3 text-start font-medium">
                                                {t('returns.col_customer')}
                                            </th>
                                            <th scope="col" className="px-4 py-3 text-start font-medium">
                                                {t('returns.col_reason')}
                                            </th>
                                            <th scope="col" className="px-4 py-3 text-end font-medium">
                                                {t('returns.col_amount')}
                                            </th>
                                            <th scope="col" className="px-4 py-3 text-center font-medium">
                                                {t('returns.col_status')}
                                            </th>
                                            <th scope="col" className="px-6 py-3 text-end font-medium">
                                                {isAr ? 'إجراء' : 'Actions'}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {returns.map((r) => (
                                            <tr key={r.id} className="hover:bg-secondary/40">
                                                <td className="px-6 py-3.5">
                                                    <span dir="ltr" className="block font-medium text-ink">
                                                        #{r.order_number ?? r.order_id}
                                                    </span>
                                                    <span className="text-xs text-ink-muted">{formatDate(r.created_at, locale)}</span>
                                                </td>
                                                <td className="px-4 py-3.5 text-ink">{r.customer_name ?? '—'}</td>
                                                <td className="max-w-xs px-4 py-3.5">
                                                    <span className="block truncate text-ink-muted">{r.reason}</span>
                                                    <span className="text-xs text-ink-muted">
                                                        {r.type === 'exchange' ? (isAr ? 'استبدال' : 'Exchange') : isAr ? 'إرجاع' : 'Return'}
                                                    </span>
                                                </td>
                                                <td className="tabular px-4 py-3.5 text-end text-ink">{formatPiasters(r.refund_amount, locale)}</td>
                                                <td className="px-4 py-3.5 text-center">
                                                    <Badge tone={statusTone[r.status]}>{statusLabel(r.status)}</Badge>
                                                </td>
                                                <td className="px-6 py-3.5 text-end">
                                                    {r.status === 'pending' ? (
                                                        <div className="inline-flex items-center gap-1.5">
                                                            <Button
                                                                size="sm"
                                                                variant="primary"
                                                                disabled={busyId === r.id}
                                                                onClick={() => handleAction(r.id, 'approve')}
                                                            >
                                                                <Check className="h-3.5 w-3.5" aria-hidden />
                                                                {isAr ? 'موافقة' : 'Approve'}
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="danger"
                                                                disabled={busyId === r.id}
                                                                onClick={() => handleAction(r.id, 'reject')}
                                                            >
                                                                <X className="h-3.5 w-3.5" aria-hidden />
                                                                {isAr ? 'رفض' : 'Reject'}
                                                            </Button>
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-ink-muted">—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardBody>
                </Card>

                <Card className="self-start">
                    <CardHeader
                        title={isAr ? 'إنشاء طلب إرجاع' : 'Create return request'}
                        description={isAr ? 'اختر طلباً تم تسليمه لتسجيل إرجاع أو استبدال.' : 'Select a delivered order to log a return or exchange.'}
                    />
                    <CardBody>
                        {deliveredOrders.length === 0 ? (
                            <p className="text-sm text-ink-muted">
                                {isAr ? 'لا توجد طلبات مُسلّمة متاحة حالياً.' : 'No delivered orders available yet.'}
                            </p>
                        ) : (
                            <form onSubmit={handleCreate} className="space-y-4">
                                <Field label={isAr ? 'الطلب المُسلّم' : 'Delivered order'} htmlFor="ret_order">
                                    <Select id="ret_order" value={orderId} onChange={(e) => setOrderId(e.target.value)} required>
                                        {deliveredOrders.map((o) => (
                                            <option key={o.id} value={o.id}>
                                                #{o.number} — {o.customer_name} ({formatPiasters(o.total, locale)})
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={isAr ? 'النوع' : 'Type'} htmlFor="ret_type">
                                    <Select id="ret_type" value={type} onChange={(e) => setType(e.target.value as 'return' | 'exchange')}>
                                        <option value="return">{isAr ? 'إرجاع واسترداد' : 'Return & refund'}</option>
                                        <option value="exchange">{isAr ? 'استبدال' : 'Exchange'}</option>
                                    </Select>
                                </Field>
                                <Field
                                    label={isAr ? 'مبلغ الاسترداد (ج.م - اختياري)' : 'Refund amount (EGP, optional)'}
                                    htmlFor="ret_amount"
                                    hint={isAr ? 'اتركه فارغاً لاسترداد إجمالي الطلب.' : 'Leave blank to default to the order total.'}
                                >
                                    <Input
                                        id="ret_amount"
                                        type="number"
                                        dir="ltr"
                                        min="0"
                                        step="0.01"
                                        value={refundEgp}
                                        onChange={(e) => setRefundEgp(e.target.value)}
                                        placeholder="0.00"
                                    />
                                </Field>
                                <Field label={isAr ? 'السبب' : 'Reason'} htmlFor="ret_reason">
                                    <Textarea
                                        id="ret_reason"
                                        rows={3}
                                        value={reason}
                                        onChange={(e) => setReason(e.target.value)}
                                        placeholder={isAr ? 'اكتب سبب الإرجاع أو الاستبدال...' : 'Describe the reason for the return...'}
                                        required
                                        maxLength={2000}
                                    />
                                </Field>
                                <Button type="submit" disabled={submitting || !orderId || !reason.trim()} className="w-full">
                                    <Plus className="h-4 w-4" aria-hidden />
                                    {submitting ? t('common.saving') : isAr ? 'تسجيل الطلب' : 'Create return request'}
                                </Button>
                            </form>
                        )}
                    </CardBody>
                </Card>
            </div>
        </DashboardLayout>
    );
}
