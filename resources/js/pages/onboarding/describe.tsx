// Onboarding 2 · Describe it. Saves the description on the store profile, then shows the build. FR-ONB-04
import { Link, useForm, usePage } from '@inertiajs/react';
import { Loader2, SendHorizontal, Sparkles } from 'lucide-react';
import type { FormEvent } from 'react';
import { StorePreview } from '@/components/onboarding/store-preview';
import { OnboardingLayout } from '@/layouts/onboarding-layout';
import { useT } from '@/lib/i18n';
import { draftFor, draftPalette } from '@/lib/onboarding';
import type { SharedProps } from '@/types';

export default function Describe({ description }: { description: string | null }) {
    const t = useT();
    const { store, domain, locale } = usePage<SharedProps>().props;
    const { data, setData, post, processing, errors } = useForm({ description: description ?? '' });
    const examples = [t('flow.example_1'), t('flow.example_2'), t('flow.example_3')];

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/onboarding/describe');
    };

    return (
        <OnboardingLayout title={t('flow.describe_title')} step={2}>
            <div className="mx-auto w-full max-w-2xl text-center">
                <h1 className="mv-rise font-display text-5xl font-bold tracking-[-0.03em] sm:text-6xl">{t('flow.describe_title')}</h1>
                <p className="mv-rise mx-auto mt-4 max-w-lg text-sm leading-relaxed text-ink-muted sm:text-base">{t('flow.describe_body')}</p>

                <form onSubmit={submit} className="mv-rise mv-rise-delay mt-8" noValidate>
                    <label htmlFor="description" className="sr-only">
                        {t('flow.describe_label')}
                    </label>
                    <div className="glass-float flex h-16 items-center gap-3 rounded-full ps-6 pe-2 shadow-lg focus-within:ring-2 focus-within:ring-line-focus">
                        <Sparkles className="h-5 w-5 shrink-0 text-ink-accent" aria-hidden />
                        <input
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder={t('flow.describe_ph')}
                            maxLength={500}
                            autoFocus
                            autoComplete="off"
                            aria-invalid={!!errors.description || undefined}
                            aria-describedby={errors.description ? 'description-error' : undefined}
                            className="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-ink placeholder:text-ink-muted focus:ring-0 sm:text-lg"
                        />
                        <button
                            type="submit"
                            disabled={processing || data.description.trim() === ''}
                            className="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-primary text-primary-fg shadow-md transition-colors hover:bg-primary-hover disabled:bg-disabled disabled:text-disabled-fg"
                            aria-label={t('flow.describe_submit')}
                        >
                            {processing ? <Loader2 className="h-5 w-5 animate-spin" aria-hidden /> : <SendHorizontal className="h-5 w-5 rtl:-scale-x-100" aria-hidden />}
                        </button>
                    </div>
                    {errors.description ? (
                        <p id="description-error" className="mt-2 text-sm text-danger">
                            {errors.description}
                        </p>
                    ) : null}

                    <div className="mt-5 flex flex-wrap items-center justify-center gap-2">
                        <span className="text-caption text-ink-muted">{t('flow.or_try')}</span>
                        {examples.map((ex) => (
                            <button
                                key={ex}
                                type="button"
                                onClick={() => setData('description', ex)}
                                className="glass-strong rounded-full px-3.5 py-2 text-caption font-semibold text-ink shadow-sm transition-colors hover:bg-elevated"
                            >
                                {ex}
                            </button>
                        ))}
                    </div>
                </form>
            </div>

            {/* What's coming: an empty draft that fills in on the next screen. */}
            <div className="relative mx-auto mt-12 w-full max-w-3xl opacity-70 [mask-image:linear-gradient(to_bottom,black_40%,transparent)]">
                <StorePreview
                    draft={draftFor(data.description, locale)}
                    palette={draftPalette}
                    storeName={store?.name ?? ''}
                    address={`${store?.subdomain}.${domain}`}
                    progress={{ header: true, hero: false, products: 0 }}
                />
            </div>

            <div className="mt-4 text-center">
                <Link href="/onboarding/skip" method="post" as="button" className="rounded-full px-3 py-2 text-sm font-medium text-ink-link hover:underline">
                    {t('flow.skip')}
                </Link>
            </div>
        </OnboardingLayout>
    );
}
