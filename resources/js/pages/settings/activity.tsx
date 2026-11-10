// Activity log: who did what, including which AI agent acted (FR-ACC-05, FR-AI-10).
import { usePage } from '@inertiajs/react';
import { Bot, Cog, User } from 'lucide-react';
import { Pagination } from '@/components/commerce';
import { Card } from '@/components/ui/card';
import { SettingsLayout } from '@/layouts/settings-layout';
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

    // Readable label for each action; unknown codes fall back to the code itself.
    const label = (action: string) => {
        const key = `activity.a.${action}`;
        const text = t(key);
        return text === key ? action : text;
    };

    return (
        <SettingsLayout title={t('activity.title')} description={t('activity.subtitle')}>
            <Card>
                {logs.data.length === 0 ? (
                    <p className="px-5 py-10 text-center text-sm text-ink-muted">{t('activity.empty')}</p>
                ) : (
                    <ul className="divide-y divide-line">
                        {logs.data.map((log) => {
                            const Icon = icons[log.actor_type];
                            const details = Object.entries(log.properties ?? {}).filter(([, v]) => v !== null && typeof v !== 'object');
                            return (
                                <li key={log.id} className="flex items-start gap-3 px-5 py-4 sm:px-6">
                                    <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-full ${log.actor_type === 'agent' ? 'bg-accent-subtle text-ink-accent' : 'bg-secondary text-ink-muted'}`}>
                                        <Icon className="h-4 w-4" aria-hidden />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm">
                                            <span className="font-medium">{who(log)}</span> <span className="text-ink-muted">·</span> {label(log.action)}
                                        </p>
                                        {details.length > 0 ? (
                                            <ul className="mt-1.5 flex flex-wrap gap-1.5">
                                                {details.slice(0, 4).map(([k, v]) => (
                                                    <li key={k} className="max-w-full truncate rounded-full bg-secondary px-2.5 py-0.5 text-xs text-ink-muted">
                                                        {k.replaceAll('_', ' ')}: <span dir="auto" className="text-ink">{String(v)}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : null}
                                    </div>
                                    <time className="shrink-0 pt-0.5 text-caption text-ink-muted" dateTime={log.created_at}>
                                        {formatDate(log.created_at, locale, true)}
                                    </time>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </Card>
            <Pagination page={logs} />
        </SettingsLayout>
    );
}
