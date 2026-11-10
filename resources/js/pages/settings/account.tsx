// Your own account: name, email, dashboard language and password. Open to every member.
import { useForm } from '@inertiajs/react';
import { KeyRound, MailCheck, MailWarning } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/input';
import { SettingsLayout } from '@/layouts/settings-layout';
import { useT } from '@/lib/i18n';

interface Props {
    account: { name: string; email: string; locale: 'ar' | 'en'; email_verified: boolean };
}

export default function AccountSettings({ account }: Props) {
    const t = useT();
    const profile = useForm({ name: account.name, email: account.email, locale: account.locale });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    const saveProfile = (e: FormEvent) => {
        e.preventDefault();
        profile.put('/settings/account', { preserveScroll: true });
    };

    const savePassword = (e: FormEvent) => {
        e.preventDefault();
        password.put('/settings/account/password', { preserveScroll: true, onSuccess: () => password.reset() });
    };

    return (
        <SettingsLayout title={t('account.title')} description={t('account.subtitle')}>
                <Card>
                    <CardHeader
                        title={t('account.profile')}
                        action={
                            account.email_verified ? (
                                <Badge tone="success">
                                    <MailCheck className="h-3.5 w-3.5" aria-hidden />
                                    {t('account.verified')}
                                </Badge>
                            ) : (
                                <Badge tone="warning">
                                    <MailWarning className="h-3.5 w-3.5" aria-hidden />
                                    {t('account.unverified')}
                                </Badge>
                            )
                        }
                    />
                    <CardBody>
                        <form onSubmit={saveProfile} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('account.name')} htmlFor="name" error={profile.errors.name}>
                                    <Input id="name" autoComplete="name" value={profile.data.name} onChange={(e) => profile.setData('name', e.target.value)} required maxLength={255} />
                                </Field>
                                <Field label={t('account.language')} htmlFor="locale" error={profile.errors.locale}>
                                    <Select id="locale" value={profile.data.locale} onChange={(e) => profile.setData('locale', e.target.value as 'ar' | 'en')}>
                                        <option value="ar">{t('common.arabic')}</option>
                                        <option value="en">{t('common.english')}</option>
                                    </Select>
                                </Field>
                            </div>
                            <Field label={t('account.email')} htmlFor="email" error={profile.errors.email} hint={t('account.email_hint')}>
                                <Input id="email" type="email" dir="ltr" autoComplete="email" value={profile.data.email} onChange={(e) => profile.setData('email', e.target.value)} required />
                            </Field>
                            <Button type="submit" disabled={profile.processing || !profile.isDirty}>
                                {profile.processing ? t('common.saving') : t('common.save')}
                            </Button>
                        </form>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title={t('account.password')} description={t('account.password_hint')} />
                    <CardBody>
                        <form onSubmit={savePassword} className="space-y-4">
                            <Field label={t('account.current_password')} htmlFor="current_password" error={password.errors.current_password}>
                                <Input
                                    id="current_password"
                                    type="password"
                                    dir="ltr"
                                    autoComplete="current-password"
                                    value={password.data.current_password}
                                    onChange={(e) => password.setData('current_password', e.target.value)}
                                    required
                                />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('account.new_password')} htmlFor="password" error={password.errors.password}>
                                    <Input id="password" type="password" dir="ltr" autoComplete="new-password" minLength={8} value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} required />
                                </Field>
                                <Field label={t('account.confirm_password')} htmlFor="password_confirmation">
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        dir="ltr"
                                        autoComplete="new-password"
                                        minLength={8}
                                        value={password.data.password_confirmation}
                                        onChange={(e) => password.setData('password_confirmation', e.target.value)}
                                        required
                                    />
                                </Field>
                            </div>
                            <Button type="submit" variant="secondary" disabled={password.processing || password.data.password.length < 8}>
                                <KeyRound className="h-4 w-4" aria-hidden />
                                {t('account.change_password')}
                            </Button>
                        </form>
                    </CardBody>
                </Card>
        </SettingsLayout>
    );
}
