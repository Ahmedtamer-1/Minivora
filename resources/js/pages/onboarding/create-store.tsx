// One-step "Create your store": store name, address, email, password. FR-ONB-01, FR-ONB-02, FR-ONB-08
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Check, CircleAlert, Loader2, Sparkles, Store, Zap } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/input';
import { AuthLayout } from '@/layouts/auth-layout';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { Card, CardBody } from '@/components/ui/card';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

type Mode = 'new-owner' | 'existing-owner';

interface CheckResult {
    subdomain: string;
    status: 'available' | 'taken' | 'reserved' | 'invalid';
    available: boolean;
    suggestions: string[];
}

function useAddressCheck(value: string) {
    const [result, setResult] = useState<CheckResult | null>(null);
    const [checking, setChecking] = useState(false);

    useEffect(() => {
        if (value.trim() === '') {
            setResult(null);
            return;
        }
        setChecking(true);
        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const res = await fetch(`/api/v1/subdomains/check?name=${encodeURIComponent(value)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (res.ok) setResult((await res.json()) as CheckResult);
            } catch {
                // Aborted or offline: the server validates again on submit.
            } finally {
                if (!controller.signal.aborted) setChecking(false);
            }
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [value]);

    return { result, checking };
}

/** Lowercase a-z, 0-9 and hyphens; the server does the full transliteration. */
function toAddress(name: string): string {
    return name
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 40);
}

function CreateStoreForm({ mode }: { mode: Mode }) {
    const t = useT();
    const { domain } = usePage<SharedProps>().props;
    const { data, setData, post, processing, errors } = useForm({
        store_name: '',
        subdomain: '',
        email: '',
        password: '',
    });
    const addressEdited = useRef(false);
    const { result, checking } = useAddressCheck(data.subdomain);

    const onName = (value: string) => {
        setData((d) => ({ ...d, store_name: value, subdomain: addressEdited.current ? d.subdomain : toAddress(value) }));
    };

    // Adopt the server's normalized form (e.g. Arabic names transliterated).
    useEffect(() => {
        if (result && result.subdomain !== '' && result.subdomain !== data.subdomain && !addressEdited.current) {
            setData('subdomain', result.subdomain);
        }
    }, [result]); // eslint-disable-line react-hooks/exhaustive-deps

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(mode === 'new-owner' ? '/create-store' : '/stores');
    };

    const statusLine = () => {
        if (errors.subdomain) return null;
        if (checking) {
            return (
                <span className="inline-flex items-center gap-1 text-ink-muted">
                    <Loader2 className="h-3.5 w-3.5 animate-spin" aria-hidden /> {t('create.checking')}
                </span>
            );
        }
        if (!result) return null;
        if (result.available) {
            return (
                <span className="inline-flex items-center gap-1 text-success">
                    <Check className="h-3.5 w-3.5" aria-hidden /> {t('create.available')}
                </span>
            );
        }
        return (
            <span className="text-danger">
                <span className="inline-flex items-center gap-1">
                    <CircleAlert className="h-3.5 w-3.5" aria-hidden />
                    {t(result.status === 'invalid' ? 'create.invalid' : `create.${result.status}`)}
                </span>
                {result.suggestions.length > 0 ? (
                    <span className="mt-2 flex flex-wrap gap-1.5">
                        {result.suggestions.map((s) => (
                            <button
                                key={s}
                                type="button"
                                onClick={() => {
                                    addressEdited.current = true;
                                    setData('subdomain', s);
                                }}
                                className="rounded-full border border-line bg-elevated px-2.5 py-1 text-xs text-ink hover:border-line-focus"
                                dir="ltr"
                            >
                                {s}
                            </button>
                        ))}
                    </span>
                ) : null}
            </span>
        );
    };

    return (
        <form onSubmit={submit} className="space-y-4" noValidate>
            <Field label={t('create.store_name')} htmlFor="store_name" error={errors.store_name}>
                <Input
                    id="store_name"
                    value={data.store_name}
                    onChange={(e) => onName(e.target.value)}
                    placeholder={t('create.store_name_ph')}
                    autoComplete="organization"
                    autoFocus
                    required
                    invalid={!!errors.store_name}
                />
            </Field>

            <Field label={t('create.address')} htmlFor="subdomain" error={errors.subdomain}>
                <div
                    dir="ltr"
                    className={cn(
                        'flex h-10 items-center overflow-hidden rounded-md border bg-elevated shadow-sm focus-within:border-line-focus focus-within:ring-1 focus-within:ring-line-focus',
                        errors.subdomain || (result && !result.available) ? 'border-danger' : 'border-line',
                    )}
                >
                    <input
                        id="subdomain"
                        value={data.subdomain}
                        onChange={(e) => {
                            addressEdited.current = true;
                            setData('subdomain', e.target.value.toLowerCase());
                        }}
                        className="h-full min-w-0 flex-1 border-0 bg-transparent px-3 text-ink focus:ring-0"
                        autoComplete="off"
                        spellCheck={false}
                        required
                        aria-describedby="subdomain-status"
                    />
                    <span className="shrink-0 border-s border-line bg-surface px-3 text-sm leading-10 text-ink-muted">.{domain}</span>
                </div>
                <div id="subdomain-status" className="min-h-5 text-xs" aria-live="polite">
                    {statusLine()}
                </div>
            </Field>

            {mode === 'new-owner' ? (
                <>
                    <Field label={t('create.email')} htmlFor="email" error={errors.email}>
                        <Input id="email" type="email" dir="ltr" value={data.email} onChange={(e) => setData('email', e.target.value)} autoComplete="email" required invalid={!!errors.email} />
                    </Field>
                    <Field label={t('create.password')} htmlFor="password" error={errors.password} hint={t('create.password_hint')}>
                        <Input
                            id="password"
                            type="password"
                            dir="ltr"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="new-password"
                            required
                            invalid={!!errors.password}
                        />
                    </Field>
                </>
            ) : null}

            <Button type="submit" size="lg" className="w-full" disabled={processing || (result !== null && !result.available)}>
                {processing ? (
                    <>
                        <Loader2 className="h-4 w-4 animate-spin" aria-hidden /> {t('create.submitting')}
                    </>
                ) : (
                    t('create.submit')
                )}
            </Button>
        </form>
    );
}

function Pitch() {
    const t = useT();
    const points = [
        { icon: Zap, text: t('create.points.one') },
        { icon: Store, text: t('create.points.two') },
        { icon: Sparkles, text: t('create.points.three') },
    ];

    return (
        <div className="max-w-lg">
            <h1 className="text-4xl font-semibold leading-tight xl:text-5xl">{t('app.tagline')}</h1>
            <ul className="mt-8 space-y-4">
                {points.map(({ icon: Icon, text }) => (
                    <li key={text} className="flex items-start gap-3 text-lg">
                        <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-md bg-elevated text-primary shadow-sm">
                            <Icon className="h-4 w-4" aria-hidden />
                        </span>
                        <span>{text}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function CreateStore({ mode }: { mode: Mode }) {
    const t = useT();

    if (mode === 'existing-owner') {
        return (
            <DashboardLayout title={t('create.title_another')}>
                <PageHeader title={t('create.title_another')} description={t('create.subtitle_another')} />
                <Card className="max-w-lg">
                    <CardBody className="py-6">
                        <CreateStoreForm mode={mode} />
                    </CardBody>
                </Card>
            </DashboardLayout>
        );
    }

    return (
        <AuthLayout title={t('create.title')} aside={<Pitch />}>
            <Head title={t('create.title')} />
            <h2 className="text-2xl font-semibold">{t('create.title')}</h2>
            <p className="mb-6 mt-1 text-sm text-ink-muted">{t('create.subtitle')}</p>
            <CreateStoreForm mode={mode} />
            <p className="mt-6 text-center text-sm text-ink-muted">
                {t('create.have_account')}{' '}
                <Link href="/login" className="font-medium text-ink-link hover:underline">
                    {t('create.sign_in')}
                </Link>
            </p>
        </AuthLayout>
    );
}
