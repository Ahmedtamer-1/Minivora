import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/input';
import { AuthLayout } from '@/layouts/auth-layout';
import { useT } from '@/lib/i18n';

export default function Login() {
    const t = useT();
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '', remember: true });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/login', { onFinish: () => setData('password', '') });
    };

    return (
        <AuthLayout title={t('login.title')}>
            <h1 className="text-2xl font-semibold">{t('login.title')}</h1>
            <p className="mb-6 mt-1 text-sm text-ink-muted">{t('login.subtitle')}</p>
            <form onSubmit={submit} className="space-y-4" noValidate>
                <Field label={t('create.email')} htmlFor="email" error={errors.email}>
                    <Input id="email" type="email" dir="ltr" value={data.email} onChange={(e) => setData('email', e.target.value)} autoComplete="email" autoFocus required invalid={!!errors.email} />
                </Field>
                <Field label={t('create.password')} htmlFor="password" error={errors.password}>
                    <Input id="password" type="password" dir="ltr" value={data.password} onChange={(e) => setData('password', e.target.value)} autoComplete="current-password" required />
                </Field>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} className="rounded border-line text-primary focus:ring-line-focus" />
                    {t('login.remember')}
                </label>
                <Button type="submit" size="lg" className="w-full" disabled={processing}>
                    {t('login.submit')}
                </Button>
            </form>
            <p className="mt-6 text-center text-sm text-ink-muted">
                {t('login.no_store')}{' '}
                <Link href="/" className="font-medium text-ink-link hover:underline">
                    {t('login.create')}
                </Link>
            </p>
        </AuthLayout>
    );
}
