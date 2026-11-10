// Shared pieces for the finance pages: types for what the server sends, the period picker, money
// cells, statement sections, the change chip and two light SVG charts (no chart library).
import { Link, router } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight, CalendarRange, type LucideIcon } from 'lucide-react';
import { useId, useMemo, useState, type ReactNode } from 'react';
import { Input, Select } from '@/components/ui/input';
import { cn } from '@/lib/cn';
import { useLocale, useT } from '@/lib/i18n';
import { formatPounds } from '@/lib/money';

export interface AccountLine {
    account_id: number;
    code: string;
    name_en: string;
    name_ar: string;
    subtype?: string;
    amount: number;
}

export interface ProfitAndLoss {
    from: string;
    to: string;
    revenue: AccountLine[];
    gross_revenue: number;
    contra: AccountLine[];
    net_revenue: number;
    cogs: AccountLine[];
    total_cogs: number;
    gross_profit: number;
    variable: AccountLine[];
    total_variable: number;
    contribution: number;
    fixed: AccountLine[];
    total_fixed: number;
    net_profit: number;
    gross_margin: number | null;
    net_margin: number | null;
}

export interface MonthPoint {
    month: string;
    revenue: number;
    costs: number;
    profit: number;
    cash: number;
    cash_change: number;
}

export interface Period {
    key: string;
    from: string;
    to: string;
}

export interface AccountRow {
    id: number;
    code: string;
    name_en: string;
    name_ar: string;
    type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense';
    subtype: string;
    is_system: boolean;
    is_active: boolean;
    balance?: number;
}

export const PERIODS = ['this_month', 'last_month', 'this_quarter', 'last_quarter', 'ytd', 'last_year', 'last_12', 'custom'] as const;

/** The account's name in the dashboard language. */
export function useAccountName(): (a: { name_en: string; name_ar: string }) => string {
    const locale = useLocale();
    return (a) => (locale === 'ar' && a.name_ar ? a.name_ar : a.name_en);
}

export function useMoney(): (piasters: number) => string {
    const locale = useLocale();
    return (p) => formatPounds(p, locale);
}

/** "Oct 2026" / "أكتوبر 2026" from "2026-10". */
export function formatMonth(ym: string, locale: 'ar' | 'en', short = false): string {
    const [y, m] = ym.split('-').map(Number);
    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG-u-nu-latn' : 'en-GB', { month: short ? 'short' : 'long', year: short ? undefined : 'numeric', timeZone: 'UTC' }).format(
        new Date(Date.UTC(y, m - 1, 1)),
    );
}

/** Money with fixed direction and tabular digits, muted when it is a deduction. */
export function Amount({ value, className, strong, signed }: { value: number; className?: string; strong?: boolean; signed?: boolean }) {
    const money = useMoney();
    return (
        <span dir="ltr" className={cn('tabular whitespace-nowrap', strong && 'font-display', value < 0 && !signed && 'text-ink-muted', signed && value < 0 && 'text-danger', signed && value > 0 && 'text-success', className)}>
            {signed && value > 0 ? '+' : ''}
            {money(value)}
        </span>
    );
}

/** Percentage change against the previous period, with direction in the icon and the words, not only colour. */
export function Delta({ now, before, inverse = false }: { now: number; before: number; inverse?: boolean }) {
    const t = useT();
    if (before === 0) return null;
    const pct = Math.round(((now - before) / Math.abs(before)) * 100);
    if (!Number.isFinite(pct)) return null;
    const up = pct >= 0;
    const good = inverse ? !up : up;
    const Icon = up ? ArrowUpRight : ArrowDownRight;
    return (
        <span className={cn('inline-flex items-center gap-0.5 text-caption', good ? 'text-success' : 'text-danger')}>
            <Icon className="h-3.5 w-3.5" aria-hidden />
            <span dir="ltr" className="tabular">
                {up ? '+' : ''}
                {pct}%
            </span>
            <span className="sr-only">{t('fin.vs_previous')}</span>
        </span>
    );
}

/**
 * Period picker: preset ranges plus a custom from/to. Changing it reloads the page with the same
 * other query parameters.
 */
export function PeriodPicker({ period, url, params = {}, options = PERIODS as unknown as string[] }: { period: Period; url: string; params?: Record<string, string | number | undefined | null>; options?: string[] }) {
    const t = useT();
    const id = useId();
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);
    const go = (extra: Record<string, string>) =>
        router.get(url, { ...Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== '')), ...extra }, { preserveScroll: true, preserveState: true, replace: true });

    return (
        <div className="flex flex-wrap items-center gap-2">
            <label htmlFor={id} className="sr-only">
                {t('fin.period')}
            </label>
            <div className="glass-strong relative flex h-10 items-center rounded-full ps-3 shadow-sm">
                <CalendarRange className="h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
                <select
                    id={id}
                    value={period.key}
                    onChange={(e) => (e.target.value === 'custom' ? go({ period: 'custom', from, to }) : go({ period: e.target.value }))}
                    className="h-full border-0 bg-transparent pe-9 ps-2 text-sm focus:ring-0"
                >
                    {options.map((p) => (
                        <option key={p} value={p}>
                            {t(`fin.period_${p}`)}
                        </option>
                    ))}
                </select>
            </div>
            {period.key === 'custom' ? (
                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        go({ period: 'custom', from, to });
                    }}
                >
                    <Input type="date" aria-label={t('fin.from')} value={from} onChange={(e) => setFrom(e.target.value)} className="!w-auto rounded-full" />
                    <Input type="date" aria-label={t('fin.to')} value={to} onChange={(e) => setTo(e.target.value)} className="!w-auto rounded-full" />
                    <button type="submit" className="h-10 rounded-full bg-primary px-4 text-sm font-semibold text-primary-fg">
                        {t('fin.apply')}
                    </button>
                </form>
            ) : null}
        </div>
    );
}

/** Rows of a statement section, each linking to the account's ledger, with an optional comparison column. */
export function StatementRows({ rows, previous, negate = false, link = true }: { rows: AccountLine[]; previous?: AccountLine[] | null; negate?: boolean; link?: boolean }) {
    const name = useAccountName();
    const prevBy = useMemo(() => new Map((previous ?? []).map((r) => [r.account_id, r.amount])), [previous]);
    return (
        <>
            {rows.map((r) => (
                <div key={r.account_id} className="grid min-h-[44px] grid-cols-[minmax(0,1fr)_auto] items-center gap-3 py-1 text-sm sm:grid-cols-[minmax(0,1fr)_9rem_9rem]">
                    <span className="flex min-w-0 items-baseline gap-2 ps-3">
                        <span dir="ltr" className="font-mono text-xs text-ink-muted">
                            {r.code}
                        </span>
                        {link ? (
                            <Link href={`/finance/ledger?account=${r.account_id}&period=ytd`} className="truncate hover:underline">
                                {name(r)}
                            </Link>
                        ) : (
                            <span className="truncate">{name(r)}</span>
                        )}
                    </span>
                    <Amount value={negate ? -r.amount : r.amount} className="text-end" />
                    {previous ? <Amount value={negate ? -(prevBy.get(r.account_id) ?? 0) : (prevBy.get(r.account_id) ?? 0)} className="hidden text-end text-ink-muted sm:block" /> : null}
                </div>
            ))}
        </>
    );
}

/** A subtotal or total line in a statement. */
export function TotalRow({ label, value, previous, level = 1, tone }: { label: ReactNode; value: number; previous?: number | null; level?: 1 | 2; tone?: 'profit' }) {
    return (
        <div
            className={cn(
                'grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-t border-line text-sm sm:grid-cols-[minmax(0,1fr)_9rem_9rem]',
                level === 2 ? 'min-h-[56px] font-display text-base' : 'min-h-[48px] font-semibold',
            )}
        >
            <span>{label}</span>
            <Amount value={value} strong={level === 2} className={cn('text-end', tone === 'profit' && (value >= 0 ? 'text-success' : 'text-danger'))} />
            {previous === undefined ? null : previous === null ? <span className="hidden sm:block" /> : <Amount value={previous} className="hidden text-end text-ink-muted sm:block" />}
        </div>
    );
}

export function SectionTitle({ children }: { children: ReactNode }) {
    return <h3 className="mt-5 pb-1 text-caption font-semibold text-ink-muted first:mt-0">{children}</h3>;
}

// Chart colours validated with the dataviz checks (CVD, lightness, contrast) on both themes.
const REVENUE = 'var(--mv-fin-revenue)';
const COSTS = 'var(--mv-fin-costs)';

/**
 * Revenue and costs per month as paired bars, with a hover/focus tooltip for profit and a table
 * for screen readers. One axis, starting at zero.
 */
export function RevenueCostChart({ data }: { data: MonthPoint[] }) {
    const t = useT();
    const locale = useLocale();
    const money = useMoney();
    const [active, setActive] = useState<number | null>(null);
    const max = Math.max(1, ...data.map((d) => Math.max(d.revenue, d.costs)));
    const W = 640;
    const H = 200;
    const pad = 4;
    const slot = W / data.length;
    const bar = Math.min(18, slot / 2 - 3);
    const y = (v: number) => H - (Math.max(0, v) / max) * (H - pad);
    const ticks = [0.5, 1].map((f) => f * max);
    const shown = active ?? data.length - 1;
    const point = data[shown];

    return (
        <figure>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <ul className="flex flex-wrap gap-4 text-caption text-ink-muted" aria-hidden>
                    <li className="inline-flex items-center gap-1.5">
                        <span className="h-2.5 w-2.5 rounded-sm" style={{ background: REVENUE }} />
                        {t('fin.revenue')}
                    </li>
                    <li className="inline-flex items-center gap-1.5">
                        <span className="h-2.5 w-2.5 rounded-sm" style={{ background: COSTS }} />
                        {t('fin.costs')}
                    </li>
                </ul>
                {point ? (
                    <p className="text-caption text-ink-muted" aria-live="polite">
                        <span className="text-ink">{formatMonth(point.month, locale)}</span> · {t('fin.revenue')} <Amount value={point.revenue} className="text-ink" /> · {t('fin.costs')}{' '}
                        <Amount value={point.costs} className="text-ink" /> · {t('fin.profit')} <Amount value={point.profit} signed />
                    </p>
                ) : null}
            </div>
            <svg viewBox={`0 0 ${W} ${H + 22}`} className="h-auto w-full overflow-visible" role="img" aria-label={t('fin.chart_label')} onMouseLeave={() => setActive(null)}>
                {ticks.map((v) => (
                    <g key={v}>
                        <line x1={0} x2={W} y1={y(v)} y2={y(v)} stroke="var(--mv-chart-track)" strokeWidth={1} strokeDasharray="2 4" />
                        <text x={W} y={y(v) - 4} textAnchor="end" className="fill-[var(--mv-text-muted)] text-[10px]" direction="ltr">
                            {money(v).replace(/\.\d+/, '')}
                        </text>
                    </g>
                ))}
                <line x1={0} x2={W} y1={H} y2={H} stroke="var(--mv-chart-track)" strokeWidth={1} />
                {data.map((d, i) => {
                    const cx = slot * i + slot / 2;
                    const on = i === shown;
                    return (
                        <g
                            key={d.month}
                            tabIndex={0}
                            role="button"
                            aria-label={`${formatMonth(d.month, locale)}: ${t('fin.revenue')} ${money(d.revenue)}, ${t('fin.costs')} ${money(d.costs)}`}
                            onMouseEnter={() => setActive(i)}
                            onFocus={() => setActive(i)}
                            className="cursor-default outline-none"
                        >
                            <rect x={slot * i} y={0} width={slot} height={H} fill={on && active !== null ? 'var(--mv-chart-track)' : 'transparent'} opacity={0.35} rx={6} />
                            <rect x={cx - bar - 1} y={y(d.revenue)} width={bar} height={H - y(d.revenue)} rx={4} fill={REVENUE} opacity={on ? 1 : 0.85} />
                            <rect x={cx + 1} y={y(d.costs)} width={bar} height={H - y(d.costs)} rx={4} fill={COSTS} opacity={on ? 1 : 0.85} />
                            <text x={cx} y={H + 16} textAnchor="middle" className={cn('text-[10px]', on ? 'fill-[var(--mv-text-primary)]' : 'fill-[var(--mv-text-muted)]')}>
                                {formatMonth(d.month, locale, true)}
                            </text>
                        </g>
                    );
                })}
            </svg>
            <table className="sr-only">
                <caption>{t('fin.chart_label')}</caption>
                <thead>
                    <tr>
                        <th>{t('fin.month')}</th>
                        <th>{t('fin.revenue')}</th>
                        <th>{t('fin.costs')}</th>
                        <th>{t('fin.profit')}</th>
                    </tr>
                </thead>
                <tbody>
                    {data.map((d) => (
                        <tr key={d.month}>
                            <td>{formatMonth(d.month, locale)}</td>
                            <td>{money(d.revenue)}</td>
                            <td>{money(d.costs)}</td>
                            <td>{money(d.profit)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}

/** Cash: the last few months as recorded, then three forecast months (hatched), against the safety level. */
export function CashChart({ history, forecast, safety }: { history: Array<{ month: string; cash: number }>; forecast: Array<{ month: string; cash: number }>; safety: number }) {
    const t = useT();
    const locale = useLocale();
    const money = useMoney();
    const patternId = useId().replace(/:/g, '');
    const points = [...history.slice(-4).map((p) => ({ ...p, f: false })), ...forecast.map((p) => ({ ...p, f: true }))];
    const max = Math.max(1, safety, ...points.map((p) => p.cash));
    const min = Math.min(0, ...points.map((p) => p.cash));
    const W = 320;
    const H = 140;
    const span = max - min;
    const y = (v: number) => H - ((v - min) / span) * H;
    const slot = W / points.length;
    const bar = Math.min(26, slot - 10);

    return (
        <figure>
            <svg viewBox={`0 0 ${W} ${H + 20}`} className="h-auto w-full overflow-visible" role="img" aria-label={t('fin.cash_chart_label')}>
                <defs>
                    <pattern id={patternId} width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                        <rect width="6" height="6" fill={REVENUE} opacity="0.35" />
                        <line x1="0" y1="0" x2="0" y2="6" stroke={REVENUE} strokeWidth="3" />
                    </pattern>
                </defs>
                <line x1={0} x2={W} y1={y(0)} y2={y(0)} stroke="var(--mv-chart-track)" />
                {points.map((p, i) => {
                    const top = Math.min(y(p.cash), y(0));
                    const h = Math.max(2, Math.abs(y(p.cash) - y(0)));
                    return (
                        <g key={p.month}>
                            <title>{`${formatMonth(p.month, locale)}${p.f ? ` (${t('fin.forecast')})` : ''}: ${money(p.cash)}`}</title>
                            <rect x={slot * i + (slot - bar) / 2} y={top} width={bar} height={h} rx={4} fill={p.cash < 0 ? 'var(--mv-status-danger)' : p.f ? `url(#${patternId})` : REVENUE} />
                            <text x={slot * i + slot / 2} y={H + 15} textAnchor="middle" className="fill-[var(--mv-text-muted)] text-[10px]">
                                {formatMonth(p.month, locale, true)}
                            </text>
                        </g>
                    );
                })}
                <line x1={0} x2={W} y1={y(safety)} y2={y(safety)} stroke="var(--mv-status-warning)" strokeWidth={1.5} strokeDasharray="5 4" />
            </svg>
            <figcaption className="mt-2 flex flex-wrap gap-4 text-caption text-ink-muted">
                <span className="inline-flex items-center gap-1.5">
                    <span className="h-2.5 w-2.5 rounded-sm" style={{ background: REVENUE }} aria-hidden />
                    {t('fin.actual')}
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <svg className="h-2.5 w-2.5 rounded-sm" aria-hidden>
                        <rect width="10" height="10" fill={`url(#${patternId})`} />
                    </svg>
                    {t('fin.forecast')}
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span className="h-0 w-3 border-t-2 border-dashed border-warning" aria-hidden />
                    {t('fin.safety_level')}
                </span>
            </figcaption>
        </figure>
    );
}

/** Horizontal share bars (expense breakdown). Values are always printed next to the bar. */
export function ShareBars({ rows, total }: { rows: Array<{ key: string | number; label: string; value: number; href?: string }>; total: number }) {
    return (
        <ul className="space-y-3">
            {rows.map((r) => {
                const pct = total > 0 ? (r.value / total) * 100 : 0;
                return (
                    <li key={r.key}>
                        <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
                            {r.href ? (
                                <Link href={r.href} className="truncate hover:underline">
                                    {r.label}
                                </Link>
                            ) : (
                                <span className="truncate">{r.label}</span>
                            )}
                            <span className="shrink-0 text-caption text-ink-muted">
                                <Amount value={r.value} className="text-ink" /> · <span dir="ltr">{Math.round(pct)}%</span>
                            </span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-chart-track" aria-hidden>
                            <div className="h-full rounded-full" style={{ width: `${Math.max(1, pct)}%`, background: COSTS }} />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

/** Select of accounts grouped by type. */
export function AccountSelect({ accounts, value, onChange, id, filter, invalid }: { accounts: AccountRow[]; value: number | ''; onChange: (id: number | '') => void; id: string; filter?: (a: AccountRow) => boolean; invalid?: boolean }) {
    const t = useT();
    const name = useAccountName();
    const list = accounts.filter((a) => a.is_active && (!filter || filter(a)));
    const types = ['asset', 'liability', 'equity', 'revenue', 'expense'] as const;
    return (
        <Select id={id} value={value} invalid={invalid} onChange={(e) => onChange(e.target.value === '' ? '' : Number(e.target.value))}>
            <option value="">{t('fin.choose_account')}</option>
            {types.map((type) => {
                const group = list.filter((a) => a.type === type);
                return group.length ? (
                    <optgroup key={type} label={t(`fin.type_${type}`)}>
                        {group.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.code} · {name(a)}
                            </option>
                        ))}
                    </optgroup>
                ) : null;
            })}
        </Select>
    );
}

/** Empty state for a finance card. */
export function EmptyFinance({ icon: Icon, title, body, action }: { icon: LucideIcon; title: string; body: string; action?: ReactNode }) {
    return (
        <div className="flex flex-col items-center gap-3 px-6 py-10 text-center">
            <span className="grid h-12 w-12 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                <Icon className="h-6 w-6" aria-hidden strokeWidth={1.75} />
            </span>
            <p className="font-display text-base">{title}</p>
            <p className="max-w-sm text-sm text-ink-muted">{body}</p>
            {action}
        </div>
    );
}
