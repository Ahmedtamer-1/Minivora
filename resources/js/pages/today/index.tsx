// Dashboard home (Figma "Home · HIG"): ask bar, sales hero, setup ring, "Needs you", orders, week,
// best seller, running low, visitors. Cards whose module has not shipped show honest empty states.
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowUpRight, Check, ChevronRight, FileText, ImageIcon, Maximize2, Package, SendHorizontal } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type CSSProperties, type FormEvent } from 'react';
import { AiOrb, openAi } from '@/components/ai-orb';
import { Badge } from '@/components/ui/badge';
import { Button, ButtonLink } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field, Input, Select, Textarea } from '@/components/ui/input';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import { StoreLine } from '@/layouts/topbar';
import { cn } from '@/lib/cn';
import { dayPart, formatNumber, useT } from '@/lib/i18n';
import { useAppeared, useCountUp, useTypedPlaceholder } from '@/lib/motion';
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

interface LowItem {
    id: number;
    name: string;
    stock: number;
    status: 'draft' | 'live';
}

interface Attention {
    key: string;
    kind: 'low' | 'sold_out' | 'drafts';
    product_id: number | null;
    name: string | null;
    count: number;
}

interface Props {
    showOnboarding: boolean;
    profile: Profile;
    checklist: { steps: Step[]; done: number; total: number; next: string | null };
    inventory: {
        total: number;
        live: number;
        drafts: number;
        running_low: LowItem[];
        low_count: number;
        sold_out_count: number;
        best_seller: { id: number; name: string; cover: string | null; sold: number; price: number } | null;
    };
    attention: Attention[];
    sales: { amount: number; orders: number; change: number | null } | null;
    toShip: { count: number; needs_call: number } | null;
    week: { amount: number; change: number | null; days: number[] } | null;
    visitors: { count: number; conversion: number; change: number | null; sources: Record<string, number> } | null;
}

const routeHref: Record<string, string> = {
    'verification.notice': '/email/verify',
    'settings.general': '/settings',
    'products.create': '/products/create',
};

const cardTitle = 'font-display text-base font-normal text-ink';

/* ---------- Ask your store ---------- */

function AskCard() {
    const t = useT();
    const can = useCan();
    const [text, setText] = useState('');
    const [busy, setBusy] = useState(false);
    const [focused, setFocused] = useState(false);
    const form = useRef<HTMLFormElement>(null);
    const placeholder = useTypedPlaceholder([t('ask.placeholder'), t('ai.s_restock'), t('ai.s_next'), t('ai.s_best')], !focused && text === '');

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const value = text.trim();
        if (!value) return;
        // "add …" goes straight to a product draft; everything else opens the AI team with the question.
        if (/^(add|please add|أضف|اضف|ضيف)\s+/iu.test(value) && can('products.manage')) {
            setBusy(true);
            router.post('/products', { note: value }, { onFinish: () => setBusy(false) });
            return;
        }
        openAi(form.current, value);
    };

    const suggestions = [
        can('products.manage') ? { label: t('ask.s_add'), href: '/products/create' } : null,
        can('products.view') ? { label: t('ask.s_restock'), href: '/products?filter=low' } : null,
        can('design.manage') ? { label: t('ask.s_publish'), href: '/settings' } : null,
    ].filter(Boolean) as { label: string; href: string }[];

    return (
        <Card className="flex flex-col p-6 md:col-span-2 xl:min-h-[272px]">
            <div className="flex items-center justify-between gap-3">
                <h2 className={cardTitle}>{t('ask.title')}</h2>
                <button
                    type="button"
                    onClick={() => openAi(form.current)}
                    className="group inline-flex h-[26px] items-center gap-1.5 rounded-full bg-secondary px-2.5 text-caption text-ink transition-colors hover:bg-elevated"
                >
                    {t('ask.badge')}
                    <Maximize2 className="h-3 w-3 transition-transform group-hover:scale-125" aria-hidden />
                </button>
            </div>
            <p className="mt-3 font-display text-[28px] font-light leading-[31px] tracking-[-0.02em]">{t('ask.question')}</p>
            <form ref={form} onSubmit={submit} className="mt-auto pt-6">
                <label htmlFor="ask" className="sr-only">
                    {t('ask.title')}
                </label>
                <div className="flex h-12 items-center gap-2 rounded-full bg-elevated pe-1 ps-2 shadow-md transition-shadow focus-within:shadow-lg">
                    <AiOrb className="h-8 w-8" />
                    <input
                        id="ask"
                        value={text}
                        onChange={(e) => setText(e.target.value)}
                        onFocus={() => setFocused(true)}
                        onBlur={() => setFocused(false)}
                        placeholder={focused ? t('ask.placeholder') : placeholder}
                        className="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-muted focus:ring-0"
                        maxLength={500}
                        autoComplete="off"
                    />
                    <button type="submit" disabled={busy || !text.trim()} className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary text-primary-fg transition-all hover:bg-primary-hover active:scale-90 disabled:opacity-60" aria-label={t('ask.send')}>
                        <SendHorizontal className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                    </button>
                </div>
            </form>
            {suggestions.length > 0 ? (
                <div className="mt-3 flex flex-wrap gap-2">
                    {suggestions.map((s) => (
                        <Link key={s.href} href={s.href} className="glass-strong inline-flex h-9 items-center rounded-full px-3.5 text-caption text-ink shadow-sm transition-transform hover:-translate-y-0.5 hover:bg-elevated">
                            {s.label}
                        </Link>
                    ))}
                </div>
            ) : null}
        </Card>
    );
}

/* ---------- Sales today (signature card) ---------- */

function SalesCard({ sales }: { sales: Props['sales'] }) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const amount = useCountUp(sales ? Math.round(sales.amount / 100) : 0);

    return (
        <section aria-labelledby="sales-title" className="hero-sales mv-hero-lights mv-shine relative isolate flex flex-col overflow-hidden rounded-2xl p-6 text-white shadow-glass xl:min-h-[272px]">
            <div className="flex items-center justify-between gap-3">
                <h2 id="sales-title" className="font-display text-base font-normal text-white">
                    {t('sales.title')}
                </h2>
                {sales?.change != null ? (
                    <span className="inline-flex h-[26px] items-center rounded-full bg-white px-2 text-caption text-[#0B1A26]">{`${sales.change > 0 ? '+' : ''}${sales.change}%`}</span>
                ) : null}
            </div>
            <div className="relative mt-auto pt-10">
                <p className="tabular font-display text-numeral">{formatNumber(Math.round(amount))}</p>
                <p className="mt-2 text-sm text-white">
                    {locale === 'ar' ? 'ج.م' : 'EGP'} · {sales ? t('sales.orders', { count: sales.orders }) : t('sales.empty')}
                </p>
            </div>
        </section>
    );
}

/* ---------- Store setup ring ---------- */

function SetupCard({ checklist }: { checklist: Props['checklist'] }) {
    const t = useT();
    const size = 128;
    const stroke = 8;
    const r = (size - stroke) / 2;
    const c = 2 * Math.PI * r;
    const pct = checklist.total ? checklist.done / checklist.total : 0;
    const next = checklist.steps.find((s) => s.key === checklist.next);
    const nextHref = next?.route ? routeHref[next.route] : undefined;
    const shown = useAppeared(250);

    return (
        <Card className="flex flex-col items-center p-6 xl:min-h-[272px]">
            <h2 className={cn(cardTitle, 'self-start')}>{t('setup.title')}</h2>
            <div className="relative mt-3" role="img" aria-label={t('checklist.progress', { done: checklist.done, total: checklist.total })}>
                <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="-rotate-90 rtl:rotate-90 rtl:-scale-y-100" aria-hidden>
                    <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="var(--mv-chart-track)" strokeWidth={stroke} />
                    <circle
                        cx={size / 2}
                        cy={size / 2}
                        r={r}
                        fill="none"
                        stroke="var(--mv-brand-accent)"
                        strokeWidth={stroke}
                        strokeLinecap="round"
                        strokeDasharray={c}
                        strokeDashoffset={c * (1 - (shown ? pct : 0))}
                        className="transition-[stroke-dashoffset] duration-[1400ms] ease-[cubic-bezier(0.22,1,0.36,1)]"
                    />
                </svg>
                <span className="tabular absolute inset-0 grid place-items-center font-display text-numeral-sm">
                    {checklist.done}/{checklist.total}
                </span>
            </div>
            <div className="mt-3 text-center">
                {next && nextHref ? (
                    <Link href={nextHref} className="inline-flex items-center gap-1 text-caption text-ink-link hover:underline">
                        {t('setup.next', { step: t(`checklist.${next.key}`) })}
                        <ChevronRight className="h-3.5 w-3.5 rtl:-scale-x-100" aria-hidden />
                    </Link>
                ) : checklist.done === checklist.total ? (
                    <p className="text-caption text-success">{t('setup.done')}</p>
                ) : (
                    <p className="text-caption text-ink-muted">{t('setup.waiting')}</p>
                )}
            </div>
            <details className="group mt-2 w-full text-caption">
                <summary className="mx-auto w-fit cursor-pointer list-none rounded-full px-2 py-1 text-ink-muted hover:text-ink">{t('setup.all_steps')}</summary>
                <ul className="mt-2 space-y-1.5">
                    {checklist.steps.map((s) => (
                        <li key={s.key} className="flex items-center gap-2">
                            <span className={cn('grid h-4 w-4 place-items-center rounded-full border', s.complete ? 'border-success bg-success text-elevated' : 'border-line-control')} aria-hidden>
                                {s.complete ? <Check className="h-3 w-3" /> : null}
                            </span>
                            <span className={cn(s.complete && 'text-ink-muted line-through')}>{t(`checklist.${s.key}`)}</span>
                            {!s.available ? <span className="ms-auto text-ink-muted">{t('common.soon')}</span> : null}
                            <span className="sr-only">{s.complete ? t('setup.step_done') : t('setup.step_todo')}</span>
                        </li>
                    ))}
                </ul>
            </details>
        </Card>
    );
}

/* ---------- Needs you ---------- */

const DISMISS_KEY = 'mv-dismissed';

function readDismissed(): string[] {
    try {
        return JSON.parse(localStorage.getItem(DISMISS_KEY) ?? '[]') as string[];
    } catch {
        return [];
    }
}

function NeedsYouCard({ attention }: { attention: Attention[] }) {
    const t = useT();
    const can = useCan();
    const [dismissed, setDismissed] = useState<string[]>([]);
    useEffect(() => setDismissed(readDismissed()), []);

    const items = attention.filter((a) => !dismissed.includes(a.key));
    const dismiss = (key: string) => {
        const next = [...dismissed, key].slice(-50);
        setDismissed(next);
        try {
            localStorage.setItem(DISMISS_KEY, JSON.stringify(next));
        } catch {
            // Storage blocked: hidden for this page view only.
        }
    };

    const view = (a: Attention) => {
        switch (a.kind) {
            case 'sold_out':
                return { icon: AlertTriangle, tint: 'bg-danger-subtle text-danger', text: t('needs.sold_out', { name: a.name ?? '' }), action: t('needs.restock'), href: `/products/${a.product_id}/edit` };
            case 'low':
                return { icon: Package, tint: 'bg-warning-subtle text-warning', text: t('needs.low', { name: a.name ?? '', count: a.count }), action: t('needs.restock'), href: `/products/${a.product_id}/edit` };
            default:
                return { icon: FileText, tint: 'bg-info-subtle text-info', text: t('needs.drafts', { count: a.count }), action: t('needs.review'), href: '/products?filter=draft' };
        }
    };

    return (
        <Card className="flex flex-col p-6 md:col-span-2 xl:min-h-[264px]">
            <div className="flex items-center justify-between gap-3">
                <h2 className={cardTitle}>{t('needs.title')}</h2>
                {items.length > 0 ? (
                    <Badge tone="highlight" className="min-w-[26px] justify-center">
                        <span className="sr-only">{t('needs.count', { count: items.length })}</span>
                        <span aria-hidden>{items.length}</span>
                    </Badge>
                ) : null}
            </div>
            {items.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center gap-2 py-6 text-center">
                    <span className="mv-float grid h-10 w-10 place-items-center rounded-full bg-success-subtle text-success">
                        <Check className="h-5 w-5" aria-hidden />
                    </span>
                    <p className="text-sm">{t('needs.empty')}</p>
                </div>
            ) : (
                <ul className="mt-4 space-y-3">
                    {items.map((a, i) => {
                        const v = view(a);
                        const Icon = v.icon;
                        return (
                            <li key={a.key} style={{ animationDelay: `${350 + i * 90}ms` }} className="mv-pop flex flex-wrap items-center gap-3 rounded-2xl bg-elevated py-2 pe-2 ps-2 shadow-sm sm:flex-nowrap">
                                <span className={cn('grid h-9 w-9 shrink-0 place-items-center rounded-full', v.tint)}>
                                    <Icon className="h-4 w-4" aria-hidden />
                                </span>
                                <p className="min-w-0 flex-1 basis-[calc(100%-3rem)] text-sm sm:basis-auto">{v.text}</p>
                                <div className="ms-auto flex shrink-0 items-center gap-1">
                                    <Button variant="ghost" size="sm" onClick={() => dismiss(a.key)}>
                                        {t('needs.not_now')}
                                    </Button>
                                    {can('products.manage') || a.kind === 'drafts' ? (
                                        <ButtonLink href={v.href} size="sm">
                                            {v.action}
                                        </ButtonLink>
                                    ) : null}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Card>
    );
}

/* ---------- To ship ---------- */

function ToShipCard({ toShip }: { toShip: Props['toShip'] }) {
    const t = useT();
    const count = useCountUp(toShip?.count ?? 0, 900);
    return (
        <Link href="/orders?filter=to_ship" className="card group flex flex-col p-6 transition-transform hover:-translate-y-0.5 focus-visible:-translate-y-0.5 xl:min-h-[264px]">
            <div className="flex items-center justify-between gap-3">
                <h2 className={cardTitle}>{t('ship.title')}</h2>
                <ArrowUpRight className="h-[18px] w-[18px] text-ink-muted transition-transform duration-300 group-hover:-translate-y-1 rtl:-scale-x-100" aria-hidden />
            </div>
            <div className="mt-auto pt-8">
                <p className="tabular font-display text-numeral">{Math.round(count)}</p>
                <p className={cn('mt-2 text-sm', toShip?.needs_call ? 'text-danger' : 'text-ink-muted')}>
                    {toShip?.needs_call ? t('ship.needs_call', { count: toShip.needs_call }) : t('ship.empty')}
                </p>
            </div>
        </Link>
    );
}

/* ---------- This week ---------- */

function WeekCard({ week }: { week: Props['week'] }) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const days = week?.days ?? Array.from({ length: 20 }, () => 0);
    const max = Math.max(1, ...days);
    const grown = useAppeared(300);
    const amount = useCountUp(week ? Math.round(week.amount / 100) : 0);

    return (
        <Card className="flex flex-col p-6 xl:min-h-[264px]">
            <div className="flex items-center justify-between gap-3">
                <h2 className={cardTitle}>{t('week.title')}</h2>
                {week?.change != null ? <Badge tone="highlight">{`${week.change > 0 ? '+' : ''}${week.change}%`}</Badge> : null}
            </div>
            <div className="mt-auto pt-6">
                <p className="tabular font-display text-numeral-sm">{formatNumber(Math.round(amount))}</p>
                <p className="mt-1 text-caption text-ink-muted">{t('week.sub', { currency: locale === 'ar' ? 'ج.م' : 'EGP' })}</p>
                {/* Bars grow in one after another; with no data yet they breathe in a slow wave. */}
                <div className={cn('mt-4 flex h-14 items-end gap-[4px]', !week && 'mv-wave')} aria-hidden>
                    {days.map((d, i) => (
                        <span
                            key={i}
                            className={cn(
                                'flex-1 origin-bottom rounded-full transition-transform duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                                week && i >= days.length - 4 ? 'bg-brand-accent' : 'bg-chart-track',
                            )}
                            style={
                                week
                                    ? { height: `${Math.max(12, (d / max) * 100)}%`, transform: `scaleY(${grown ? 1 : 0})`, transitionDelay: `${i * 30}ms` }
                                    : ({ height: '40%', '--i': i } as CSSProperties)
                            }
                        />
                    ))}
                </div>
                {!week ? <p className="mt-2 text-caption text-ink-muted">{t('week.empty')}</p> : null}
            </div>
        </Card>
    );
}

/* ---------- Best seller ---------- */

function BestSellerCard({ best, total }: { best: Props['inventory']['best_seller']; total: number }) {
    const t = useT();
    const can = useCan();
    return (
        <Card className="group flex flex-col p-6 xl:min-h-[296px]">
            <h2 className={cardTitle}>{t('best.title')}</h2>
            <div className="mv-shine relative mt-4 aspect-[258/140] overflow-hidden rounded-2xl bg-accent-subtle">
                {best?.cover ? (
                    <img src={best.cover} alt="" className="h-full w-full object-cover transition-transform duration-[1200ms] ease-out group-hover:scale-[1.06]" />
                ) : (
                    <div className="mv-pan grid h-full w-full place-items-center" style={{ background: 'radial-gradient(60% 80% at 35% 70%, color-mix(in srgb, var(--mv-brand-accent) 55%, transparent), transparent 70%), radial-gradient(50% 70% at 85% 20%, color-mix(in srgb, var(--mv-brand-highlight) 60%, transparent), transparent 70%)' }}>
                        <ImageIcon className="mv-float h-7 w-7 text-ink" aria-hidden strokeWidth={1.5} />
                    </div>
                )}
            </div>
            <div className="mt-auto pt-4">
                {best ? (
                    <>
                        <p className="text-sm font-semibold">{best.name}</p>
                        <p className="mt-1 text-caption text-ink-muted">{t('best.sold', { count: best.sold })}</p>
                    </>
                ) : total === 0 && can('products.manage') ? (
                    <Link href="/products/create" className="inline-flex items-center gap-1 text-caption text-ink-link hover:underline">
                        {t('best.add_first')}
                        <ChevronRight className="h-3.5 w-3.5 rtl:-scale-x-100" aria-hidden />
                    </Link>
                ) : (
                    <p className="text-caption text-ink-muted">{t('best.empty')}</p>
                )}
            </div>
        </Card>
    );
}

/* ---------- Running low ---------- */

const toneText = { danger: 'text-danger', warning: 'text-warning', success: 'text-success' } as const;
const toneBg = { danger: 'bg-danger', warning: 'bg-warning', success: 'bg-success' } as const;

function RunningLowCard({ items }: { items: LowItem[] }) {
    const t = useT();
    const grown = useAppeared(400);
    return (
        <Card className="flex flex-col p-6 xl:min-h-[296px]">
            <div className="flex items-center justify-between gap-3">
                <h2 className={cardTitle}>{t('low.title')}</h2>
                {items.length > 0 ? (
                    <Link href="/products?filter=low" className="text-caption text-ink-link hover:underline">
                        {t('low.see_all')}
                    </Link>
                ) : null}
            </div>
            {items.length === 0 ? (
                <p className="mt-4 text-caption text-ink-muted">{t('low.empty')}</p>
            ) : (
                <ul className="mt-4 space-y-4">
                    {items.map((p, i) => {
                        const tone: keyof typeof toneText = p.stock === 0 ? 'danger' : p.stock <= 5 ? 'warning' : 'success';
                        const label = p.stock === 0 ? t('low.sold_out') : p.stock <= 5 ? t('low.left', { count: p.stock }) : t('low.in_stock', { count: p.stock });
                        const width = p.stock === 0 ? 0 : Math.min(100, Math.max(8, (p.stock / 40) * 100));
                        return (
                            <li key={p.id}>
                                <Link href={`/products/${p.id}/edit`} className="block rounded-md hover:opacity-80">
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="truncate text-sm">{p.name || t('products.untitled')}</span>
                                        <span className={cn('shrink-0 text-caption', toneText[tone])}>{label}</span>
                                    </div>
                                    <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-chart-track" aria-hidden>
                                        <div
                                            className={cn('h-full rounded-full transition-[width] duration-1000 ease-[cubic-bezier(0.22,1,0.36,1)]', toneBg[tone])}
                                            style={{ width: `${grown ? width : 0}%`, transitionDelay: `${i * 100}ms` }}
                                        />
                                    </div>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Card>
    );
}

/* ---------- Visitors ---------- */

const sources = [
    { key: 'instagram', color: 'bg-chart-1' },
    { key: 'facebook', color: 'bg-chart-2' },
    { key: 'direct', color: 'bg-chart-3' },
    { key: 'google', color: 'bg-chart-4' },
] as const;

function VisitorsCard({ visitors }: { visitors: Props['visitors'] }) {
    const t = useT();
    const count = useCountUp(visitors?.count ?? 0);
    return (
        <Card className="flex flex-col p-6 md:col-span-2 xl:min-h-[296px]">
            <div className="flex items-center justify-between gap-3">
                <h2 className={cardTitle}>{t('visitors.title')}</h2>
                {visitors?.change != null ? <Badge tone="highlight">{`${visitors.change > 0 ? '+' : ''}${visitors.change}%`}</Badge> : null}
            </div>
            <div className="mt-3 flex flex-wrap items-end gap-3">
                <p className="tabular font-display text-numeral">{formatNumber(Math.round(count))}</p>
                <p className="pb-1 text-sm text-ink-muted">{visitors ? t('visitors.bought', { pct: visitors.conversion }) : t('visitors.empty')}</p>
            </div>
            <ul className="mt-auto grid grid-cols-2 gap-2 pt-6 sm:grid-cols-4">
                {sources.map((s, i) => (
                    <li key={s.key} style={{ animationDelay: `${500 + i * 80}ms` }} className="mv-pop rounded-2xl bg-elevated p-3 shadow-sm transition-transform hover:-translate-y-0.5">
                        <span className={cn('block h-2 w-2 rounded-full', s.color, visitors && 'mv-pulse')} aria-hidden />
                        <p className="tabular mt-3 font-display text-numeral-sm">{visitors ? `${visitors.sources[s.key] ?? 0}%` : '—'}</p>
                        <p className="mt-1 text-caption text-ink-muted">{t(`visitors.${s.key}`)}</p>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

/* ---------- Onboarding questions (FR-ONB-04) ---------- */

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
        <Card className="mb-4 p-6">
            <h2 className={cardTitle}>{t('onboarding.title')}</h2>
            <p className="mt-1 text-sm text-ink-muted">{t('onboarding.subtitle')}</p>
            <form onSubmit={submit} className="mt-5 grid gap-4 lg:grid-cols-[2fr_1fr_1fr_auto] lg:items-end">
                <Field label={t('onboarding.sells')} htmlFor="sells" error={errors.sells}>
                    <Textarea id="sells" rows={1} value={data.sells} onChange={(e) => setData('sells', e.target.value)} placeholder={t('onboarding.sells_ph')} maxLength={500} />
                </Field>
                <Field label={t('onboarding.location')} htmlFor="location" error={errors.location}>
                    <Input id="location" value={data.location} onChange={(e) => setData('location', e.target.value)} placeholder={t('onboarding.location_ph')} maxLength={100} />
                </Field>
                <Field label={t('onboarding.language')} htmlFor="language" error={errors.language}>
                    <Select id="language" value={data.language} onChange={(e) => setData('language', e.target.value as 'ar' | 'en')}>
                        <option value="ar">{t('common.arabic')}</option>
                        <option value="en">{t('common.english')}</option>
                    </Select>
                </Field>
                <div className="flex gap-2">
                    <Button type="submit" disabled={processing}>
                        {t('onboarding.save')}
                    </Button>
                    <Button variant="ghost" onClick={() => router.post('/onboarding/skip')} disabled={processing}>
                        {t('onboarding.skip')}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

export default function Today(props: Props) {
    const t = useT();
    const can = useCan();
    const { auth } = usePage<SharedProps>().props;
    const firstName = (auth.user?.name ?? '').trim().split(/\s+/)[0] ?? '';
    const part = useMemo(() => dayPart(), []);

    const heading = (
        <div>
            <h1 className="text-[26px] font-light leading-tight tracking-[-0.03em] sm:text-greeting">{t(`home.greeting_${part}`, { name: firstName })}</h1>
            <div className="mt-1.5">
                <StoreLine />
            </div>
        </div>
    );

    return (
        <DashboardLayout title={t('nav.home')} heading={heading}>
            {props.showOnboarding && can('design.manage') ? <OnboardingQuestions profile={props.profile} /> : null}
            <div className="mv-stagger grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <AskCard />
                <SalesCard sales={props.sales} />
                <SetupCard checklist={props.checklist} />
                <NeedsYouCard attention={props.attention} />
                <ToShipCard toShip={props.toShip} />
                <WeekCard week={props.week} />
                <BestSellerCard best={props.inventory.best_seller} total={props.inventory.total} />
                <RunningLowCard items={props.inventory.running_low} />
                <VisitorsCard visitors={props.visitors} />
            </div>
        </DashboardLayout>
    );
}

