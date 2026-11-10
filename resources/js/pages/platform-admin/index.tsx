// Platform admin: list, suspend and restore stores, with usage. FR-ACC-08
import { router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StoreStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatDate, useT } from '@/lib/i18n';
import type { Paginated, SharedProps, StoreStatus } from '@/types';
import { Pagination } from '@/components/commerce';

interface AdminStore {
    id: number;
    name: string;
    subdomain: string;
    url: string;
    status: StoreStatus;
    members_count: number;
    suspended_reason: string | null;
    created_at: string;
}

interface Props {
    stores: Paginated<AdminStore>;
    q: string;
    totals: { stores: number; live: number; suspended: number };
}

export default function PlatformAdmin({ stores, q, totals }: Props) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const [search, setSearch] = useState(q);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get('/admin/stores', search ? { q: search } : {}, { preserveState: true });
    };

    const suspend = (store: AdminStore) => {
        const reason = window.prompt(t('admin.reason'));
        if (reason === null) return;
        router.post(`/admin/stores/${store.id}/suspend`, { reason }, { preserveScroll: true });
    };

    return (
        <DashboardLayout title={t('admin.title')}>
            <PageHeader title={t('admin.title')} />

            <div className="mb-6 grid grid-cols-3 gap-3">
                {[
                    [t('admin.total'), totals.stores],
                    [t('admin.live'), totals.live],
                    [t('admin.suspended'), totals.suspended],
                ].map(([label, value]) => (
                    <Card key={String(label)} className="px-4 py-3">
                        <p className="text-xs uppercase tracking-wide text-ink-muted">{label}</p>
                        <p className="mt-1 font-display text-2xl font-semibold">{value}</p>
                    </Card>
                ))}
            </div>

            <form onSubmit={submit} className="relative mb-4">
                <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-muted" aria-hidden />
                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('admin.search')} className="ps-9" aria-label={t('admin.search')} />
            </form>

            <Card className="overflow-x-auto">
                {stores.data.length === 0 ? (
                    <p className="px-5 py-8 text-center text-ink-muted">{t('admin.empty')}</p>
                ) : (
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="border-b border-line text-start text-xs uppercase tracking-wide text-ink-muted">
                            <tr>
                                <th className="px-5 py-3 text-start font-medium">{t('admin.title')}</th>
                                <th className="px-3 py-3 text-start font-medium">{t('admin.members')}</th>
                                <th className="px-3 py-3 text-start font-medium">{t('admin.created')}</th>
                                <th className="px-5 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {stores.data.map((s) => (
                                <tr key={s.id}>
                                    <td className="px-5 py-3">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{s.name}</span>
                                            <StoreStatusBadge status={s.status} />
                                        </div>
                                        <a href={s.url} target="_blank" rel="noopener" className="text-xs text-ink-link hover:underline" dir="ltr">
                                            {s.subdomain}
                                        </a>
                                        {s.suspended_reason ? <p className="text-xs text-danger">{s.suspended_reason}</p> : null}
                                    </td>
                                    <td className="px-3 py-3">{s.members_count}</td>
                                    <td className="px-3 py-3 text-ink-muted">{formatDate(s.created_at, locale)}</td>
                                    <td className="px-5 py-3 text-end">
                                        {s.status === 'suspended' ? (
                                            <Button size="sm" variant="secondary" onClick={() => router.post(`/admin/stores/${s.id}/restore`, {}, { preserveScroll: true })}>
                                                {t('admin.restore')}
                                            </Button>
                                        ) : (
                                            <Button size="sm" variant="danger" onClick={() => suspend(s)}>
                                                {t('admin.suspend')}
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Card>
            <Pagination page={stores} />
        </DashboardLayout>
    );
}
