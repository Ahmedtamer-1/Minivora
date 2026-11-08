// Dashboard home: onboarding questions (FR-ONB-04), setup checklist (FR-ONB-10), store preview.
// The AI "Today" feed (FR-PRO-04) and KPIs (FR-ANA-02) arrive here with their modules.
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Bot, Check, Copy, ExternalLink } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StoreStatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button, ButtonLink } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { GlassPanel } from '@/components/ui/glass-panel';
import { Field, Input, Select, Textarea } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';
import { useCan } from '@/lib/permissions';
import type { SharedProps } from '@/types';

interface Profile {
    sells: string | null;
    location: string | null;
    language: 'ar' | 'en' | null;
}

interface Step {
    key: string;
    complete: boolean;
    available: boolean;
    route: string | null;
}

interface Props {
    showOnboarding: boolean;
    profile: Profile;
    checklist: { steps: Step[]; done: number; total: number; next: string | null };
    previewUrl: string;
}

const stepHref: Record<string, string> = {
    'verification.notice': '/email/verify',
    'settings.general': '/settings',
};

function OnboardingQuestions({ profile }: { profile: Profile }) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const { data, setData, post, processing, errors } = useForm({
        sells: profile.sells ?? '',
        location: profile.location ?? '',
        language: profile.language ?? locale,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/onboarding', { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader title={t('onboarding.title')} description={t('onboarding.subtitle')} />
            <CardBody>
                <form onSubmit={submit} className="space-y-4">
                    <Field label={t('onboarding.sells')} htmlFor="sells" error={errors.sells}>
                        <Textarea id="sells" rows={2} value={data.sells} onChange={(e) => setData('sells', e.target.value)} placeholder={t('onboarding.sells_ph')} maxLength={500} />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('onboarding.location')} htmlFor="location" error={errors.location}>
                            <Input id="location" value={data.location} onChange={(e) => setData('location', e.target.value)} placeholder={t('onboarding.location_ph')} maxLength={100} />
                        </Field>
                        <Field label={t('onboarding.language')} htmlFor="language" error={errors.language}>
                            <Select id="language" value={data.language} onChange={(e) => setData('language', e.target.value as 'ar' | 'en')}>
                                <option value="ar">{t('common.arabic')}</option>
                                <option value="en">{t('common.english')}</option>
                            </Select>
                        </Field>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={processing}>
                            {t('onboarding.save')}
                        </Button>
                        <Button variant="ghost" onClick={() => router.post('/onboarding/skip')} disabled={processing}>
                            {t('onboarding.skip')}
                        </Button>
                    </div>
                </form>
            </CardBody>
        </Card>
    );
}

function Checklist({ checklist }: { checklist: Props['checklist'] }) {
    const t = useT();
    const pct = Math.round((checklist.done / checklist.total) * 100);

    return (
        <Card>
            <CardHeader title={t('checklist.title')} description={t('checklist.progress', { done: checklist.done, total: checklist.total })} />
            <div className="px-5 pt-4">
                <div className="h-1.5 overflow-hidden rounded-full bg-surface" role="progressbar" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100}>
                    <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${pct}%` }} />
                </div>
            </div>
            <ul className="divide-y divide-line px-5 py-2">
                {checklist.steps.map((step) => {
                    const isNext = step.key === checklist.next;
                    const href = step.route ? stepHref[step.route] : undefined;
                    return (
                        <li key={step.key} className="flex items-center gap-3 py-3">
                            <span
                                className={cn(
                                    'grid h-6 w-6 shrink-0 place-items-center rounded-full border',
                                    step.complete ? 'border-success bg-success text-elevated' : isNext ? 'border-primary' : 'border-line',
                                )}
                                aria-hidden
                            >
                                {step.complete ? <Check className="h-3.5 w-3.5" /> : null}
                            </span>
                            <span className={cn('flex-1 text-sm', step.complete && 'text-ink-muted line-through', !step.available && 'text-ink-muted')}>
                                {t(`checklist.${step.key}`)}
                            </span>
                            {!step.available ? (
                                <Badge>{t('common.soon')}</Badge>
                            ) : !step.complete && href ? (
                                <ButtonLink href={href} variant={isNext ? 'primary' : 'secondary'} size="sm">
                                    {t('checklist.go')}
                                </ButtonLink>
                            ) : null}
                        </li>
                    );
                })}
            </ul>
        </Card>
    );
}

function StoreCard() {
    const t = useT();
    const { store } = usePage<SharedProps>().props;
    const [copied, setCopied] = useState(false);
    if (!store) return null;

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(store.url);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // Clipboard blocked; the address is still visible to copy by hand.
        }
    };

    return (
        <div className="aurora overflow-hidden rounded-lg border border-line">
            <GlassPanel className="m-4 p-5">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-xs font-semibold uppercase tracking-wider text-ink-muted">{t('home.address')}</p>
                    <StoreStatusBadge status={store.status} />
                </div>
                <p className="mt-2 break-all font-display text-lg font-semibold" dir="ltr">
                    {store.url.replace(/^https?:\/\//, '')}
                </p>
                <div className="mt-4 flex flex-wrap gap-2">
                    <a href="/preview" target="_blank" rel="noopener" className="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-fg hover:bg-primary-hover">
                        <ExternalLink className="h-4 w-4" aria-hidden />
                        {t('home.preview')}
                    </a>
                    <Button variant="secondary" size="sm" onClick={copy}>
                        {copied ? <Check className="h-4 w-4" aria-hidden /> : <Copy className="h-4 w-4" aria-hidden />}
                        {copied ? t('common.copied') : t('common.copy')}
                    </Button>
                </div>
            </GlassPanel>
        </div>
    );
}

function AiTeaser() {
    const t = useT();
    return (
        <Card className="border-dashed">
            <CardBody className="flex gap-3">
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-accent-subtle text-primary">
                    <Bot className="h-5 w-5" aria-hidden />
                </span>
                <div>
                    <p className="font-medium">{t('home.ai_title')}</p>
                    <p className="mt-1 text-sm text-ink-muted">{t('home.ai_body')}</p>
                </div>
            </CardBody>
        </Card>
    );
}

export default function Today({ showOnboarding, profile, checklist }: Props) {
    const t = useT();
    const can = useCan();
    const { store } = usePage<SharedProps>().props;

    return (
        <DashboardLayout title={t('nav.home')}>
            <PageHeader title={t('home.greeting', { store: store?.name ?? '' })} description={t('home.subtitle')} />
            <div className="grid gap-6 lg:grid-cols-5">
                <div className="space-y-6 lg:col-span-3">
                    {showOnboarding && can('design.manage') ? <OnboardingQuestions profile={profile} /> : null}
                    <Checklist checklist={checklist} />
                </div>
                <div className="space-y-6 lg:col-span-2">
                    <StoreCard />
                    <AiTeaser />
                    {can('members.manage') ? (
                        <Link href="/settings/members" className="block text-sm text-ink-link hover:underline">
                            {t('members.subtitle')}
                        </Link>
                    ) : null}
                </div>
            </div>
        </DashboardLayout>
    );
}
