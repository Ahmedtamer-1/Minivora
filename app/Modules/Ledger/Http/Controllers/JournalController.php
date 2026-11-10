<?php

namespace App\Modules\Ledger\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Http\Controllers\Concerns\ReadsFinanceInput;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Models\JournalLine;
use App\Modules\Ledger\Services\ChartOfAccounts;
use App\Modules\Ledger\Services\FinanceSettings;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Ledger\Services\PostingRules;
use App\Modules\Ledger\Services\StatementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * General ledger: the journal, the chart of accounts with balances, one account's activity with a
 * running balance, manual journal entries, reversals, transfers between money accounts (gateway
 * payouts) and finance settings. FR-LED-01..03, FR-LED-06
 */
class JournalController extends Controller
{
    use ReadsFinanceInput;

    public const TABS = ['journal', 'accounts', 'settings'];

    public const SOURCES = ['order', 'expense', 'manual', 'adjustment', 'reversal'];

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly StatementService $statements,
        private readonly FinanceSettings $settings,
        private readonly ChartOfAccounts $chart,
        private readonly CurrentStore $current,
    ) {}

    public function index(Request $request): Response
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'journal';
        $accounts = $this->statements->accounts();
        $today = Carbon::now('Africa/Cairo');
        $balances = $this->statements->balances(null, $today);

        $props = [
            'tab' => $tab,
            'accounts' => $accounts->values()->map(fn (Account $a) => $a->toRow() + ['balance' => $balances[$a->id] ?? 0])->all(),
            'settings' => $this->settings->current()->toArray(),
            'today' => $today->toDateString(),
        ];

        if ($tab === 'journal') {
            $source = in_array($request->query('source'), self::SOURCES, true) ? (string) $request->query('source') : null;
            $accountId = $request->integer('account') ?: null;
            $search = trim((string) $request->query('search', ''));

            if ($accountId !== null && ($account = $accounts->firstWhere('id', $accountId)) !== null) {
                [$from, $to, $key] = $this->period($request, 'this_month');
                $props['activity'] = $this->statements->accountActivity($account, $from, $to);
                $props['period'] = ['key' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString()];
            }

            $query = JournalEntry::query()->with('lines')->orderByDesc('entry_date')->orderByDesc('number');
            if ($source !== null) {
                $query->where('source_type', $source);
            }
            if ($search !== '') {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], ltrim($search, '#')).'%';
                $query->where(fn ($q) => $q->where('memo', 'like', $like)->when(ctype_digit(ltrim($search, '#')), fn ($q) => $q->orWhere('number', (int) ltrim($search, '#'))));
            }

            $props['entries'] = $query->paginate(20)->withQueryString()->through(fn (JournalEntry $e) => [
                'id' => $e->id,
                'number' => $e->number,
                'date' => $e->entry_date->toDateString(),
                'memo' => $e->memo,
                'source_type' => $e->source_type,
                'source_id' => $e->source_id,
                'amount' => $e->total_debit,
                'reversed' => $e->reversed_by_id !== null,
                'reversal_of' => $e->reversal_of_id,
                'lines' => $e->lines->map(fn (JournalLine $l) => ['account_id' => $l->account_id, 'debit' => $l->debit, 'credit' => $l->credit, 'memo' => $l->memo])->all(),
            ]);
            $props['filters'] = ['source' => $source, 'account' => $accountId, 'search' => $search];
        }

        if ($tab === 'accounts') {
            $props['trial'] = $this->statements->trialBalance($today);
        }

        return Inertia::render('finance/ledger', $props);
    }

    /** A manual journal entry, e.g. owner's capital, a loan, depreciation, a correction. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'memo' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2', 'max:30'],
            'lines.*.account_id' => ['required', 'integer'],
            'lines.*.debit' => ['nullable', 'string', 'max:20'],
            'lines.*.credit' => ['nullable', 'string', 'max:20'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $lines = [];
        foreach ($data['lines'] as $i => $line) {
            $lines[] = [
                'account_id' => (int) $line['account_id'],
                'debit' => trim((string) ($line['debit'] ?? '')) === '' ? 0 : $this->piasters($line['debit'], "lines.{$i}.debit", true),
                'credit' => trim((string) ($line['credit'] ?? '')) === '' ? 0 : $this->piasters($line['credit'], "lines.{$i}.credit", true),
                'memo' => $line['memo'] ?? null,
            ];
        }

        $entry = $this->ledger->post($data['entry_date'], $data['memo'], $lines, ['source_type' => 'manual', 'by' => $this->user($request)]);
        if ($entry === null) {
            throw ValidationException::withMessages(['lines' => __('finance.empty_entry')]);
        }

        return back()->with('success', __('finance.entry_posted', ['number' => $entry->number]));
    }

    public function reverse(Request $request, int $entry): RedirectResponse
    {
        $entry = JournalEntry::query()->findOrFail($entry);
        $reversal = $this->ledger->reverse($entry, $this->user($request));

        return back()->with('success', __('finance.entry_reversed', ['number' => $entry->number, 'reversal' => $reversal->number]));
    }

    /** Move money between own accounts: a gateway payout to the bank, a cash deposit, a withdrawal. */
    public function transfer(Request $request): RedirectResponse
    {
        $money = [ChartOfAccounts::CASH, ChartOfAccounts::BANK, ChartOfAccounts::CLEARING];
        $data = $request->validate([
            'from' => ['required', Rule::in($money)],
            'to' => ['required', Rule::in($money), 'different:from'],
            'amount' => ['required', 'string', 'max:20'],
            'fee' => ['nullable', 'string', 'max:20'],
            'entry_date' => ['required', 'date'],
        ]);
        $amount = $this->piasters($data['amount'], 'amount');
        $fee = trim((string) ($data['fee'] ?? '')) === '' ? 0 : $this->piasters($data['fee'], 'fee', true);
        if ($fee >= $amount) {
            throw ValidationException::withMessages(['fee' => __('finance.fee_too_big')]);
        }

        // A payout arrives less the bank's charge: Dr Bank (net), Dr Bank charges, Cr source (gross).
        $lines = PostingRules::transfer($data['from'], $data['to'], $amount);
        $lines[0]['debit'] = $amount - $fee;
        $lines[] = ['code' => '6500', 'debit' => $fee];
        $entry = $this->ledger->post($data['entry_date'], __('finance.memo_transfer', [
            'from' => __('finance.money_'.$data['from']), 'to' => __('finance.money_'.$data['to']),
        ]), $lines, ['source_type' => 'manual', 'by' => $this->user($request)]);

        return back()->with('success', __('finance.entry_posted', ['number' => $entry?->number]));
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[1-9][0-9]{3,5}$/', Rule::unique('accounts', 'code')->where('store_id', $this->current->getOrFail()->getKey())],
            'name_en' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::in(AccountType::values())],
            'subtype' => ['nullable', Rule::in(['other', 'fixed', 'variable', 'cogs', 'contra', 'cash', 'bank', 'fixed_asset', 'prepaid', 'payable', 'loan', 'accrued', 'capital'])],
        ]);
        // The first digit says where an account belongs; keep the chart readable.
        $expected = ['1' => 'asset', '2' => 'liability', '3' => 'equity', '4' => 'revenue', '5' => 'expense', '6' => 'expense', '7' => 'expense', '8' => 'revenue', '9' => 'expense'][$data['code'][0]];
        if ($expected !== $data['type']) {
            throw ValidationException::withMessages(['code' => __('finance.code_type_mismatch')]);
        }

        Account::query()->create([
            'code' => $data['code'],
            'name_en' => $data['name_en'],
            'name_ar' => $data['name_ar'] ?? '',
            'type' => $data['type'],
            'subtype' => $data['subtype'] ?? ($data['type'] === 'expense' ? 'fixed' : 'other'),
        ]);
        $this->chart->forget((int) $this->current->getOrFail()->getKey());

        return back()->with('success', __('finance.account_added'));
    }

    public function updateAccount(Request $request, int $account): RedirectResponse
    {
        $account = Account::query()->findOrFail($account);
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
        ]);
        if ($account->is_system && ! ($data['is_active'] ?? true)) {
            throw ValidationException::withMessages(['is_active' => __('finance.system_account')]);
        }
        $account->fill(['name_en' => $data['name_en'], 'name_ar' => $data['name_ar'] ?? '', 'is_active' => $data['is_active'] ?? true])->save();

        return back()->with('success', __('finance.account_saved'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vat_registered' => ['required', 'boolean'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:30'],
            'gateway_fee_pct' => ['required', 'numeric', 'min:0', 'max:15'],
            'gateway_fee_fixed' => ['required', 'string', 'max:20'],
            'cash_safety_level' => ['required', 'string', 'max:20'],
        ]);

        $this->settings->update([
            'vat_registered' => (bool) $data['vat_registered'],
            'vat_rate_bps' => (int) round((float) $data['vat_rate'] * 100),
            'gateway_fee_bps' => (int) round((float) $data['gateway_fee_pct'] * 100),
            'gateway_fee_fixed' => $this->piasters($data['gateway_fee_fixed'], 'gateway_fee_fixed', true),
            'cash_safety_level' => $this->piasters($data['cash_safety_level'], 'cash_safety_level', true),
        ]);

        return back()->with('success', __('finance.settings_saved'));
    }
}
