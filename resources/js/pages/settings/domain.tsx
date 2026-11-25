// Domain & Maintenance: live storefront URL, custom domain guide, and maintenance mode settings. FR-SEO-21
import { useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, Globe2, Lock, Wrench } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Checkbox, Field, Textarea } from '@/components/ui/input';
import { SettingsLayout } from '@/layouts/settings-layout';
import { formatDate, useLocale, useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

interface DomainStoreProp {
    subdomain: string;
    domain?: string;
    url?: string;
    maintenance_mode?: boolean;
    maintenance_message?: string;
    published_at?: string | null;
}

interface Props {
    store?: DomainStoreProp;
}

export default function DomainSettings({ store: storeProp }: Props) {
    const t = useT();
    const locale = useLocale();
    const isAr = locale === 'ar';
    const shared = usePage<SharedProps>().props;

    const subdomain = storeProp?.subdomain ?? shared.store?.subdomain ?? '';
    const baseDomain = storeProp?.domain ?? shared.domain;
    const host = subdomain && baseDomain ? `${subdomain}.${baseDomain}` : '';
    const storefrontUrl = storeProp?.url ?? shared.store?.url ?? (host ? `https://${host}` : '');
    const publishedAt = storeProp?.published_at ?? null;

    const form = useForm({
        maintenance_mode: Boolean(storeProp?.maintenance_mode ?? false),
        maintenance_message: storeProp?.maintenance_message ?? '',
    });

    const saveMaintenance = (e: FormEvent) => {
        e.preventDefault();
        form.put('/settings/domain/maintenance', { preserveScroll: true });
    };

    return (
        <SettingsLayout title={t('domain.title')} description={t('domain.subtitle')}>
            <Card>
                <CardHeader
                    title={t('domain.free')}
                    action={
                        publishedAt ? (
                            <Badge tone="success">{isAr ? 'منشور' : 'Published'}</Badge>
                        ) : (
                            <Badge tone="warning">{isAr ? 'قريباً' : 'Opening soon'}</Badge>
                        )
                    }
                />
                <CardBody className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="flex flex-wrap items-center gap-3 text-sm">
                            <Globe2 className="h-5 w-5 text-ink-muted" aria-hidden />
                            <span dir="ltr" className="font-medium text-ink">
                                {host || '—'}
                            </span>
                            <Badge tone="success">
                                <Lock className="h-3.5 w-3.5" aria-hidden />
                                HTTPS
                            </Badge>
                        </p>
                        {storefrontUrl ? (
                            <a
                                href={storefrontUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1.5 text-sm font-medium text-ink-accent hover:underline"
                            >
                                {isAr ? 'فتح المتجر' : 'Open storefront'}
                                <ExternalLink className="h-4 w-4" aria-hidden />
                            </a>
                        ) : null}
                    </div>
                    {publishedAt ? (
                        <p className="text-xs text-ink-muted">
                            {isAr ? 'تاريخ النشر:' : 'Published on:'} {formatDate(publishedAt, locale, true)}
                        </p>
                    ) : null}
                </CardBody>
            </Card>

            <Card>
                <CardHeader
                    title={isAr ? 'وضع الصيانة' : 'Maintenance mode'}
                    description={
                        isAr
                            ? 'اعرض رسالة صيانة مؤقتة لزوار المتجر أثناء التحديثات.'
                            : 'Show a temporary maintenance screen to storefront visitors while you make updates.'
                    }
                    action={
                        <Badge tone={form.data.maintenance_mode ? 'warning' : 'success'}>
                            <Wrench className="h-3.5 w-3.5" aria-hidden />
                            {form.data.maintenance_mode ? (isAr ? 'مفعّل' : 'Active') : isAr ? 'متوقف' : 'Off'}
                        </Badge>
                    }
                />
                <CardBody>
                    <form onSubmit={saveMaintenance} className="space-y-4">
                        <Checkbox
                            id="maintenance_mode"
                            checked={form.data.maintenance_mode}
                            onChange={(e) => form.setData('maintenance_mode', e.target.checked)}
                            label={isAr ? 'تفعيل وضع الصيانة على واجهة المتجر' : 'Enable maintenance mode on storefront'}
                        />
                        <Field
                            label={isAr ? 'رسالة الصيانة للعملاء' : 'Maintenance message'}
                            htmlFor="maintenance_message"
                            error={form.errors.maintenance_message}
                            hint={
                                isAr
                                    ? 'تُعرض هذه الرسالة للزوار عند تفعيل وضع الصيانة.'
                                    : 'Displayed to shoppers while maintenance mode is enabled.'
                            }
                        >
                            <Textarea
                                id="maintenance_message"
                                rows={3}
                                value={form.data.maintenance_message}
                                onChange={(e) => form.setData('maintenance_message', e.target.value)}
                                placeholder={
                                    isAr
                                        ? 'نعمل حالياً على تحديث المتجر، سنعود قريباً.'
                                        : 'We are currently performing scheduled maintenance. Please check back soon.'
                                }
                                maxLength={500}
                            />
                        </Field>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? t('common.saving') : t('common.save')}
                        </Button>
                    </form>
                </CardBody>
            </Card>

            <Card>
                <CardHeader title={t('domain.custom')} description={t('domain.custom_hint')} />
                <CardBody>
                    <ol className="space-y-2 text-sm text-ink-muted">
                        {['step1', 'step2', 'step3'].map((s) => (
                            <li key={s} className="flex items-start gap-2">
                                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-ink-accent" aria-hidden />
                                {t(`domain.${s}`)}
                            </li>
                        ))}
                    </ol>
                </CardBody>
            </Card>
        </SettingsLayout>
    );
}
