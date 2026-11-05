// Accept a store invitation. FR-ACC-04
import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button, ButtonLink } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/input';
import { AuthLayout } from '@/layouts/auth-layout';
import { useT } from '@/lib/i18n';
import type { Role, SharedProps } from '@/types';

type Props =
    | { valid: false }
    | { valid: true; token: string; email: string; role: Role; storeName: string; hasAccount: boolean };

export default function Invitation(props: Props) {
    const t = useT();
    const { auth, errors: pageErrors } = usePage<SharedProps>().props;
    const { data, setData, post, processing, errors } = useForm({ name: '', password: '' });

    if (!props.valid) {
        return (
            <AuthLayout title={t('invite.invalid_title')}>
                <h1 className="text-2xl font-semibold">{t('invite.invalid_title')}</h1>
                <p className="mt-2 text-ink-muted">{t('invite.invalid_body')}</p>
                <Link href="/login" className="mt-6 inline-block font-medium text-ink-link hover:underline">
                    {t('login.title')}
                </Link>
            </AuthLayout>
        );
    }

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/invitations/${props.token}`);
    };

    const needsAccount = !auth.user && !props.hasAccount;

    return (
        <AuthLayout title={t('invite.title', { store: props.storeName })}>
            <h1 className="text-2xl font-semibold">{t('invite.title', { store: props.storeName })}</h1>
            <p className="mb-6 mt-1 text-ink-muted">{t('invite.body', { role: t(`roles.${props.role}`) })}</p>
            <p className="mb-4 rounded-md bg-surface px-3 py-2 text-sm" dir="ltr">
                {props.email}
            </p>

            {!auth.user && props.hasAccount ? (
                <ButtonLink href="/login" size="lg" className="w-full">
                    {t('invite.sign_in_first')}
                </ButtonLink>
            ) : (
                <form onSubmit={submit} className="space-y-4" noValidate>
                    {needsAccount ? (
                        <>
                            <Field label={t('invite.name')} htmlFor="name" error={errors.name}>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoComplete="name" required invalid={!!errors.name} />
                            </Field>
                            <Field label={t('create.password')} htmlFor="password" error={errors.password} hint={t('create.password_hint')}>
                                <Input id="password" type="password" dir="ltr" value={data.password} onChange={(e) => setData('password', e.target.value)} autoComplete="new-password" required invalid={!!errors.password} />
                            </Field>
                        </>
                    ) : null}
                    {pageErrors.invitation ? <p className="text-sm text-danger">{pageErrors.invitation}</p> : null}
                    <Button type="submit" size="lg" className="w-full" disabled={processing}>
                        {t('invite.accept')}
                    </Button>
                </form>
            )}
        </AuthLayout>
    );
}
