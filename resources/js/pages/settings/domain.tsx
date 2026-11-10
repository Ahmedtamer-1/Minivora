// Store address and custom domain with automatic HTTPS. FR-SEO-21. Preview.
import { usePage } from '@inertiajs/react';
import { CheckCircle2, Globe2, Lock } from 'lucide-react';
import { PlannedNotice, PreviewCard } from '@/components/planned';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/input';
import { SettingsLayout } from '@/layouts/settings-layout';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

export default function DomainSettings() {
    const t = useT();
    const { store, domain } = usePage<SharedProps>().props;
    return (
        <SettingsLayout title={t('domain.title')} description={t('domain.subtitle')}>
            <PlannedNotice requirements={['FR-SEO-21']} />
            <PreviewCard title={t('domain.free')} sample={false}>
                <p className="flex flex-wrap items-center gap-3 text-sm">
                    <Globe2 className="h-5 w-5 text-ink-muted" aria-hidden />
                    <span dir="ltr" className="font-medium">
                        {store?.subdomain}.{domain}
                    </span>
                    <Badge tone="success">
                        <Lock className="h-3.5 w-3.5" aria-hidden />
                        HTTPS
                    </Badge>
                </p>
            </PreviewCard>
            <PreviewCard title={t('domain.custom')} description={t('domain.custom_hint')}>
                <div className="flex flex-wrap items-end gap-3">
                    <Field label={t('domain.your_domain')} htmlFor="custom_domain" className="min-w-56 flex-1">
                        <Input id="custom_domain" dir="ltr" placeholder="shop.example.com" disabled />
                    </Field>
                    <Button disabled>{t('domain.connect')}</Button>
                </div>
                <ol className="mt-5 space-y-2 text-sm text-ink-muted">
                    {['step1', 'step2', 'step3'].map((s) => (
                        <li key={s} className="flex items-start gap-2">
                            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
                            {t(`domain.${s}`)}
                        </li>
                    ))}
                </ol>
            </PreviewCard>
        </SettingsLayout>
    );
}
