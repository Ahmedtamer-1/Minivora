// Building blocks for areas that are designed but not built yet (Documentation §7.3). Each preview
// page shows the real layout with clearly marked sample data, says which requirements it will meet,
// and keeps every action disabled, so nobody mistakes a mock-up for live numbers.
import { CircleDashed, Sparkles, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/cn';
import { useT } from '@/lib/i18n';

/** Slim banner at the top of a preview page: what it is, and which SRS requirements it covers. */
export function PlannedNotice({ requirements, body }: { requirements: string[]; body?: ReactNode }) {
    const t = useT();
    return (
        <div role="note" className="card mb-6 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-4 sm:px-5">
            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                <Sparkles className="h-5 w-5" aria-hidden strokeWidth={1.75} />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold">{t('planned.title')}</p>
                <p className="mt-0.5 text-sm text-ink-muted">{body ?? t('planned.body')}</p>
            </div>
            <ul className="flex flex-wrap gap-1.5" aria-label={t('planned.requirements')}>
                {requirements.map((r) => (
                    <li key={r}>
                        <span dir="ltr" className="inline-flex h-7 items-center rounded-full bg-secondary px-2.5 font-mono text-xs text-ink-muted">
                            {r}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** "Sample" chip for cards and tables that show made-up data. */
export function SampleBadge() {
    const t = useT();
    return (
        <Badge tone="neutral">
            <CircleDashed className="h-3.5 w-3.5" aria-hidden />
            {t('planned.sample')}
        </Badge>
    );
}

/** Card with a title, an optional description and the sample chip, so preview cards all look alike. */
export function PreviewCard({ title, description, action, children, className, sample = true }: { title: ReactNode; description?: ReactNode; action?: ReactNode; children: ReactNode; className?: string; sample?: boolean }) {
    return (
        <Card className={cn('flex flex-col', className)}>
            <div className="flex items-start justify-between gap-3 px-5 pb-3 pt-5 sm:px-6 sm:pt-6">
                <div className="min-w-0 flex-1">
                    <h2 className="font-display text-base">{title}</h2>
                    {description ? <p className="mt-1 text-sm text-ink-muted">{description}</p> : null}
                </div>
                <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                    {action}
                    {sample ? <SampleBadge /> : null}
                </div>
            </div>
            <div className="flex-1 px-5 pb-5 sm:px-6 sm:pb-6">{children}</div>
        </Card>
    );
}

export interface Column {
    key: string;
    label: ReactNode;
    align?: 'start' | 'end';
    /** Hidden on phones, where rows stack. */
    wide?: boolean;
}

/** Plain table for sample rows: a real <table> on wide screens, stacked rows on phones. */
export function PreviewTable({ columns, rows, caption }: { columns: Column[]; rows: Array<Record<string, ReactNode> & { id: string | number }>; caption: string }) {
    return (
        <div className="relative -mx-5 overflow-x-auto sm:-mx-6">
            <table className="w-full min-w-[32rem] text-sm">
                <caption className="sr-only">{caption}</caption>
                <thead>
                    <tr className="border-b border-line text-caption text-ink-muted">
                        {columns.map((c) => (
                            <th key={c.key} scope="col" className={cn('px-5 py-2.5 font-medium first:ps-5 sm:px-6', c.align === 'end' ? 'text-end' : 'text-start')}>
                                {c.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id} className="border-b border-line last:border-b-0">
                            {columns.map((c) => (
                                <td key={c.key} className={cn('px-5 py-3 sm:px-6', c.align === 'end' ? 'tabular text-end' : 'text-start')}>
                                    {row[c.key]}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** "What this area will do" list, with the requirement each line comes from. */
export function PlannedFeatures({ title, items }: { title?: ReactNode; items: Array<{ icon: LucideIcon; text: ReactNode; req?: string }> }) {
    const t = useT();
    return (
        <Card className="p-5 sm:p-6">
            <h2 className="font-display text-base">{title ?? t('planned.features')}</h2>
            <ul className="mt-4 space-y-3">
                {items.map(({ icon: Icon, text, req }, i) => (
                    <li key={i} className="flex items-start gap-3">
                        <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-accent-subtle text-ink-accent">
                            <Icon className="h-4 w-4" aria-hidden strokeWidth={1.75} />
                        </span>
                        <p className="min-w-0 flex-1 pt-1.5 text-sm">
                            {text}
                            {req ? (
                                <span dir="ltr" className="ms-2 whitespace-nowrap font-mono text-xs text-ink-muted">
                                    {req}
                                </span>
                            ) : null}
                        </p>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

/** Thin horizontal bar for shares and progress in previews. Width is the only thing that changes. */
export function Meter({ value, tone = 'accent', label }: { value: number; tone?: 'accent' | 'success' | 'warning' | 'danger'; label: string }) {
    const fill = { accent: 'bg-brand-accent', success: 'bg-success', warning: 'bg-warning', danger: 'bg-danger' }[tone];
    const pct = Math.max(0, Math.min(100, value));
    return (
        <div role="meter" aria-label={label} aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(pct)} className="h-2 w-full overflow-hidden rounded-full bg-chart-track">
            <div className={cn('h-full rounded-full', fill)} style={{ width: `${pct}%` }} />
        </div>
    );
}
