// General ledger: journal entries (automatic and manual), one account's activity with a running
// balance, manual entries and transfers, the chart of accounts with balances, and finance settings.
// FR-LED-01, FR-LED-02, FR-LED-03, FR-LED-06
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeftRight, ChevronDown, Plus, Scale, Search, Trash2, Undo2, X } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { FilterTabs, Pagination } from '@/components/commerce';
import { AccountSelect, Amount, PeriodPicker, useAccountName, useMoney, type AccountRow, type Period } from '@/components/finance';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Checkbox, Field, Input, Select } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { formatDate, useLocale, useT } from '@/lib/i18n';
import { useCan } from '@/lib/permissions';
import type { Paginated } from '@/types';

type Tab = 'journal' | 'accounts' | 'settings';

interface Entry {
    id: number;
    number: number;
    date: string;
    memo: string;
    source_type: 'order' | 'expense' | 'manual' | 'adjustment' | 'reversal';
    source_id: number | null;
    amount: number;
    reversed: boolean;
    reversal_of: number | null;
    lines: Array<{ account_id: number; debit: number; credit: number; memo: string | null }>;
}

interface Activity {
    account: AccountRow;
    opening: number;
    closing: number;
    debit: number;
    credit: number;
    lines: Array<{ id: number; date: string; entry_id: number; number: number; memo: string; source_type: string; debit: number; credit: number; balance: number }>;
}

interface Settings {
    vat_registered: boolean;
    vat_rate_bps: number;
    gateway_fee_bps: number;
    gateway_fee_fixed: number;
    cash_safety_level: number;
}

interface Props {
    tab: Tab;
    accounts: AccountRow[];
    settings: Settings;
    today: string;
    entries?: Paginated<Entry>;
    filters?: { source: string | null; account: number | null; search: string };
    activity?: Activity;
    period?: Period;
    trial?: { debit: number; credit: number; balanced: boolean };
}

export default function FinanceLedger({ tab, accounts, settings, today, entries, filters, activity, period, trial }: Props) {
    const t = useT();
    const can = useCan();
    const manage = can('ledger.manage');
    const { url } = usePage();
    const [panel, setPanel] = useState<'entry' | 'transfer' | null>(url.includes('new=1') ? 'entry' : null);

    return (
        <DashboardLayout title={t('nav.finance_ledger')}>
            <PageHeader
                title={t('ledger.title')}
                description={t('ledger.subtitle')}
                action={
                    manage && tab === 'journal' ? (
                        <>
                            <Button variant="secondary" onClick={() => setPanel(panel === 'transfer' ? null : 'transfer')} aria-expanded={panel === 'transfer'}>
                                <ArrowLeftRight className="h-4 w-4" aria-hidden />
                                {t('ledger.transfer')}
                            </Button>
                            <Button onClick={() => setPanel(panel === 'entry' ? null : 'entry')} aria-expanded={panel === 'entry'}>
                                <Plus className="h-4 w-4" aria-hidden />
                                {t('ledger.new_entry')}
                            </Button>
                        </>
                    ) : null
                }
            />

            <FilterTabs
                label={t('ledger.tabs')}
                active={tab}
                onChange={(k) => router.get('/finance/ledger', { tab: k }, { preserveScroll: true })}
                options={(['journal', 'accounts', 'settings'] as Tab[]).map((k) => ({ key: k, label: t(`ledger.tab_${k}`) }))}
            />

            {tab === 'journal' && panel === 'entry' ? <ManualEntry accounts={accounts} today={today} onDone={() => setPanel(null)} /> : null}
            {tab === 'journal' && panel === 'transfer' ? <Transfer today={today} onDone={() => setPanel(null)} /> : null}

            {tab === 'journal' && entries && filters ? <Journal entries={entries} filters={filters} accounts={accounts} activity={activity} period={period} manage={manage} /> : null}
            {tab === 'accounts' ? <Accounts accounts={accounts} trial={trial} manage={manage} /> : null}
            {tab === 'settings' ? <SettingsForm settings={settings} manage={manage} /> : null}
        </DashboardLayout>
    );
}

function Journal({ entries, filters, accounts, activity, period, manage }: { entries: Paginated<Entry>; filters: NonNullable<Props['filters']>; accounts: AccountRow[]; activity?: Activity; period?: Period; manage: boolean }) {
    const t = useT();
    const locale = useLocale();
    const name = useAccountName();
    const byId = useMemo(() => new Map(accounts.map((a) => [a.id, a])), [accounts]);
    const [search, setSearch] = useState(filters.search);
    const [open, setOpen] = useState<number | null>(null);
    const params = { tab: 'journal', source: filters.source ?? undefined, account: filters.account ?? undefined, search: filters.search || undefined };

    useEffect(() => {
        if (search === filters.search) return;
        const id = setTimeout(() => router.get('/finance/ledger', { ...params, search: search || undefined }, { preserveState: true, preserveScroll: true, replace: true }), 300);
        return () => clearTimeout(id);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div className="space-y-6">
            {activity && period ? (
                <Card>
                    <CardHeader
                        title={`${activity.account.code} · ${name(activity.account)}`}
                        description={t('ledger.activity_hint')}
                        action={
                            <Link href="/finance/ledger" className="inline-flex items-center gap-1 text-sm text-ink-accent hover:underline">
                                <X className="h-4 w-4" aria-hidden />
                                {t('ledger.clear_account')}
                            </Link>
                        }
                    />
                    <CardBody>
                        <div className="mb-4">
                            <PeriodPicker period={period} url="/finance/ledger" params={{ account: activity.account.id }} />
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <caption className="sr-only">{t('ledger.activity')}</caption>
                                <thead>
                                    <tr className="border-b border-line text-caption text-ink-muted">
                                        <th className="py-2 text-start font-normal">{t('ledger.col_date')}</th>
                                        <th className="py-2 text-start font-normal">{t('ledger.col_memo')}</th>
                                        <th className="py-2 text-end font-normal">{t('ledger.col_debit')}</th>
                                        <th className="py-2 text-end font-normal">{t('ledger.col_credit')}</th>
                                        <th className="py-2 text-end font-normal">{t('ledger.col_balance')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    <tr className="text-ink-muted">
                                        <td className="py-2" colSpan={4}>
                                            {t('ledger.opening')}
                                        </td>
                                        <td className="py-2 text-end">
                                            <Amount value={activity.opening} />
                                        </td>
                                    </tr>
                                    {activity.lines.map((l) => (
                                        <tr key={l.id}>
                                            <td className="whitespace-nowrap py-2 text-ink-muted">{formatDate(l.date, locale)}</td>
                                            <td className="py-2">
                                                <span dir="ltr" className="me-1 text-ink-muted">
                                                    #{l.number}
                                                </span>
                                                {l.memo}
                                            </td>
                                            <td className="py-2 text-end">{l.debit ? <Amount value={l.debit} /> : null}</td>
                                            <td className="py-2 text-end">{l.credit ? <Amount value={l.credit} /> : null}</td>
                                            <td className="py-2 text-end">
                                                <Amount value={l.balance} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t-2 border-line font-semibold">
                                        <td className="py-2.5" colSpan={2}>
                                            {t('ledger.closing')}
                                        </td>
                                        <td className="py-2.5 text-end">
                                            <Amount value={activity.debit} />
                                        </td>
                                        <td className="py-2.5 text-end">
                                            <Amount value={activity.credit} />
                                        </td>
                                        <td className="py-2.5 text-end">
                                            <Amount value={activity.closing} />
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </CardBody>
                </Card>
            ) : null}

            <Card>
                <CardHeader
                    title={t('ledger.journal')}
                    description={t('ledger.journal_hint')}
                    action={
                        <Badge tone="success">
                            <Scale className="h-3.5 w-3.5" aria-hidden />
                            {t('ledger.balanced')}
                        </Badge>
                    }
                />
                <CardBody className="pt-0">
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <label className="glass-strong flex h-10 min-w-0 flex-1 items-center gap-2 rounded-full px-4 sm:max-w-xs">
                            <Search className="h-4 w-4 shrink-0 text-ink-muted" aria-hidden />
                            <span className="sr-only">{t('ledger.search')}</span>
                            <input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('ledger.search')} className="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:ring-0" />
                        </label>
                        <Select
                            aria-label={t('ledger.source')}
                            value={filters.source ?? ''}
                            onChange={(e) => router.get('/finance/ledger', { ...params, source: e.target.value || undefined }, { preserveState: true, preserveScroll: true, replace: true })}
                            className="!w-auto rounded-full"
                        >
                            <option value="">{t('ledger.all_sources')}</option>
                            {['order', 'expense', 'manual', 'adjustment', 'reversal'].map((s) => (
                                <option key={s} value={s}>
                                    {t(`ledger.source_${s}`)}
                                </option>
                            ))}
                        </Select>
                        <Select
                            aria-label={t('ledger.col_account')}
                            value={filters.account ?? ''}
                            onChange={(e) => router.get('/finance/ledger', { ...params, account: e.target.value || undefined, period: 'ytd' }, { preserveScroll: true })}
                            className="!w-auto max-w-[16rem] rounded-full"
                        >
                            <option value="">{t('ledger.any_account')}</option>
                            {accounts.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.code} · {name(a)}
                                </option>
                            ))}
                        </Select>
                    </div>

                    {entries.data.length === 0 ? (
                        <p className="py-10 text-center text-sm text-ink-muted">{t('ledger.empty')}</p>
                    ) : (
                        <ul className="divide-y divide-line">
                            {entries.data.map((e) => {
                                const isOpen = open === e.id;
                                return (
                                    <li key={e.id}>
                                        <button type="button" onClick={() => setOpen(isOpen ? null : e.id)} aria-expanded={isOpen} className="flex w-full items-center gap-3 py-3 text-start text-sm">
                                            <span dir="ltr" className="w-12 shrink-0 font-mono text-xs text-ink-muted">
                                                #{e.number}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className={cn('block truncate font-medium', e.reversed && 'line-through opacity-70')}>{e.memo}</span>
                                                <span className="text-caption text-ink-muted">
                                                    {formatDate(e.date, locale)} · {t(`ledger.source_${e.source_type}`)}
                                                    {e.reversed ? ` · ${t('ledger.reversed')}` : ''}
                                                </span>
                                            </span>
                                            <Amount value={e.amount} className="shrink-0" />
                                            <ChevronDown className={cn('h-4 w-4 shrink-0 text-ink-muted transition-transform', isOpen && 'rotate-180')} aria-hidden />
                                        </button>
                                        {isOpen ? (
                                            <div className="mb-3 rounded-2xl bg-secondary p-3">
                                                <table className="w-full text-sm">
                                                    <caption className="sr-only">{t('ledger.lines_of', { number: e.number })}</caption>
                                                    <thead>
                                                        <tr className="text-caption text-ink-muted">
                                                            <th className="pb-1 text-start font-normal">{t('ledger.col_account')}</th>
                                                            <th className="pb-1 text-end font-normal">{t('ledger.col_debit')}</th>
                                                            <th className="pb-1 text-end font-normal">{t('ledger.col_credit')}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {e.lines.map((l, i) => {
                                                            const a = byId.get(l.account_id);
                                                            return (
                                                                <tr key={i}>
                                                                    <td className={cn('py-1', l.credit > 0 && 'ps-6')}>
                                                                        <Link href={`/finance/ledger?account=${l.account_id}&period=ytd`} className="hover:underline">
                                                                            {a ? `${a.code} · ${name(a)}` : l.account_id}
                                                                        </Link>
                                                                    </td>
                                                                    <td className="py-1 text-end">{l.debit ? <Amount value={l.debit} /> : null}</td>
                                                                    <td className="py-1 text-end">{l.credit ? <Amount value={l.credit} /> : null}</td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>
                                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                                    {e.source_type === 'order' && e.source_id ? (
                                                        <Link href={`/orders/${e.source_id}`} className="text-sm text-ink-accent hover:underline">
                                                            {t('ledger.open_order')}
                                                        </Link>
                                                    ) : null}
                                                    {manage && !e.reversed && e.source_type !== 'reversal' ? (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => window.confirm(t('ledger.reverse_confirm')) && router.post(`/finance/journal/${e.id}/reverse`, {}, { preserveScroll: true })}
                                                        >
                                                            <Undo2 className="h-3.5 w-3.5" aria-hidden />
                                                            {t('ledger.reverse')}
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            </div>
                                        ) : null}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    <Pagination page={entries} />
                </CardBody>
            </Card>
        </div>
    );
}

interface DraftLine {
    account_id: number | '';
    debit: string;
    credit: string;
}

const toPiasters = (s: string) => Math.round(Number((s || '0').replace(/,/g, '')) * 100) || 0;

function ManualEntry({ accounts, today, onDone }: { accounts: AccountRow[]; today: string; onDone: () => void }) {
    const t = useT();
    const money = useMoney();
    const form = useForm<{ entry_date: string; memo: string; lines: DraftLine[] }>({
        entry_date: today,
        memo: '',
        lines: [
            { account_id: '', debit: '', credit: '' },
            { account_id: '', debit: '', credit: '' },
        ],
    });
    const debit = form.data.lines.reduce((s, l) => s + toPiasters(l.debit), 0);
    const credit = form.data.lines.reduce((s, l) => s + toPiasters(l.credit), 0);
    const balanced = debit === credit && debit > 0;
    const set = (i: number, patch: Partial<DraftLine>) => form.setData('lines', form.data.lines.map((l, j) => (j === i ? { ...l, ...patch } : l)));
    const errors = form.errors as Record<string, string>;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/finance/journal', { preserveScroll: true, onSuccess: () => { form.reset(); onDone(); } });
    };

    return (
        <Card className="mb-6">
            <CardHeader title={t('ledger.new_entry')} description={t('ledger.new_entry_hint')} />
            <CardBody>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-[12rem_minmax(0,1fr)]">
                        <Field label={t('ledger.col_date')} htmlFor="entry_date" error={form.errors.entry_date}>
                            <Input id="entry_date" type="date" value={form.data.entry_date} onChange={(e) => form.setData('entry_date', e.target.value)} />
                        </Field>
                        <Field label={t('ledger.col_memo')} htmlFor="memo" error={form.errors.memo}>
                            <Input id="memo" value={form.data.memo} onChange={(e) => form.setData('memo', e.target.value)} placeholder={t('ledger.memo_ph')} required />
                        </Field>
                    </div>
                    <div className="space-y-2">
                        <div className="hidden grid-cols-[minmax(0,1fr)_8rem_8rem_2.5rem] gap-2 text-caption text-ink-muted sm:grid">
                            <span>{t('ledger.col_account')}</span>
                            <span>{t('ledger.col_debit')}</span>
                            <span>{t('ledger.col_credit')}</span>
                            <span />
                        </div>
                        {form.data.lines.map((l, i) => (
                            <div key={i} className="grid grid-cols-2 gap-2 sm:grid-cols-[minmax(0,1fr)_8rem_8rem_2.5rem]">
                                <div className="col-span-2 sm:col-span-1">
                                    <AccountSelect id={`line-${i}`} accounts={accounts} value={l.account_id} onChange={(v) => set(i, { account_id: v })} invalid={!!errors[`lines.${i}.account_id`]} />
                                </div>
                                <Input aria-label={t('ledger.col_debit')} inputMode="decimal" dir="ltr" placeholder={t('ledger.col_debit')} value={l.debit} onChange={(e) => set(i, { debit: e.target.value, credit: e.target.value ? '' : l.credit })} />
                                <Input aria-label={t('ledger.col_credit')} inputMode="decimal" dir="ltr" placeholder={t('ledger.col_credit')} value={l.credit} onChange={(e) => set(i, { credit: e.target.value, debit: e.target.value ? '' : l.debit })} />
                                <Button
                                    size="icon-sm"
                                    variant="ghost"
                                    aria-label={t('ledger.remove_line')}
                                    disabled={form.data.lines.length <= 2}
                                    onClick={() => form.setData('lines', form.data.lines.filter((_, j) => j !== i))}
                                >
                                    <Trash2 className="h-4 w-4" aria-hidden />
                                </Button>
                            </div>
                        ))}
                        <Button size="sm" variant="ghost" onClick={() => form.setData('lines', [...form.data.lines, { account_id: '', debit: '', credit: '' }])}>
                            <Plus className="h-4 w-4" aria-hidden />
                            {t('ledger.add_line')}
                        </Button>
                    </div>
                    <div className={cn('flex flex-wrap items-center justify-between gap-3 rounded-2xl p-3 text-sm', balanced ? 'bg-success-subtle' : 'bg-warning-subtle')} aria-live="polite">
                        <span>
                            {t('ledger.col_debit')} <strong dir="ltr">{money(debit)}</strong> · {t('ledger.col_credit')} <strong dir="ltr">{money(credit)}</strong>
                        </span>
                        <span className={balanced ? 'text-success' : 'text-warning'}>{balanced ? t('ledger.is_balanced') : t('ledger.difference', { amount: money(Math.abs(debit - credit)) })}</span>
                    </div>
                    {errors.lines ? (
                        <p role="alert" className="text-sm text-danger">
                            {errors.lines}
                        </p>
                    ) : null}
                    <p className="text-caption text-ink-muted">{t('ledger.examples')}</p>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={!balanced || form.processing}>
                            {t('ledger.post')}
                        </Button>
                        <Button variant="ghost" onClick={onDone}>
                            {t('expenses.cancel')}
                        </Button>
                    </div>
                </form>
            </CardBody>
        </Card>
    );
}

function Transfer({ today, onDone }: { today: string; onDone: () => void }) {
    const t = useT();
    const form = useForm({ from: '1020', to: '1010', amount: '', fee: '', entry_date: today });
    const options = ['1000', '1010', '1020'];
    return (
        <Card className="mb-6">
            <CardHeader title={t('ledger.transfer')} description={t('ledger.transfer_hint')} />
            <CardBody>
                <form
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/finance/transfer', { preserveScroll: true, onSuccess: onDone });
                    }}
                >
                    <Field label={t('ledger.from')} htmlFor="t-from" error={form.errors.from}>
                        <Select id="t-from" value={form.data.from} onChange={(e) => form.setData('from', e.target.value)}>
                            {options.map((o) => (
                                <option key={o} value={o}>
                                    {t(`ledger.money_${o}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('ledger.to')} htmlFor="t-to" error={form.errors.to}>
                        <Select id="t-to" value={form.data.to} onChange={(e) => form.setData('to', e.target.value)}>
                            {options.map((o) => (
                                <option key={o} value={o}>
                                    {t(`ledger.money_${o}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('expenses.f_amount')} htmlFor="t-amount" error={form.errors.amount}>
                        <Input id="t-amount" inputMode="decimal" dir="ltr" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} required />
                    </Field>
                    <Field label={t('ledger.fee')} htmlFor="t-fee" error={form.errors.fee} hint={t('ledger.fee_hint')}>
                        <Input id="t-fee" inputMode="decimal" dir="ltr" value={form.data.fee} onChange={(e) => form.setData('fee', e.target.value)} />
                    </Field>
                    <Field label={t('ledger.col_date')} htmlFor="t-date" error={form.errors.entry_date}>
                        <Input id="t-date" type="date" value={form.data.entry_date} onChange={(e) => form.setData('entry_date', e.target.value)} />
                    </Field>
                    <div className="flex gap-2 sm:col-span-2 xl:col-span-5">
                        <Button type="submit" disabled={form.processing}>
                            {t('ledger.record_transfer')}
                        </Button>
                        <Button variant="ghost" onClick={onDone}>
                            {t('expenses.cancel')}
                        </Button>
                    </div>
                </form>
            </CardBody>
        </Card>
    );
}

function Accounts({ accounts, trial, manage }: { accounts: AccountRow[]; trial?: Props['trial']; manage: boolean }) {
    const t = useT();
    const name = useAccountName();
    const [adding, setAdding] = useState(false);
    const form = useForm({ code: '', name_en: '', name_ar: '', type: 'expense', subtype: 'fixed' });
    const types = ['asset', 'liability', 'equity', 'revenue', 'expense'] as const;

    return (
        <div className="space-y-6">
            {manage ? (
                adding ? (
                    <Card>
                        <CardHeader title={t('ledger.add_account')} description={t('ledger.add_account_hint')} />
                        <CardBody>
                            <form
                                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.post('/finance/accounts', { preserveScroll: true, onSuccess: () => { form.reset(); setAdding(false); } });
                                }}
                            >
                                <Field label={t('ledger.code')} htmlFor="a-code" error={form.errors.code}>
                                    <Input id="a-code" dir="ltr" inputMode="numeric" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} placeholder="6150" required />
                                </Field>
                                <Field label={t('ledger.name_en')} htmlFor="a-en" error={form.errors.name_en}>
                                    <Input id="a-en" value={form.data.name_en} onChange={(e) => form.setData('name_en', e.target.value)} required />
                                </Field>
                                <Field label={t('ledger.name_ar')} htmlFor="a-ar" error={form.errors.name_ar}>
                                    <Input id="a-ar" dir="rtl" value={form.data.name_ar} onChange={(e) => form.setData('name_ar', e.target.value)} />
                                </Field>
                                <Field label={t('ledger.type')} htmlFor="a-type" error={form.errors.type}>
                                    <Select id="a-type" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                                        {types.map((ty) => (
                                            <option key={ty} value={ty}>
                                                {t(`fin.type_${ty}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                {form.data.type === 'expense' ? (
                                    <Field label={t('ledger.behaviour')} htmlFor="a-sub" hint={t('ledger.behaviour_hint')}>
                                        <Select id="a-sub" value={form.data.subtype} onChange={(e) => form.setData('subtype', e.target.value)}>
                                            <option value="fixed">{t('ledger.sub_fixed')}</option>
                                            <option value="variable">{t('ledger.sub_variable')}</option>
                                        </Select>
                                    </Field>
                                ) : null}
                                <div className="flex gap-2 sm:col-span-2 xl:col-span-5">
                                    <Button type="submit" disabled={form.processing}>
                                        {t('ledger.add_account')}
                                    </Button>
                                    <Button variant="ghost" onClick={() => setAdding(false)}>
                                        {t('expenses.cancel')}
                                    </Button>
                                </div>
                            </form>
                        </CardBody>
                    </Card>
                ) : (
                    <Button onClick={() => setAdding(true)}>
                        <Plus className="h-4 w-4" aria-hidden />
                        {t('ledger.add_account')}
                    </Button>
                )
            ) : null}

            <div className="grid gap-6 lg:grid-cols-2">
                {types.map((type) => {
                    const list = accounts.filter((a) => a.type === type);
                    return (
                        <Card key={type}>
                            <CardHeader title={t(`fin.type_${type}`)} description={t(`ledger.type_hint_${type}`)} />
                            <CardBody className="pt-0">
                                <ul className="divide-y divide-line">
                                    {list.map((a) => (
                                        <li key={a.id} className={cn('flex min-h-[44px] items-center gap-3 text-sm', !a.is_active && 'opacity-50')}>
                                            <span dir="ltr" className="w-12 shrink-0 font-mono text-xs text-ink-muted">
                                                {a.code}
                                            </span>
                                            <Link href={`/finance/ledger?account=${a.id}&period=ytd`} className="min-w-0 flex-1 truncate hover:underline">
                                                {name(a)}
                                            </Link>
                                            {a.subtype === 'variable' || a.subtype === 'cogs' ? <Badge tone="info">{t('ledger.sub_variable')}</Badge> : null}
                                            <Amount value={a.balance ?? 0} />
                                        </li>
                                    ))}
                                </ul>
                            </CardBody>
                        </Card>
                    );
                })}
            </div>
            {trial ? (
                <p className="text-caption text-ink-muted">
                    {t('ledger.trial_line')} <Amount value={trial.debit} /> / <Amount value={trial.credit} /> — {t(trial.balanced ? 'reports.balanced' : 'reports.not_balanced')}
                </p>
            ) : null}
        </div>
    );
}

function SettingsForm({ settings, manage }: { settings: Settings; manage: boolean }) {
    const t = useT();
    const form = useForm({
        vat_registered: settings.vat_registered,
        vat_rate: String(settings.vat_rate_bps / 100),
        gateway_fee_pct: String(settings.gateway_fee_bps / 100),
        gateway_fee_fixed: String(settings.gateway_fee_fixed / 100),
        cash_safety_level: String(settings.cash_safety_level / 100),
    });
    return (
        <Card className="max-w-3xl">
            <CardHeader title={t('ledger.settings_title')} description={t('ledger.settings_hint')} />
            <CardBody>
                <form
                    className="space-y-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put('/finance/settings', { preserveScroll: true });
                    }}
                >
                    <fieldset disabled={!manage} className="space-y-6">
                        <div className="space-y-3">
                            <Checkbox label={t('ledger.vat_registered')} checked={form.data.vat_registered} onChange={(e) => form.setData('vat_registered', e.target.checked)} />
                            <p className="text-sm text-ink-muted">{t('ledger.vat_hint')}</p>
                            {form.data.vat_registered ? (
                                <Field label={t('ledger.vat_rate')} htmlFor="vat_rate" error={form.errors.vat_rate} className="max-w-[12rem]">
                                    <Input id="vat_rate" inputMode="decimal" dir="ltr" value={form.data.vat_rate} onChange={(e) => form.setData('vat_rate', e.target.value)} />
                                </Field>
                            ) : null}
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('ledger.gateway_pct')} htmlFor="gw_pct" error={form.errors.gateway_fee_pct} hint={t('ledger.gateway_hint')}>
                                <Input id="gw_pct" inputMode="decimal" dir="ltr" value={form.data.gateway_fee_pct} onChange={(e) => form.setData('gateway_fee_pct', e.target.value)} />
                            </Field>
                            <Field label={t('ledger.gateway_fixed')} htmlFor="gw_fixed" error={form.errors.gateway_fee_fixed}>
                                <Input id="gw_fixed" inputMode="decimal" dir="ltr" value={form.data.gateway_fee_fixed} onChange={(e) => form.setData('gateway_fee_fixed', e.target.value)} />
                            </Field>
                            <Field label={t('ledger.safety')} htmlFor="safety" error={form.errors.cash_safety_level} hint={t('ledger.safety_hint')}>
                                <Input id="safety" inputMode="decimal" dir="ltr" value={form.data.cash_safety_level} onChange={(e) => form.setData('cash_safety_level', e.target.value)} />
                            </Field>
                        </div>
                        {manage ? (
                            <Button type="submit" disabled={form.processing}>
                                {t('ledger.save_settings')}
                            </Button>
                        ) : null}
                    </fieldset>
                </form>
            </CardBody>
        </Card>
    );
}
