// Expenses and bills: record a cost paid now or later, pay bills, void mistakes. Every action posts
// to the ledger. UC-10, FR-LED-05
import { router, useForm, usePage } from '@inertiajs/react';
import { Ban, CheckCircle2, Plus, Receipt, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { FilterTabs, Pagination, StatTile } from '@/components/commerce';
import { Amount, EmptyFinance, PeriodPicker, useAccountName, useMoney, type Period } from '@/components/finance';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Checkbox, Field, Input, Select } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { cn } from '@/lib/cn';
import { formatDate, useLocale, useT } from '@/lib/i18n';
import { useCan } from '@/lib/permissions';
import type { Paginated } from '@/types';

interface ExpenseRow {
    id: number;
    date: string;
    vendor: string | null;
    description: string;
    account_id: number;
    account: string | null;
    account_code: string | null;
    paid_from: 'cash' | 'bank' | 'card' | 'payable';
    amount: number;
    vat: number;
    due_date: string | null;
    paid_at: string | null;
    paid_with: string | null;
    unpaid: boolean;
    overdue: boolean;
    voided: boolean;
    entry_id: number | null;
}

interface ExpenseAccount {
    id: number;
    code: string;
    name_en: string;
    name_ar: string;
    subtype: string;
}

interface Props {
    period: Period;
    filter: 'all' | 'unpaid' | 'paid' | 'voided';
    accountFilter: number | null;
    expenses: Paginated<ExpenseRow>;
    accounts: ExpenseAccount[];
    summary: { total: number; vat: number; count: number; unpaid: number; unpaid_count: number; overdue: number; by_account: Record<string, number> };
    today: string;
}

export default function FinanceExpenses({ period, filter, accountFilter, expenses, accounts, summary, today }: Props) {
    const t = useT();
    const locale = useLocale();
    const money = useMoney();
    const name = useAccountName();
    const can = useCan();
    const manage = can('ledger.manage');
    const { url } = usePage();
    const base = url.split('?')[0];
    const [adding, setAdding] = useState(false);
    const [paying, setPaying] = useState<ExpenseRow | null>(null);
    const params = { period: period.key, from: period.key === 'custom' ? period.from : undefined, to: period.key === 'custom' ? period.to : undefined, account: accountFilter ?? undefined };
    const top = Object.entries(summary.by_account)
        .map(([id, v]) => ({ id: Number(id), v }))
        .sort((a, b) => b.v - a.v)
        .slice(0, 5);

    return (
        <DashboardLayout title={t('nav.finance_expenses')}>
            <PageHeader
                title={t('expenses.title')}
                description={t('expenses.subtitle')}
                action={
                    manage ? (
                        <Button onClick={() => setAdding((v) => !v)} aria-expanded={adding}>
                            {adding ? <X className="h-4 w-4" aria-hidden /> : <Plus className="h-4 w-4" aria-hidden />}
                            {adding ? t('expenses.cancel') : t('expenses.add')}
                        </Button>
                    ) : null
                }
            />

            {adding ? <ExpenseForm accounts={accounts} today={today} onDone={() => setAdding(false)} /> : null}

            <div className="mb-6 grid grid-cols-3 gap-2 sm:gap-4">
                <StatTile icon={Receipt} label={t('expenses.in_period')} value={money(summary.total)} hint={t('expenses.count', { count: summary.count })} />
                <StatTile label={t('expenses.vat_paid')} value={money(summary.vat)} hint={t('expenses.vat_hint')} />
                <StatTile label={t('expenses.unpaid_bills')} value={money(summary.unpaid)} tone={summary.overdue > 0 ? 'danger' : summary.unpaid_count > 0 ? 'warning' : 'default'} hint={summary.overdue > 0 ? t('expenses.overdue', { count: summary.overdue }) : t('expenses.count', { count: summary.unpaid_count })} />
            </div>

            <div className="mb-2 flex flex-wrap items-center justify-between gap-3">
                <FilterTabs
                    label={t('expenses.filter')}
                    active={filter}
                    onChange={(k) => router.get(base, { ...params, filter: k }, { preserveScroll: true, preserveState: true, replace: true })}
                    options={(['all', 'unpaid', 'paid', 'voided'] as const).map((k) => ({ key: k, label: t(`expenses.filter_${k}`), count: k === 'unpaid' ? summary.unpaid_count : undefined }))}
                />
                <div className="mb-6 flex flex-wrap items-center gap-2">
                    <PeriodPicker period={period} url={base} params={{ filter, account: accountFilter ?? undefined }} />
                    <Select
                        aria-label={t('expenses.col_account')}
                        value={accountFilter ?? ''}
                        onChange={(e) => router.get(base, { ...params, filter, account: e.target.value || undefined }, { preserveScroll: true, preserveState: true, replace: true })}
                        className="!w-auto rounded-full"
                    >
                        <option value="">{t('expenses.all_categories')}</option>
                        {accounts.map((a) => (
                            <option key={a.id} value={a.id}>
                                {name(a)}
                            </option>
                        ))}
                    </Select>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <Card>
                    {expenses.data.length === 0 ? (
                        <EmptyFinance icon={Receipt} title={t('expenses.empty_title')} body={t('expenses.empty_body')} />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <caption className="sr-only">{t('expenses.list')}</caption>
                                <thead>
                                    <tr className="border-b border-line text-caption text-ink-muted">
                                        <th scope="col" className="px-4 py-3 text-start font-normal sm:px-6">
                                            {t('expenses.col_what')}
                                        </th>
                                        <th scope="col" className="hidden px-3 py-3 text-start font-normal md:table-cell">
                                            {t('expenses.col_account')}
                                        </th>
                                        <th scope="col" className="hidden px-3 py-3 text-start font-normal sm:table-cell">
                                            {t('expenses.col_status')}
                                        </th>
                                        <th scope="col" className="px-4 py-3 text-end font-normal sm:px-6">
                                            {t('expenses.col_amount')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {expenses.data.map((e) => (
                                        <tr key={e.id} className={cn(e.voided && 'opacity-60')}>
                                            <td className="px-4 py-3 sm:px-6">
                                                <p className={cn('font-medium', e.voided && 'line-through')}>{e.description}</p>
                                                <p className="text-caption text-ink-muted">
                                                    {formatDate(e.date, locale)}
                                                    {e.vendor ? ` · ${e.vendor}` : ''}
                                                    <span className="md:hidden"> · {e.account}</span>
                                                </p>
                                            </td>
                                            <td className="hidden px-3 py-3 md:table-cell">{e.account}</td>
                                            <td className="hidden px-3 py-3 sm:table-cell">
                                                {e.voided ? (
                                                    <Badge>{t('expenses.voided')}</Badge>
                                                ) : e.unpaid ? (
                                                    <Badge tone={e.overdue ? 'danger' : 'warning'}>{e.due_date ? t(e.overdue ? 'expenses.overdue_since' : 'expenses.due_on', { date: formatDate(e.due_date, locale) }) : t('expenses.unpaid')}</Badge>
                                                ) : (
                                                    <Badge tone="success">{t(`expenses.paid_${e.paid_with ?? e.paid_from}`)}</Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-end sm:px-6">
                                                <Amount value={e.amount} className="font-medium" />
                                                {e.vat > 0 ? <p className="text-caption text-ink-muted">{t('expenses.incl_vat', { amount: money(e.vat) })}</p> : null}
                                                {manage && !e.voided ? (
                                                    <div className="mt-1 flex justify-end gap-1">
                                                        {e.unpaid ? (
                                                            <Button size="sm" variant="secondary" onClick={() => setPaying(e)}>
                                                                <CheckCircle2 className="h-3.5 w-3.5" aria-hidden />
                                                                {t('expenses.pay')}
                                                            </Button>
                                                        ) : null}
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            aria-label={t('expenses.void_label', { what: e.description })}
                                                            onClick={() => window.confirm(t('expenses.void_confirm')) && router.post(`/finance/expenses/${e.id}/void`, {}, { preserveScroll: true })}
                                                        >
                                                            <Ban className="h-3.5 w-3.5" aria-hidden />
                                                            <span className="hidden sm:inline">{t('expenses.void')}</span>
                                                        </Button>
                                                    </div>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <div className="space-y-6">
                    {paying ? <PayBill expense={paying} today={today} onDone={() => setPaying(null)} /> : null}
                    <Card>
                        <CardHeader title={t('expenses.by_category')} />
                        <CardBody>
                            {top.length ? (
                                <ul className="space-y-2.5 text-sm">
                                    {top.map(({ id, v }) => {
                                        const a = accounts.find((x) => x.id === id);
                                        return (
                                            <li key={id} className="flex items-center justify-between gap-3">
                                                <button type="button" className="truncate text-start hover:underline" onClick={() => router.get(base, { ...params, filter, account: id }, { preserveScroll: true })}>
                                                    {a ? name(a) : `#${id}`}
                                                </button>
                                                <Amount value={v} />
                                            </li>
                                        );
                                    })}
                                </ul>
                            ) : (
                                <p className="text-sm text-ink-muted">{t('expenses.nothing_yet')}</p>
                            )}
                        </CardBody>
                    </Card>
                    <Card>
                        <CardBody>
                            <p className="text-sm font-semibold">{t('expenses.how_title')}</p>
                            <ul className="mt-2 list-disc space-y-1.5 ps-5 text-sm text-ink-muted">
                                <li>{t('expenses.how_1')}</li>
                                <li>{t('expenses.how_2')}</li>
                                <li>{t('expenses.how_3')}</li>
                            </ul>
                        </CardBody>
                    </Card>
                </div>
            </div>
            <Pagination page={expenses} />
        </DashboardLayout>
    );
}

function ExpenseForm({ accounts, today, onDone }: { accounts: ExpenseAccount[]; today: string; onDone: () => void }) {
    const t = useT();
    const name = useAccountName();
    const form = useForm({
        expense_date: today,
        description: '',
        vendor: '',
        account_id: '' as number | '',
        amount: '',
        vat: '',
        paid_from: 'cash' as 'cash' | 'bank' | 'card' | 'payable',
        due_date: '',
    });
    const [withVat, setWithVat] = useState(false);
    const groups = [
        ['variable', accounts.filter((a) => a.subtype === 'variable')],
        ['fixed', accounts.filter((a) => a.subtype !== 'variable')],
    ] as const;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, vat: withVat ? d.vat : '', due_date: d.paid_from === 'payable' ? d.due_date : '' }));
        form.post('/finance/expenses', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onDone();
            },
        });
    };
    // 14% VAT inside a VAT-inclusive receipt: amount × 14 / 114.
    const suggestVat = () => {
        const n = Number(form.data.amount.replace(/,/g, ''));
        if (n > 0) form.setData('vat', ((n * 14) / 114).toFixed(2));
    };

    return (
        <Card className="mb-6">
            <CardHeader title={t('expenses.new')} description={t('expenses.new_hint')} />
            <CardBody>
                <form onSubmit={submit} className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Field label={t('expenses.f_description')} htmlFor="description" error={form.errors.description}>
                        <Input id="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder={t('expenses.f_description_ph')} required />
                    </Field>
                    <Field label={t('expenses.col_account')} htmlFor="account_id" error={form.errors.account_id}>
                        <Select id="account_id" value={form.data.account_id} onChange={(e) => form.setData('account_id', e.target.value === '' ? '' : Number(e.target.value))} required>
                            <option value="">{t('fin.choose_account')}</option>
                            {groups.map(([g, list]) => (
                                <optgroup key={g} label={t(`expenses.group_${g}`)}>
                                    {list.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {name(a)}
                                        </option>
                                    ))}
                                </optgroup>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('expenses.f_vendor')} htmlFor="vendor" error={form.errors.vendor}>
                        <Input id="vendor" value={form.data.vendor} onChange={(e) => form.setData('vendor', e.target.value)} />
                    </Field>
                    <Field label={t('expenses.f_amount')} htmlFor="amount" error={form.errors.amount} hint={t('expenses.f_amount_hint')}>
                        <Input id="amount" inputMode="decimal" dir="ltr" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} placeholder="0.00" required />
                    </Field>
                    <Field label={t('expenses.f_date')} htmlFor="expense_date" error={form.errors.expense_date}>
                        <Input id="expense_date" type="date" value={form.data.expense_date} onChange={(e) => form.setData('expense_date', e.target.value)} required />
                    </Field>
                    <Field label={t('expenses.f_paid_from')} htmlFor="paid_from" error={form.errors.paid_from}>
                        <Select id="paid_from" value={form.data.paid_from} onChange={(e) => form.setData('paid_from', e.target.value as typeof form.data.paid_from)}>
                            {(['cash', 'bank', 'card', 'payable'] as const).map((k) => (
                                <option key={k} value={k}>
                                    {t(`expenses.from_${k}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    {form.data.paid_from === 'payable' ? (
                        <Field label={t('expenses.f_due')} htmlFor="due_date" error={form.errors.due_date}>
                            <Input id="due_date" type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                        </Field>
                    ) : null}
                    <div className="space-y-2 md:col-span-2 xl:col-span-3">
                        <Checkbox label={t('expenses.has_vat')} checked={withVat} onChange={(e) => setWithVat(e.target.checked)} />
                        {withVat ? (
                            <div className="flex max-w-md items-end gap-2">
                                <Field label={t('expenses.f_vat')} htmlFor="vat" error={form.errors.vat} className="flex-1">
                                    <Input id="vat" inputMode="decimal" dir="ltr" value={form.data.vat} onChange={(e) => form.setData('vat', e.target.value)} />
                                </Field>
                                <Button variant="secondary" onClick={suggestVat}>
                                    {t('expenses.vat_14')}
                                </Button>
                            </div>
                        ) : null}
                    </div>
                    <div className="flex gap-2 md:col-span-2 xl:col-span-3">
                        <Button type="submit" disabled={form.processing}>
                            {t('expenses.save')}
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

function PayBill({ expense, today, onDone }: { expense: ExpenseRow; today: string; onDone: () => void }) {
    const t = useT();
    const form = useForm({ paid_with: 'bank' as 'cash' | 'bank', paid_on: today });
    return (
        <Card>
            <CardHeader title={t('expenses.pay_title')} description={expense.description} />
            <CardBody>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`/finance/expenses/${expense.id}/pay`, { preserveScroll: true, onSuccess: onDone });
                    }}
                >
                    <p className="text-sm">
                        <Amount value={expense.amount} className="font-display text-lg" />
                    </p>
                    <Field label={t('expenses.pay_with')} htmlFor="paid_with" error={form.errors.paid_with}>
                        <Select id="paid_with" value={form.data.paid_with} onChange={(e) => form.setData('paid_with', e.target.value as 'cash' | 'bank')}>
                            <option value="bank">{t('expenses.from_bank')}</option>
                            <option value="cash">{t('expenses.from_cash')}</option>
                        </Select>
                    </Field>
                    <Field label={t('expenses.pay_on')} htmlFor="paid_on" error={form.errors.paid_on}>
                        <Input id="paid_on" type="date" value={form.data.paid_on} onChange={(e) => form.setData('paid_on', e.target.value)} />
                    </Field>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {t('expenses.pay')}
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
