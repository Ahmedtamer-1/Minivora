<?php

namespace App\Modules\Ledger\Services;

use App\Core\Tenancy\CurrentStore;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Models\JournalLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Financial statements computed from journal lines only, so every figure on screen, in an export
 * or in an AI answer traces back to posted entries (FR-LED-04, FR-AI-03). All amounts in piasters.
 *
 *   Profit and loss  net revenue − COGS = gross profit; − variable costs = contribution margin;
 *                    − fixed costs = net profit
 *   Balance sheet    assets = liabilities + equity, where equity includes all profit to date
 *                    (there is no year-end closing entry, so retained profit is computed)
 *   Cash flow        direct method over the money accounts (cash, bank, gateway clearing):
 *                    each money movement is attributed to the other accounts in its entry and
 *                    grouped as operating, investing or financing
 */
class StatementService
{
    public function __construct(
        private readonly CurrentStore $current,
        private readonly ChartOfAccounts $chart,
    ) {}

    /** @return Collection<string, Account> */
    public function accounts(): Collection
    {
        return $this->chart->all($this->storeId());
    }

    /**
     * Debit and credit totals per account id between two dates (inclusive). Null from = since the start.
     *
     * @return array<int, array{debit: int, credit: int}>
     */
    public function totals(?Carbon $from, Carbon $to): array
    {
        $query = JournalLine::query()
            ->selectRaw('account_id, SUM(debit) as d, SUM(credit) as c')
            ->where('entry_date', '<=', $to->toDateString())
            ->groupBy('account_id');
        if ($from !== null) {
            $query->where('entry_date', '>=', $from->toDateString());
        }

        $out = [];
        foreach ($query->get() as $row) {
            $out[(int) $row->account_id] = ['debit' => (int) $row->d, 'credit' => (int) $row->c];
        }

        return $out;
    }

    /** Signed balance (normal direction) of every account. @return array<int, int> */
    public function balances(?Carbon $from, Carbon $to): array
    {
        $totals = $this->totals($from, $to);
        $out = [];
        foreach ($this->accounts() as $account) {
            $t = $totals[$account->id] ?? ['debit' => 0, 'credit' => 0];
            $out[$account->id] = $account->signed($t['debit'], $t['credit']);
        }

        return $out;
    }

    /**
     * @return array{as_of: string, rows: list<array<string, mixed>>, debit: int, credit: int, balanced: bool}
     */
    public function trialBalance(Carbon $asOf): array
    {
        $totals = $this->totals(null, $asOf);
        $rows = [];
        $sumD = 0;
        $sumC = 0;
        foreach ($this->accounts() as $account) {
            $t = $totals[$account->id] ?? null;
            if ($t === null) {
                continue;
            }
            $net = $t['debit'] - $t['credit'];
            $rows[] = $this->accountRow($account) + ['debit' => max(0, $net), 'credit' => max(0, -$net)];
            $sumD += max(0, $net);
            $sumC += max(0, -$net);
        }

        return ['as_of' => $asOf->toDateString(), 'rows' => $rows, 'debit' => $sumD, 'credit' => $sumC, 'balanced' => $sumD === $sumC];
    }

    /**
     * @return array{
     *   from: string, to: string,
     *   revenue: list<array<string, mixed>>, gross_revenue: int, contra: list<array<string, mixed>>, net_revenue: int,
     *   cogs: list<array<string, mixed>>, total_cogs: int, gross_profit: int,
     *   variable: list<array<string, mixed>>, total_variable: int, contribution: int,
     *   fixed: list<array<string, mixed>>, total_fixed: int, net_profit: int,
     *   gross_margin: float|null, net_margin: float|null
     * }
     */
    public function profitAndLoss(Carbon $from, Carbon $to): array
    {
        $balances = $this->balances($from, $to);
        $groups = ['revenue' => [], 'contra' => [], 'cogs' => [], 'variable' => [], 'fixed' => []];

        foreach ($this->accounts() as $account) {
            if (! $account->type->isTemporary()) {
                continue;
            }
            $amount = $balances[$account->id] ?? 0;
            if ($account->type === AccountType::Revenue) {
                $group = $account->subtype === 'contra' ? 'contra' : 'revenue';
                // Contra revenue is debit-normal in practice: show what it took off revenue.
                if ($group === 'contra') {
                    $amount = -$amount;
                }
            } else {
                $group = match ($account->subtype) {
                    'cogs' => 'cogs',
                    'variable' => 'variable',
                    default => 'fixed',
                };
            }
            if ($amount !== 0) {
                $groups[$group][] = $this->accountRow($account) + ['amount' => $amount];
            }
        }

        $sum = fn (array $rows) => array_sum(array_column($rows, 'amount'));
        $gross = $sum($groups['revenue']);
        $net = $gross - $sum($groups['contra']);
        $cogs = $sum($groups['cogs']);
        $variable = $sum($groups['variable']);
        $fixed = $sum($groups['fixed']);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'revenue' => $groups['revenue'],
            'gross_revenue' => $gross,
            'contra' => $groups['contra'],
            'net_revenue' => $net,
            'cogs' => $groups['cogs'],
            'total_cogs' => $cogs,
            'gross_profit' => $net - $cogs,
            'variable' => $groups['variable'],
            'total_variable' => $variable,
            'contribution' => $net - $cogs - $variable,
            'fixed' => $groups['fixed'],
            'total_fixed' => $fixed,
            'net_profit' => $net - $cogs - $variable - $fixed,
            'gross_margin' => $net > 0 ? round(($net - $cogs) * 100 / $net, 1) : null,
            'net_margin' => $net > 0 ? round(($net - $cogs - $variable - $fixed) * 100 / $net, 1) : null,
        ];
    }

    /**
     * @return array{as_of: string, assets: list<array<string, mixed>>, total_assets: int, liabilities: list<array<string, mixed>>, total_liabilities: int, equity: list<array<string, mixed>>, profit_to_date: int, total_equity: int, balanced: bool}
     */
    public function balanceSheet(Carbon $asOf): array
    {
        $balances = $this->balances(null, $asOf);
        $sections = ['asset' => [], 'liability' => [], 'equity' => []];
        $profit = 0;

        foreach ($this->accounts() as $account) {
            $amount = $balances[$account->id] ?? 0;
            if ($account->type->isTemporary()) {
                $profit += $account->type === AccountType::Revenue ? $amount : -$amount;

                continue;
            }
            if ($amount !== 0) {
                $sections[$account->type->value][] = $this->accountRow($account) + ['amount' => $amount];
            }
        }

        $assets = array_sum(array_column($sections['asset'], 'amount'));
        $liabilities = array_sum(array_column($sections['liability'], 'amount'));
        $equity = array_sum(array_column($sections['equity'], 'amount')) + $profit;

        return [
            'as_of' => $asOf->toDateString(),
            'assets' => $sections['asset'],
            'total_assets' => $assets,
            'liabilities' => $sections['liability'],
            'total_liabilities' => $liabilities,
            'equity' => $sections['equity'],
            'profit_to_date' => $profit,
            'total_equity' => $equity,
            'balanced' => $assets === $liabilities + $equity,
        ];
    }

    /** Cash, bank and gateway clearing together, at the end of a day. */
    public function moneyBalance(Carbon $asOf): int
    {
        $balances = $this->balances(null, $asOf);

        return (int) $this->accounts()->filter(fn (Account $a) => in_array($a->subtype, ChartOfAccounts::MONEY_SUBTYPES, true))
            ->sum(fn (Account $a) => $balances[$a->id] ?? 0);
    }

    /**
     * @return array{from: string, to: string, opening: int, closing: int, net: int,
     *   operating: list<array<string, mixed>>, total_operating: int,
     *   investing: list<array<string, mixed>>, total_investing: int,
     *   financing: list<array<string, mixed>>, total_financing: int,
     *   inflow: int, outflow: int}
     */
    public function cashFlow(Carbon $from, Carbon $to): array
    {
        $accounts = $this->accounts()->keyBy('id');
        $isMoney = fn (int $id) => in_array($accounts->get($id)?->subtype, ChartOfAccounts::MONEY_SUBTYPES, true);

        $byAccount = [];
        $inflow = 0;
        $outflow = 0;

        $entries = JournalEntry::query()->with('lines')
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('lines', fn ($q) => $q->whereIn('account_id', $accounts->filter(fn ($a) => in_array($a->subtype, ChartOfAccounts::MONEY_SUBTYPES, true))->keys()))
            ->get();

        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                if ($isMoney($line->account_id)) {
                    $inflow += $line->debit;
                    $outflow += $line->credit;

                    continue;
                }
                // Money moved by this line = the opposite of its own debit/credit.
                $effect = $line->credit - $line->debit;
                $byAccount[$line->account_id] = ($byAccount[$line->account_id] ?? 0) + $effect;
            }
        }

        $sections = ['operating' => [], 'investing' => [], 'financing' => []];
        foreach ($byAccount as $id => $amount) {
            $account = $accounts->get($id);
            if ($account === null || $amount === 0) {
                continue;
            }
            $section = match (true) {
                in_array($account->subtype, ['fixed_asset'], true) || $account->code === '1590' => 'investing',
                $account->type === AccountType::Equity || $account->subtype === 'loan' => 'financing',
                default => 'operating',
            };
            $sections[$section][] = $this->accountRow($account) + ['amount' => $amount];
        }
        foreach ($sections as &$rows) {
            usort($rows, fn ($a, $b) => strcmp($a['code'], $b['code']));
        }
        unset($rows);

        $opening = $this->moneyBalance($from->copy()->subDay());
        $closing = $this->moneyBalance($to);
        $sum = fn (array $rows) => (int) array_sum(array_column($rows, 'amount'));

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'opening' => $opening,
            'closing' => $closing,
            'net' => $closing - $opening,
            'operating' => $sections['operating'],
            'total_operating' => $sum($sections['operating']),
            'investing' => $sections['investing'],
            'total_investing' => $sum($sections['investing']),
            'financing' => $sections['financing'],
            'total_financing' => $sum($sections['financing']),
            'inflow' => $inflow,
            'outflow' => $outflow,
        ];
    }

    /**
     * VAT for a period, as the monthly return needs it: VAT charged on sales (less refunds) against
     * VAT paid on purchases. Settlements with the Tax Authority are manual entries and left out.
     *
     * @return array{from: string, to: string, output: int, input: int, net: int, sales_net: int, purchases_net: int}
     */
    public function vat(Carbon $from, Carbon $to): array
    {
        $sumOn = function (string $code, string $sideExpr) use ($from, $to): int {
            return (int) JournalLine::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_lines.account_id', $this->chart->get($this->storeId(), $code)->id)
                ->whereBetween('journal_lines.entry_date', [$from->toDateString(), $to->toDateString()])
                ->where('journal_entries.source_type', '!=', 'manual')
                ->sum($sideExpr);
        };

        $output = $sumOn(ChartOfAccounts::VAT_OUT, 'journal_lines.credit') - $sumOn(ChartOfAccounts::VAT_OUT, 'journal_lines.debit');
        $input = $sumOn(ChartOfAccounts::VAT_IN, 'journal_lines.debit') - $sumOn(ChartOfAccounts::VAT_IN, 'journal_lines.credit');
        $pl = $this->profitAndLoss($from, $to);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'output' => $output,
            'input' => $input,
            'net' => $output - $input,
            'sales_net' => $pl['net_revenue'],
            'purchases_net' => $pl['total_variable'] + $pl['total_fixed'],
        ];
    }

    /**
     * Revenue, costs, profit and closing cash for each of the last $months calendar months
     * (oldest first), in two queries.
     *
     * @return list<array{month: string, revenue: int, costs: int, profit: int, cash: int}>
     */
    public function monthly(int $months, ?Carbon $until = null): array
    {
        $until = ($until ?? Carbon::now('Africa/Cairo'))->copy()->endOfMonth();
        $start = $until->copy()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $accounts = $this->accounts()->keyBy('id');

        $rows = JournalLine::query()
            ->selectRaw('account_id, SUBSTR(entry_date, 1, 7) as ym, SUM(debit) as d, SUM(credit) as c')
            ->whereBetween('entry_date', [$start->toDateString(), $until->toDateString()])
            ->groupBy('account_id', 'ym')
            ->get();

        $series = [];
        for ($m = $start->copy(); $m <= $until; $m->addMonthNoOverflow()) {
            $series[$m->format('Y-m')] = ['month' => $m->format('Y-m'), 'revenue' => 0, 'costs' => 0, 'profit' => 0, 'cash' => 0, 'money_change' => 0];
        }

        foreach ($rows as $row) {
            $account = $accounts->get((int) $row->account_id);
            $ym = (string) $row->ym;
            if ($account === null || ! isset($series[$ym])) {
                continue;
            }
            $d = (int) $row->d;
            $c = (int) $row->c;
            if ($account->type === AccountType::Revenue) {
                $series[$ym]['revenue'] += $c - $d;
            } elseif ($account->type === AccountType::Expense) {
                $series[$ym]['costs'] += $d - $c;
            }
            if (in_array($account->subtype, ChartOfAccounts::MONEY_SUBTYPES, true)) {
                $series[$ym]['money_change'] += $d - $c;
            }
        }

        $cash = $this->moneyBalance($start->copy()->subDay());
        $out = [];
        foreach ($series as $row) {
            $cash += $row['money_change'];
            $out[] = ['month' => $row['month'], 'revenue' => $row['revenue'], 'costs' => $row['costs'], 'profit' => $row['revenue'] - $row['costs'], 'cash' => $cash, 'cash_change' => $row['money_change']];
        }

        return $out;
    }

    /**
     * One account's lines with a running balance (general ledger drill-down).
     *
     * @return array{account: array<string, mixed>, opening: int, closing: int, lines: list<array<string, mixed>>, debit: int, credit: int}
     */
    public function accountActivity(Account $account, Carbon $from, Carbon $to, int $limit = 300): array
    {
        $before = $this->totals(null, $from->copy()->subDay())[$account->id] ?? ['debit' => 0, 'credit' => 0];
        $running = $account->signed($before['debit'], $before['credit']);
        $opening = $running;

        $lines = JournalLine::query()->with('entry')
            ->where('account_id', $account->id)
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('entry_date')->orderBy('journal_entry_id')->orderBy('id')
            ->limit($limit)
            ->get();

        $rows = [];
        $d = 0;
        $c = 0;
        foreach ($lines as $line) {
            $running += $account->signed($line->debit, $line->credit);
            $d += $line->debit;
            $c += $line->credit;
            $rows[] = [
                'id' => $line->id,
                'date' => $line->entry_date->toDateString(),
                'entry_id' => $line->journal_entry_id,
                'number' => $line->entry?->number,
                'memo' => $line->memo ?? $line->entry?->memo,
                'source_type' => $line->entry?->source_type,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'balance' => $running,
            ];
        }

        return ['account' => $account->toRow(), 'opening' => $opening, 'closing' => $running, 'lines' => $rows, 'debit' => $d, 'credit' => $c];
    }

    /** How many orders were paid in the period (sale entries), for average order value. */
    public function paidOrders(Carbon $from, Carbon $to): int
    {
        return JournalEntry::query()->where('source_type', 'order')->where('source_key', 'like', '%:sale')
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])->count();
    }

    /** @return array<string, mixed> */
    private function accountRow(Account $account): array
    {
        return ['account_id' => $account->id, 'code' => $account->code, 'name_en' => $account->name_en, 'name_ar' => $account->name_ar, 'subtype' => $account->subtype];
    }

    private function storeId(): int
    {
        return (int) $this->current->getOrFail()->getKey();
    }
}
