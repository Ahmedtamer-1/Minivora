// Payment methods: Kashier, Paymob, Cash on Delivery, InstaPay, Fawry. FR-PAY-04 to FR-PAY-09. Preview.
import { Banknote, CreditCard, Receipt, ShieldCheck, Smartphone, Wallet, type LucideIcon } from 'lucide-react';
import { PlannedNotice, PreviewCard } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SettingsLayout } from '@/layouts/settings-layout';
import { useT } from '@/lib/i18n';

const methods: Array<{ key: string; icon: LucideIcon; on: boolean; req: string }> = [
    { key: 'cod', icon: Banknote, on: true, req: 'FR-PAY-05' },
    { key: 'kashier', icon: CreditCard, on: true, req: 'FR-PAY-04' },
    { key: 'instapay', icon: Smartphone, on: false, req: 'FR-PAY-06' },
    { key: 'paymob', icon: Wallet, on: false, req: 'FR-PAY-07' },
    { key: 'fawry', icon: Receipt, on: false, req: 'FR-PAY-08' },
];

export default function PaymentSettings() {
    const t = useT();
    return (
        <SettingsLayout title={t('payments.title')} description={t('payments.subtitle')}>
            <PlannedNotice requirements={['FR-PAY-04', 'FR-PAY-05', 'FR-PAY-06', 'FR-PAY-09']} />
            <PreviewCard title={t('payments.methods')} description={t('payments.methods_hint')}>
                <ul className="divide-y divide-line">
                    {methods.map(({ key, icon: Icon, on, req }) => (
                        <li key={key} className="flex min-h-[68px] items-center gap-3 py-2">
                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                                <Icon className="h-5 w-5" aria-hidden strokeWidth={1.75} />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-medium">{t(`payments.m_${key}`)}</span>
                                <span className="block text-caption text-ink-muted">
                                    {t(`payments.m_${key}_hint`)}{' '}
                                    <span dir="ltr" className="font-mono text-xs">
                                        {req}
                                    </span>
                                </span>
                            </span>
                            {on ? (
                                <Badge tone="success">{t('payments.on')}</Badge>
                            ) : (
                                <Button size="sm" variant="secondary" disabled>
                                    {t('payments.set_up')}
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            </PreviewCard>
            <PreviewCard title={t('payments.safety')} sample={false}>
                <p className="flex items-start gap-3 text-sm text-ink-muted">
                    <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0 text-success" aria-hidden />
                    {t('payments.safety_body')}
                </p>
            </PreviewCard>
        </SettingsLayout>
    );
}
