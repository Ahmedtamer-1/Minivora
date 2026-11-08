// Activity log: who did what, including which AI agent acted (FR-ACC-05, FR-AI-10).
import { Link, usePage } from '@inertiajs/react';
import { Bot, Cog, User } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatDate, useT } from '@/lib/i18n';
import type { Paginated, SharedProps } from '@/types';

interface Log {
    id: number;
    action: string;
    actor_type: 'user' | 'agent' | 'system';
    actor: string | null;
    on_behalf_of: string | null;
    properties: Record<string, unknown> | null;
    created_at: string;
}

const icons = { user: User, agent: Bot, system: Cog };

export default function Activity({ logs }: { logs: Paginated<Log> }) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;

    const who = (log: Log) => {
        if (log.actor_type === 'agent') {
            const agent = t('activity.agent', { agent: (log.actor ?? '').toUpperCase() });
            return log.on_behalf_of ? `${agent} · ${t('activity.for', { name: log.on_behalf_of })}` : agent;
        }
        if (log.actor_type === 'system') return t('activity.system');
        return log.actor ?? '—';
    };

    return (
        <DashboardLayout title={t('activity.title')}>
            <PageHeader title={t('activity.title')} description={t('activity.subtitle')} />
            <Card>
                {logs.data.length === 0 ? (
                    <p className="px-5 py-8 text-center text-ink-muted">{t('activity.empty')}</p>
                ) : (
                    <ul className="divide-y divide-line">
                        {logs.data.map((log) => {
                            const Icon = icons[log.actor_type];
                            return (
                                <li key={log.id} className="flex items-start gap-3 px-5 py-3">
                                    <Icon className="mt-0.5 h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm">
                                            <span className="font-medium">{who(log)}</span>{' '}
                                            <code className="rounded-sm bg-surface px-1.5 py-0.5 font-mono text-xs" dir="ltr">
                                                {log.action}
                                            </code>
                                        </p>
                                        {log.properties ? (
                                            <p className="mt-1 truncate font-mono text-xs text-ink-muted" dir="ltr">
                                                {JSON.stringify(log.properties)}
                                            </p>
                                        ) : null}
                                    </div>
                                    <time className="shrink-0 text-xs text-ink-muted" dateTime={log.created_at}>
                                        {formatDate(log.created_at, locale, true)}
                                    </time>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </Card>
            <Pagination page={logs} />
        </DashboardLayout>
    );
}

export function Pagination<T>({ page }: { page: Paginated<T> }) {
    const t = useT();
    if (page.last_page <= 1) return null;
    return (
        <nav className="mt-4 flex items-center justify-between text-sm" aria-label="Pagination">
            {page.prev_page_url ? <Link href={page.prev_page_url} className="text-ink-link hover:underline">{t('pagination.prev')}</Link> : <span />}
            <span className="text-ink-muted">
                {page.current_page} / {page.last_page}
            </span>
            {page.next_page_url ? <Link href={page.next_page_url} className="text-ink-link hover:underline">{t('pagination.next')}</Link> : <span />}
        </nav>
    );
}
