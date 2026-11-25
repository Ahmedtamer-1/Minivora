// Notifications & Alerts: live dashboard alerts with mark-read and diagnostic test email. FR-NTF-03, FR-NTF-04
import { router, usePage } from '@inertiajs/react';
import { BellRing, Check, Mail, Send } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input } from '@/components/ui/input';
import { SettingsLayout } from '@/layouts/settings-layout';
import { formatDate, formatNumber, useLocale, useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

interface AlertRow {
    id: number;
    type: string;
    severity: 'info' | 'warning' | 'critical';
    title: string;
    body: string;
    action_url: string | null;
    read_at: string | null;
    created_at: string;
}

interface Props {
    alerts?: AlertRow[];
}

const severityTone: Record<AlertRow['severity'], Tone> = {
    info: 'info',
    warning: 'warning',
    critical: 'danger',
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export default function NotificationSettings({ alerts = [] }: Props) {
    const t = useT();
    const locale = useLocale();
    const isAr = locale === 'ar';
    const { auth } = usePage<SharedProps>().props;

    const [email, setEmail] = useState<string>(auth.user?.email ?? '');
    const [sending, setSending] = useState(false);
    const [markingId, setMarkingId] = useState<number | null>(null);
    const [feedback, setFeedback] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);

    const unreadCount = alerts.filter((a) => !a.read_at).length;

    const markRead = async (id: number) => {
        setMarkingId(id);
        setFeedback(null);
        try {
            const res = await fetch(`/alerts/${id}/read`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            });
            if (!res.ok) {
                throw new Error(isAr ? 'تعذر تحديث التنبيه.' : 'Could not mark alert as read.');
            }
            router.reload();
        } catch (err) {
            setFeedback({ kind: 'error', text: err instanceof Error ? err.message : 'Error' });
        } finally {
            setMarkingId(null);
        }
    };

    const sendTestEmail = async (e: FormEvent) => {
        e.preventDefault();
        setSending(true);
        setFeedback(null);
        try {
            const res = await fetch('/settings/notifications/test-email', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ email: email.trim() || null }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error((data as { message?: string }).message ?? (isAr ? 'تعذر إرسال البريد التجريبي.' : 'Failed to send test email.'));
            }
            const recipient = (data as { to?: string }).to ?? email;
            setFeedback({
                kind: 'success',
                text: isAr ? `تم إرسال بريد تشخيصي إلى ${recipient}.` : `Diagnostic test email sent to ${recipient}.`,
            });
        } catch (err) {
            setFeedback({ kind: 'error', text: err instanceof Error ? err.message : 'Error' });
        } finally {
            setSending(false);
        }
    };

    return (
        <SettingsLayout title={t('notify.title')} description={t('notify.subtitle')}>
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
                <CardHeader
                    title={isAr ? 'تنبيهات المتجر' : 'Store alerts'}
                    description={isAr ? 'أحدث تنبيهات الطلبات والمخزون والموافقات.' : 'Recent order, stock, approval, and anomaly alerts.'}
                    action={
                        <Badge tone={unreadCount > 0 ? 'warning' : 'neutral'}>
                            <BellRing className="h-3.5 w-3.5" aria-hidden />
                            {formatNumber(unreadCount)} {isAr ? 'غير مقروء' : 'unread'}
                        </Badge>
                    }
                />
                <CardBody>
                    {alerts.length === 0 ? (
                        <p className="py-4 text-sm text-ink-muted">
                            {isAr ? 'لا توجد تنبيهات حالياً — كل شيء يسير بسلاسة.' : 'No alerts yet — everything looks quiet.'}
                        </p>
                    ) : (
                        <ul className="divide-y divide-line">
                            {alerts.map((alert) => {
                                const unread = alert.read_at === null;
                                return (
                                    <li key={alert.id} className="flex flex-wrap items-start justify-between gap-3 py-3.5">
                                        <div className="min-w-0 flex-1 space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge tone={severityTone[alert.severity] ?? 'info'}>{alert.severity}</Badge>
                                                <span className={`text-sm ${unread ? 'font-semibold text-ink' : 'font-medium text-ink-muted'}`}>
                                                    {alert.title}
                                                </span>
                                                <span className="text-xs text-ink-muted">{formatDate(alert.created_at, locale, true)}</span>
                                            </div>
                                            <p className="text-sm text-ink-muted">{alert.body}</p>
                                        </div>
                                        {unread ? (
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                disabled={markingId === alert.id}
                                                onClick={() => markRead(alert.id)}
                                            >
                                                <Check className="h-3.5 w-3.5" aria-hidden />
                                                {isAr ? 'تمت القراءة' : 'Mark read'}
                                            </Button>
                                        ) : (
                                            <Badge tone="neutral">{isAr ? 'مقروء' : 'Read'}</Badge>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </CardBody>
            </Card>

            <Card>
                <CardHeader
                    title={isAr ? 'اختبار البريد الإلكتروني' : 'Send test diagnostic email'}
                    description={
                        isAr
                            ? 'تأكد من وصول إشعارات المتجر إلى بريدك الإلكتروني بنجاح.'
                            : 'Verify that store notification emails are delivered properly.'
                    }
                />
                <CardBody>
                    <form onSubmit={sendTestEmail} className="flex flex-wrap items-end gap-3">
                        <Field label={isAr ? 'البريد الإلكتروني المستلم' : 'Recipient email'} htmlFor="diag_email" className="min-w-56 flex-1">
                            <Input
                                id="diag_email"
                                type="email"
                                dir="ltr"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="owner@example.com"
                            />
                        </Field>
                        <Button type="submit" disabled={sending}>
                            {sending ? <Mail className="h-4 w-4" aria-hidden /> : <Send className="h-4 w-4" aria-hidden />}
                            {sending ? (isAr ? 'جاري الإرسال...' : 'Sending...') : isAr ? 'إرسال بريد تجريبي' : 'Send test email'}
                        </Button>
                    </form>
                </CardBody>
            </Card>
        </SettingsLayout>
    );
}
