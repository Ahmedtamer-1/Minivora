// Payment settings: gateway overview, pending InstaPay receipt verification, and recent payments. FR-PAY-04..09
import { router } from '@inertiajs/react';
import { Banknote, CheckCircle2, CreditCard, Receipt, ShieldCheck, Smartphone, Wallet, type LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { SettingsLayout } from '@/layouts/settings-layout';
import { formatDate, formatNumber, useLocale, useT } from '@/lib/i18n';
import { formatPiasters } from '@/lib/money';

interface PaymentRow {
    id: number;
    order_id: number;
    order_number: number | null;
    customer_name: string | null;
    gateway: string;
    mode: string;
    status: string;
    amount: number;
    gateway_fee: number;
    cod_fee: number;
    external_reference: string | null;
    receipt_path: string | null;
    paid_at: string | null;
    created_at: string;
}

interface Props {
    pendingInstaPay?: PaymentRow[];
    recentPayments?: PaymentRow[];
}

const gateways: Array<{ key: string; icon: LucideIcon; tone: Tone; statusEn: string; statusAr: string }> = [
    { key: 'cod', icon: Banknote, tone: 'success', statusEn: 'Active', statusAr: 'مفعّل' },
    { key: 'kashier', icon: CreditCard, tone: 'info', statusEn: 'Test mode', statusAr: 'وضع الاختبار' },
    { key: 'instapay', icon: Smartphone, tone: 'success', statusEn: 'Active (Manual verify)', statusAr: 'مفعّل (تأكيد يدوي)' },
    { key: 'paymob', icon: Wallet, tone: 'info', statusEn: 'Test mode', statusAr: 'وضع الاختبار' },
    { key: 'fawry', icon: Receipt, tone: 'neutral', statusEn: 'Available', statusAr: 'متاح' },
];

const paymentStatusTone: Record<string, Tone> = {
    paid: 'success',
    pending: 'warning',
    failed: 'danger',
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export default function PaymentSettings({ pendingInstaPay = [], recentPayments = [] }: Props) {
    const t = useT();
    const locale = useLocale();
    const isAr = locale === 'ar';
    const [verifyingId, setVerifyingId] = useState<number | null>(null);
    const [feedback, setFeedback] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);

    const verifyInstaPay = async (paymentId: number) => {
        setVerifyingId(paymentId);
        setFeedback(null);
        try {
            const res = await fetch(`/payments/${paymentId}/verify-instapay`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error((data as { message?: string }).message ?? (isAr ? 'تعذر تأكيد التحويل.' : 'Verification failed.'));
            }
            setFeedback({ kind: 'success', text: isAr ? 'تم تأكيد دفعة إنستاباي بنجاح.' : 'InstaPay payment verified.' });
            router.reload();
        } catch (err) {
            setFeedback({ kind: 'error', text: err instanceof Error ? err.message : 'Error' });
        } finally {
            setVerifyingId(null);
        }
    };

    return (
        <SettingsLayout title={t('payments.title')} description={t('payments.subtitle')}>
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
                <CardHeader title={t('payments.methods')} description={t('payments.methods_hint')} />
                <CardBody>
                    <ul className="divide-y divide-line">
                        {gateways.map(({ key, icon: Icon, tone, statusEn, statusAr }) => (
                            <li key={key} className="flex min-h-[64px] items-center gap-3 py-2">
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                                    <Icon className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium text-ink">{t(`payments.m_${key}`)}</span>
                                    <span className="block text-caption text-ink-muted">{t(`payments.m_${key}_hint`)}</span>
                                </span>
                                <Badge tone={tone}>{isAr ? statusAr : statusEn}</Badge>
                            </li>
                        ))}
                    </ul>
                </CardBody>
            </Card>

            <Card>
                <CardHeader
                    title={isAr ? 'تحويلات إنستاباي بانتظار التأكيد' : 'Pending InstaPay verification'}
                    description={
                        isAr
                            ? 'راجع التحويلات المستلمة واضغط تأكيد لتمييز الطلب كمدفوع.'
                            : 'Review incoming InstaPay transfers and verify to mark the order paid.'
                    }
                    action={<Badge tone={pendingInstaPay.length > 0 ? 'warning' : 'neutral'}>{formatNumber(pendingInstaPay.length)}</Badge>}
                />
                <CardBody className="p-0">
                    {pendingInstaPay.length === 0 ? (
                        <p className="px-6 py-6 text-sm text-ink-muted">
                            {isAr ? 'لا توجد تحويلات إنستاباي معلقة حالياً.' : 'No pending InstaPay transfers right now.'}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-line text-caption text-ink-muted">
                                        <th scope="col" className="px-6 py-3 text-start font-medium">
                                            {isAr ? 'الطلب' : 'Order'}
                                        </th>
                                        <th scope="col" className="px-4 py-3 text-start font-medium">
                                            {isAr ? 'المرجع' : 'Reference'}
                                        </th>
                                        <th scope="col" className="px-4 py-3 text-end font-medium">
                                            {isAr ? 'المبلغ' : 'Amount'}
                                        </th>
                                        <th scope="col" className="px-6 py-3 text-end font-medium">
                                            {isAr ? 'تأكيد' : 'Verify'}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {pendingInstaPay.map((p) => (
                                        <tr key={p.id} className="hover:bg-secondary/40">
                                            <td className="px-6 py-3">
                                                <span dir="ltr" className="block font-medium text-ink">
                                                    #{p.order_number ?? p.order_id}
                                                </span>
                                                <span className="text-xs text-ink-muted">{p.customer_name ?? formatDate(p.created_at, locale)}</span>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs text-ink-muted" dir="ltr">
                                                {p.external_reference ?? '—'}
                                            </td>
                                            <td className="tabular px-4 py-3 text-end font-medium text-ink">{formatPiasters(p.amount, locale)}</td>
                                            <td className="px-6 py-3 text-end">
                                                <Button size="sm" disabled={verifyingId === p.id} onClick={() => verifyInstaPay(p.id)}>
                                                    <CheckCircle2 className="h-3.5 w-3.5" aria-hidden />
                                                    {isAr ? 'تأكيد الدفع' : 'Verify'}
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

            <Card>
                <CardHeader
                    title={isAr ? 'أحدث المدفوعات' : 'Recent payments'}
                    action={<Badge tone="neutral">{formatNumber(recentPayments.length)}</Badge>}
                />
                <CardBody className="p-0">
                    {recentPayments.length === 0 ? (
                        <p className="px-6 py-6 text-sm text-ink-muted">
                            {isAr ? 'لا توجد معاملات دفع مسجلة بعد.' : 'No payment records yet.'}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-line text-caption text-ink-muted">
                                        <th scope="col" className="px-6 py-3 text-start font-medium">
                                            {isAr ? 'الطلب' : 'Order'}
                                        </th>
                                        <th scope="col" className="px-4 py-3 text-start font-medium">
                                            {isAr ? 'البوابة' : 'Gateway'}
                                        </th>
                                        <th scope="col" className="px-4 py-3 text-center font-medium">
                                            {isAr ? 'الحالة' : 'Status'}
                                        </th>
                                        <th scope="col" className="px-6 py-3 text-end font-medium">
                                            {isAr ? 'المبلغ' : 'Amount'}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {recentPayments.map((p) => (
                                        <tr key={p.id}>
                                            <td className="px-6 py-3">
                                                <span dir="ltr" className="block font-medium text-ink">
                                                    #{p.order_number ?? p.order_id}
                                                </span>
                                                <span className="text-xs text-ink-muted">{formatDate(p.created_at, locale, true)}</span>
                                            </td>
                                            <td className="px-4 py-3 capitalize text-ink">
                                                {p.gateway}{' '}
                                                <span className="text-xs text-ink-muted">({p.mode})</span>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge tone={paymentStatusTone[p.status] ?? 'neutral'}>{p.status}</Badge>
                                            </td>
                                            <td className="tabular px-6 py-3 text-end text-ink">{formatPiasters(p.amount, locale)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardBody>
            </Card>

            <Card>
                <CardHeader title={t('payments.safety')} />
                <CardBody>
                    <p className="flex items-start gap-3 text-sm text-ink-muted">
                        <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0 text-success" aria-hidden />
                        {t('payments.safety_body')}
                    </p>
                </CardBody>
            </Card>
        </SettingsLayout>
    );
}
