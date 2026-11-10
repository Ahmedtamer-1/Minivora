// Financial statements for any period with a comparison column, CSV export and print / save as PDF.
// UC-11, FR-LED-04, FR-LED-08
import { router, usePage } from '@inertiajs/react';
import { CheckCircle2, FileSpreadsheet, Printer, XCircle } from 'lucide-react';
import { FilterTabs } from '@/components/commerce';
import { Amount, PeriodPicker, SectionTitle, StatementRows, TotalRow, useAccountName, type AccountLine, type Period, type ProfitAndLoss } from '@/components/finance';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/input';
import { DashboardLayout, PageHeader } from '@/layouts/dashboard-layout';
import { formatDate, useLocale, useT } from '@/lib/i18n';

type Report = 'pl' | 'balance' | 'cash' | 'trial' | 'vat';

interface BalanceSheet {
    as_of: string;
    assets: AccountLine[];
    total_assets: number;
    liabilities: AccountLine[];
    total_liabilities: number;
    equity: AccountLine[];
    profit_to_date: number;
    total_equity: number;
    balanced: boolean;
}
interface CashFlow {
    opening: number;
    closing: number;
    net: number;
    operating: AccountLine[];
    total_operating: number;
    investing: AccountLine[];
    total_investing: number;
    financing: AccountLine[];
    total_financing: number;
    inflow: number;
    outflow: number;
}
interface Trial {
    as_of: string;
    rows: Array<{ account_id: number; code: string; name_en: string; name_ar: string; debit: number; credit: number }>;
    debit: number;
    credit: number;
    balanced: boolean;
}
interface Vat {
    output: number;
    input: number;
    net: number;
    sales_net: number;
    purchases_net: number;
}

interface Props {
    report: Report;
    period: Period & { prev_from: string; prev_to: string };
    compare: boolean;
    data: ProfitAndLoss | BalanceSheet | CashFlow | Trial | Vat;
    previous: ProfitAndLoss | BalanceSheet | CashFlow | Vat | null;
    vatRegistered: boolean;
}

export default function FinanceReports({ report, period, compare, data, previous, vatRegistered }: Props) {
    const t = useT();
    const locale = useLocale();
    const { url } = usePage();
    const base = url.split('?')[0];
    const params = { report, period: period.key, from: period.key === 'custom' ? period.from : undefined, to: period.key === 'custom' ? period.to : undefined, compare: compare ? undefined : 0 };
    const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined).map(([k, v]) => [k, String(v)])).toString();
    const showPrev = previous !== null && report !== 'trial';
    const range = report === 'balance' || report === 'trial' ? t('reports.as_of', { date: formatDate(period.to, locale) }) : `${formatDate(period.from, locale)} – ${formatDate(period.to, locale)}`;

    return (
        <DashboardLayout title={t('nav.finance_reports')}>
            <PageHeader
                title={t('reports.title')}
                description={t('reports.subtitle')}
                action={
                    <>
                        <a href={`/finance/reports/export?${qs}`} className="inline-flex h-10 items-center gap-2 rounded-full bg-secondary px-4 font-display text-sm font-semibold text-secondary-fg hover:bg-secondary-hover">
                            <FileSpreadsheet className="h-4 w-4" aria-hidden />
                            {t('reports.excel')}
                        </a>
                        <Button variant="secondary" onClick={() => window.print()}>
                            <Printer className="h-4 w-4" aria-hidden />
                            {t('reports.pdf')}
                        </Button>
                    </>
                }
            />

            <div className="print:hidden">
                <FilterTabs
                    label={t('reports.which')}
                    active={report}
                    onChange={(k) => router.get(base, { ...params, report: k }, { preserveScroll: true, preserveState: true, replace: true })}
                    options={(['pl', 'balance', 'cash', 'trial', 'vat'] as Report[]).map((k) => ({ key: k, label: t(`reports.${k}`) }))}
                />
                <div className="mb-6 flex flex-wrap items-center gap-4">
                    <PeriodPicker period={period} url={base} params={{ report, compare: compare ? undefined : 0 }} />
                    {report !== 'trial' ? (
                        <Checkbox
                            label={t('reports.compare')}
                            checked={compare}
                            onChange={(e) => router.get(base, { ...params, compare: e.target.checked ? 1 : 0 }, { preserveScroll: true, preserveState: true, replace: true })}
                        />
                    ) : null}
                </div>
            </div>

            <Card className="max-w-4xl">
                <CardBody>
                    <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="font-display text-lg">{t(`reports.${report}`)}</h2>
                            <p className="text-sm text-ink-muted">{range}</p>
                        </div>
                        {'balanced' in data ? (
                            <Badge tone={data.balanced ? 'success' : 'danger'}>
                                {data.balanced ? <CheckCircle2 className="h-3.5 w-3.5" aria-hidden /> : <XCircle className="h-3.5 w-3.5" aria-hidden />}
                                {t(data.balanced ? 'reports.balanced' : 'reports.not_balanced')}
                            </Badge>
                        ) : null}
                    </div>

                    {showPrev ? (
                        <div className="hidden grid-cols-[minmax(0,1fr)_9rem_9rem] gap-3 border-b border-line pb-2 text-end text-caption text-ink-muted sm:grid">
                            <span />
                            <span>{t('reports.this_period')}</span>
                            <span>{t('reports.previous_period')}</span>
                        </div>
                    ) : null}

                    {report === 'pl' ? <PlStatement d={data as ProfitAndLoss} p={showPrev ? (previous as ProfitAndLoss) : null} /> : null}
                    {report === 'balance' ? <BalanceStatement d={data as BalanceSheet} p={showPrev ? (previous as BalanceSheet) : null} /> : null}
                    {report === 'cash' ? <CashStatement d={data as CashFlow} p={showPrev ? (previous as CashFlow) : null} /> : null}
                    {report === 'trial' ? <TrialStatement d={data as Trial} /> : null}
                    {report === 'vat' ? <VatStatement d={data as Vat} p={showPrev ? (previous as Vat) : null} registered={vatRegistered} /> : null}

                    <p className="mt-6 text-caption text-ink-muted">{t(`reports.note_${report}`)}</p>
                </CardBody>
            </Card>
        </DashboardLayout>
    );
}

function PlStatement({ d, p }: { d: ProfitAndLoss; p: ProfitAndLoss | null }) {
    const t = useT();
    const v = (n: number | undefined) => (p ? (n ?? 0) : undefined);
    return (
        <div>
            <SectionTitle>{t('reports.revenue')}</SectionTitle>
            <StatementRows rows={d.revenue} previous={p?.revenue} />
            {d.contra.length ? (
                <>
                    <SectionTitle>{t('reports.less_contra')}</SectionTitle>
                    <StatementRows rows={d.contra} previous={p?.contra} negate />
                </>
            ) : null}
            <TotalRow label={t('reports.row_net_revenue')} value={d.net_revenue} previous={v(p?.net_revenue)} />
            <SectionTitle>{t('reports.cogs')}</SectionTitle>
            <StatementRows rows={d.cogs} previous={p?.cogs} negate />
            <TotalRow label={`${t('reports.row_gross')}${d.gross_margin !== null ? ` · ${d.gross_margin}%` : ''}`} value={d.gross_profit} previous={v(p?.gross_profit)} />
            <SectionTitle>{t('reports.variable')}</SectionTitle>
            <StatementRows rows={d.variable} previous={p?.variable} negate />
            <TotalRow label={t('reports.row_contribution')} value={d.contribution} previous={v(p?.contribution)} />
            <SectionTitle>{t('reports.fixed')}</SectionTitle>
            <StatementRows rows={d.fixed} previous={p?.fixed} negate />
            <TotalRow label={`${t('reports.row_net')}${d.net_margin !== null ? ` · ${d.net_margin}%` : ''}`} value={d.net_profit} previous={v(p?.net_profit)} level={2} tone="profit" />
        </div>
    );
}

function BalanceStatement({ d, p }: { d: BalanceSheet; p: BalanceSheet | null }) {
    const t = useT();
    const v = (n: number | undefined) => (p ? (n ?? 0) : undefined);
    return (
        <div>
            <SectionTitle>{t('reports.row_assets')}</SectionTitle>
            <StatementRows rows={d.assets} previous={p?.assets} />
            <TotalRow label={t('reports.total_assets')} value={d.total_assets} previous={v(p?.total_assets)} level={2} />
            <SectionTitle>{t('reports.liabilities')}</SectionTitle>
            <StatementRows rows={d.liabilities} previous={p?.liabilities} />
            <TotalRow label={t('reports.total_liabilities')} value={d.total_liabilities} previous={v(p?.total_liabilities)} />
            <SectionTitle>{t('reports.row_equity')}</SectionTitle>
            <StatementRows rows={d.equity} previous={p?.equity} />
            <div className="grid min-h-[44px] grid-cols-[minmax(0,1fr)_auto] items-center gap-3 text-sm sm:grid-cols-[minmax(0,1fr)_9rem_9rem]">
                <span className="ps-3">{t('reports.profit_to_date')}</span>
                <Amount value={d.profit_to_date} className="text-end" />
                {p ? <Amount value={p.profit_to_date} className="hidden text-end text-ink-muted sm:block" /> : null}
            </div>
            <TotalRow label={t('reports.total_equity')} value={d.total_equity} previous={v(p?.total_equity)} />
            <TotalRow label={t('reports.row_liab_equity')} value={d.total_liabilities + d.total_equity} previous={p ? p.total_liabilities + p.total_equity : undefined} level={2} />
        </div>
    );
}

function CashStatement({ d, p }: { d: CashFlow; p: CashFlow | null }) {
    const t = useT();
    const v = (n: number | undefined) => (p ? (n ?? 0) : undefined);
    return (
        <div>
            <TotalRow label={t('reports.opening_cash')} value={d.opening} previous={v(p?.opening)} />
            {(['operating', 'investing', 'financing'] as const).map((k) => (
                <div key={k}>
                    <SectionTitle>{t(`reports.cf_${k}`)}</SectionTitle>
                    {d[k].length ? <StatementRows rows={d[k]} previous={p?.[k]} /> : <p className="py-2 ps-3 text-sm text-ink-muted">{t('reports.nothing')}</p>}
                    <TotalRow label={t(`reports.cf_total_${k}`)} value={d[`total_${k}`]} previous={v(p?.[`total_${k}`])} />
                </div>
            ))}
            <TotalRow label={t('reports.row_net_cash')} value={d.net} previous={v(p?.net)} />
            <TotalRow label={t('reports.closing_cash')} value={d.closing} previous={v(p?.closing)} level={2} />
            <div className="mt-4 flex flex-wrap gap-4 text-sm text-ink-muted">
                <span>
                    {t('reports.row_cash_in')}: <Amount value={d.inflow} />
                </span>
                <span>
                    {t('reports.row_cash_out')}: <Amount value={-d.outflow} />
                </span>
            </div>
        </div>
    );
}

function TrialStatement({ d }: { d: Trial }) {
    const t = useT();
    const name = useAccountName();
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <caption className="sr-only">{t('reports.trial')}</caption>
                <thead>
                    <tr className="border-b border-line text-caption text-ink-muted">
                        <th scope="col" className="py-2 text-start font-normal">
                            {t('ledger.col_account')}
                        </th>
                        <th scope="col" className="py-2 text-end font-normal">
                            {t('ledger.col_debit')}
                        </th>
                        <th scope="col" className="py-2 text-end font-normal">
                            {t('ledger.col_credit')}
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-line">
                    {d.rows.map((r) => (
                        <tr key={r.account_id}>
                            <td className="py-2.5">
                                <span dir="ltr" className="me-2 font-mono text-xs text-ink-muted">
                                    {r.code}
                                </span>
                                {name(r)}
                            </td>
                            <td className="py-2.5 text-end">{r.debit ? <Amount value={r.debit} /> : null}</td>
                            <td className="py-2.5 text-end">{r.credit ? <Amount value={r.credit} /> : null}</td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t-2 border-line font-semibold">
                        <td className="py-3">{t('reports.total')}</td>
                        <td className="py-3 text-end">
                            <Amount value={d.debit} />
                        </td>
                        <td className="py-3 text-end">
                            <Amount value={d.credit} />
                        </td>
                    </tr>
                </tfoot>
            </table>
            {d.rows.length === 0 ? <p className="py-6 text-center text-sm text-ink-muted">{t('reports.nothing')}</p> : null}
        </div>
    );
}

function VatStatement({ d, p, registered }: { d: Vat; p: Vat | null; registered: boolean }) {
    const t = useT();
    const v = (n: number | undefined) => (p ? (n ?? 0) : undefined);
    return (
        <div>
            {!registered ? <p className="mb-4 rounded-2xl bg-info-subtle p-3 text-sm text-info">{t('reports.vat_not_registered')}</p> : null}
            <TotalRow label={t('reports.vat_sales')} value={d.sales_net} previous={v(p?.sales_net)} />
            <TotalRow label={t('reports.vat_output')} value={d.output} previous={v(p?.output)} />
            <TotalRow label={t('reports.vat_purchases')} value={d.purchases_net} previous={v(p?.purchases_net)} />
            <TotalRow label={t('reports.vat_input')} value={-d.input} previous={p ? -p.input : undefined} />
            <TotalRow label={t(d.net >= 0 ? 'reports.vat_payable' : 'reports.vat_refundable')} value={d.net} previous={v(p?.net)} level={2} />
        </div>
    );
}
