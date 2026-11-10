// Store settings: name, language, visibility (publish), opening-soon password. FR-ONB-06, FR-ONB-07
import { router, useForm, usePage } from '@inertiajs/react';
import { Globe, Lock } from 'lucide-react';
import type { FormEvent } from 'react';
import { StoreStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/input';
import { SettingsLayout } from '@/layouts/settings-layout';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

interface Props {
    settings: {
        name: string;
        subdomain: string;
        default_locale: 'ar' | 'en';
        has_storefront_password: boolean;
        published_at: string | null;
    };
}

export default function Settings({ settings }: Props) {
    const t = useT();
    const { store, domain, errors: pageErrors } = usePage<SharedProps>().props;
    const general = useForm({ name: settings.name, default_locale: settings.default_locale });
    const password = useForm({ password: '' });

    const saveGeneral = (e: FormEvent) => {
        e.preventDefault();
        general.put('/settings/general', { preserveScroll: true });
    };

    const savePassword = (e: FormEvent) => {
        e.preventDefault();
        password.put('/settings/storefront-password', { preserveScroll: true, onSuccess: () => password.reset() });
    };

    const live = store?.status === 'live';

    return (
        <SettingsLayout title={t('settings.title')} description={t('settings.subtitle')}>
                <Card>
                    <CardHeader title={t('settings.visibility')} action={store ? <StoreStatusBadge status={store.status} /> : null} />
                    <CardBody className="flex flex-wrap items-center justify-between gap-4">
                        <p className="max-w-xl text-sm text-ink-muted">{live ? t('settings.live_body') : t('settings.opening_soon_body')}</p>
                        {live ? (
                            <Button variant="secondary" onClick={() => router.post('/settings/unpublish', {}, { preserveScroll: true })}>
                                {t('settings.unpublish')}
                            </Button>
                        ) : (
                            <Button onClick={() => router.post('/settings/publish', {}, { preserveScroll: true })} disabled={store?.status === 'suspended'}>
                                <Globe className="h-4 w-4" aria-hidden />
                                {t('settings.publish')}
                            </Button>
                        )}
                    </CardBody>
                    {pageErrors.publish ? <p className="px-5 pb-4 text-sm text-danger" role="alert">{pageErrors.publish}</p> : null}
                </Card>

                <Card>
                    <CardHeader title={t('settings.general')} />
                    <CardBody>
                        <form onSubmit={saveGeneral} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('settings.name')} htmlFor="name" error={general.errors.name}>
                                    <Input id="name" value={general.data.name} onChange={(e) => general.setData('name', e.target.value)} required maxLength={80} />
                                </Field>
                                <Field label={t('settings.language')} htmlFor="default_locale" error={general.errors.default_locale}>
                                    <Select id="default_locale" value={general.data.default_locale} onChange={(e) => general.setData('default_locale', e.target.value as 'ar' | 'en')}>
                                        <option value="ar">{t('common.arabic')}</option>
                                        <option value="en">{t('common.english')}</option>
                                    </Select>
                                </Field>
                            </div>
                            <Field label={t('create.address')} htmlFor="address" hint={t('settings.address_note')}>
                                <Input id="address" dir="ltr" value={`${settings.subdomain}.${domain}`} disabled readOnly />
                            </Field>
                            <Button type="submit" disabled={general.processing || !general.isDirty}>
                                {general.processing ? t('common.saving') : t('common.save')}
                            </Button>
                        </form>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title={t('settings.password')} description={t('settings.password_body')} />
                    <CardBody>
                        <p className="mb-4 flex items-center gap-2 text-sm">
                            <Lock className="h-4 w-4 text-ink-muted" aria-hidden />
                            {settings.has_storefront_password ? t('settings.password_set') : t('settings.password_none')}
                        </p>
                        <form onSubmit={savePassword} className="flex flex-wrap items-end gap-3">
                            <Field label={t('settings.password_new')} htmlFor="sf_password" error={password.errors.password} className="min-w-56 flex-1">
                                <Input id="sf_password" dir="ltr" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} autoComplete="off" minLength={4} maxLength={64} />
                            </Field>
                            <Button type="submit" disabled={password.processing || password.data.password.length < 4}>
                                {t('common.save')}
                            </Button>
                            {settings.has_storefront_password ? (
                                <Button variant="ghost" onClick={() => router.put('/settings/storefront-password', { password: null }, { preserveScroll: true })}>
                                    {t('settings.password_remove')}
                                </Button>
                            ) : null}
                        </form>
                    </CardBody>
                </Card>
        </SettingsLayout>
    );
}
