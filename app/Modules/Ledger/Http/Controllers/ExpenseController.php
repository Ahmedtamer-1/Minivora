<?php

namespace App\Modules\Ledger\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Http\Controllers\Concerns\ReadsFinanceInput;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Expense;
use App\Modules\Ledger\Services\ExpenseService;
use App\Modules\Ledger\Services\PeriodService;
use App\Modules\Ledger\Services\StatementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Expenses and bills (FR-LED-05) and month close (FR-LED-07), UC-10.
 */
class ExpenseController extends Controller
{
    use ReadsFinanceInput;

    public const FILTERS = ['all', 'unpaid', 'paid', 'voided'];

    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly PeriodService $periods,
        private readonly StatementService $statements,
    ) {}

    public function index(Request $request): Response
    {
        [$from, $to, $key] = $this->period($request, 'this_month');
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? (string) $request->query('filter') : 'all';
        $accountId = $request->integer('account') ?: null;
        $locale = app()->getLocale();
        $this->statements->accounts(); // makes sure the chart exists before listing expense accounts

        $query = Expense::query()->with('account')->orderByDesc('expense_date')->orderByDesc('id');
        if ($filter === 'unpaid') {
            // Bills still owed show whatever their date, so nothing slips.
            $query->whereNull('paid_at')->whereNull('voided_at')->where('paid_from', 'payable');
        } else {
            $query->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()]);
            match ($filter) {
                'paid' => $query->whereNotNull('paid_at')->whereNull('voided_at'),
                'voided' => $query->whereNotNull('voided_at'),
                default => null,
            };
        }
        if ($accountId !== null) {
            $query->where('account_id', $accountId);
        }

        $inPeriod = Expense::query()->whereNull('voided_at')->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()]);
        $byAccount = (clone $inPeriod)->selectRaw('account_id, SUM(amount) as total')->groupBy('account_id')->pluck('total', 'account_id');
        $unpaid = Expense::query()->whereNull('paid_at')->whereNull('voided_at')->where('paid_from', 'payable');

        return Inertia::render('finance/expenses', [
            'period' => ['key' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'filter' => $filter,
            'accountFilter' => $accountId,
            'expenses' => $query->paginate(25)->withQueryString()->through(fn (Expense $e) => $this->expenses->toRow($e, $locale)),
            'accounts' => Account::query()->where('type', AccountType::Expense)->where('is_active', true)->where('subtype', '!=', 'cogs')->orderBy('code')->get()
                ->map(fn (Account $a) => ['id' => $a->id, 'code' => $a->code, 'name_en' => $a->name_en, 'name_ar' => $a->name_ar, 'subtype' => $a->subtype])->all(),
            'summary' => [
                'total' => (int) (clone $inPeriod)->sum('amount'),
                'vat' => (int) (clone $inPeriod)->sum('vat'),
                'count' => (clone $inPeriod)->count(),
                'unpaid' => (int) (clone $unpaid)->sum('amount'),
                'unpaid_count' => (clone $unpaid)->count(),
                'overdue' => (clone $unpaid)->whereNotNull('due_date')->where('due_date', '<', Carbon::now('Africa/Cairo')->toDateString())->count(),
                'by_account' => $byAccount->map(fn ($v) => (int) $v)->all(),
            ],
            'today' => Carbon::now('Africa/Cairo')->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expense_date' => ['required', 'date', 'before_or_equal:'.Carbon::now('Africa/Cairo')->addDay()->toDateString()],
            'vendor' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'integer'],
            'paid_from' => ['required', Rule::in(Expense::PAID_FROM)],
            'amount' => ['required', 'string', 'max:20'],
            'vat' => ['nullable', 'string', 'max:20'],
            'due_date' => ['nullable', 'date', 'after_or_equal:expense_date'],
        ]);

        $this->expenses->record([
            ...$data,
            'account_id' => (int) $data['account_id'],
            'amount' => $this->piasters($data['amount'], 'amount'),
            'vat' => trim((string) ($data['vat'] ?? '')) === '' ? 0 : $this->piasters($data['vat'], 'vat', true),
        ], $this->user($request));

        return back()->with('success', __('finance.expense_recorded'));
    }

    public function pay(Request $request, int $expense): RedirectResponse
    {
        $data = $request->validate([
            'paid_with' => ['required', Rule::in(['cash', 'bank'])],
            'paid_on' => ['required', 'date'],
        ]);
        $this->expenses->markPaid(Expense::query()->findOrFail($expense), $data['paid_with'], $data['paid_on'], $this->user($request));

        return back()->with('success', __('finance.bill_paid'));
    }

    public function void(Request $request, int $expense): RedirectResponse
    {
        $this->expenses->void(Expense::query()->findOrFail($expense), $this->user($request));

        return back()->with('success', __('finance.expense_voided'));
    }

    public function close(Request $request): Response
    {
        $default = Carbon::now('Africa/Cairo')->subMonthNoOverflow()->startOfMonth();
        $month = $request->filled('month') ? $this->month($request) : $default;

        return Inertia::render('finance/close', [
            'checklist' => $this->periods->checklist($month),
            'closed' => $this->periods->closedMonths(),
            'summary' => $this->statements->profitAndLoss($month->copy()->startOfMonth(), $month->copy()->endOfMonth()),
        ]);
    }

    public function closeMonth(Request $request): RedirectResponse
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/'], 'bank_confirmed' => ['accepted']]);
        $this->periods->close($this->month($request), true, $this->user($request));

        return back()->with('success', __('finance.month_closed'));
    }

    public function reopen(Request $request): RedirectResponse
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        $this->periods->reopen($this->month($request), $this->user($request));

        return back()->with('success', __('finance.month_reopened'));
    }

    public function catchUp(): RedirectResponse
    {
        $n = $this->periods->catchUp();

        return back()->with('success', __('finance.caught_up', ['count' => $n]));
    }

    public function syncInventory(Request $request): RedirectResponse
    {
        $diff = $this->periods->syncInventory($this->user($request));

        return back()->with('success', $diff === null ? __('finance.inventory_matches') : __('finance.inventory_synced'));
    }
}
