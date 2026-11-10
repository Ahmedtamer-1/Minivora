<?php

namespace App\Modules\Fpa\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Modules\Fpa\Models\Budget;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Services\StatementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly budgets per P&L account and budget-versus-actual (FR-FPA-01, FR-FPA-02). Actuals come
 * from the ledger. A variance is favourable when revenue beats plan or a cost comes in under it.
 */
class VarianceService
{
    public function __construct(
        private readonly StatementService $statements,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @return array{month: string, rows: list<array<string, mixed>>, totals: array{revenue_budget: int, revenue_actual: int, cost_budget: int, cost_actual: int, profit_budget: int, profit_actual: int}, has_budget: bool, running: bool, share: float}
     */
    public function report(Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $actual = $this->statements->balances($from, $to);
        $budgets = Budget::query()->where('month', $from->toDateString())->pluck('amount', 'account_id');

        // A month still running is judged against the plan so far (budget × days gone ÷ days in month).
        $today = Carbon::now('Africa/Cairo')->startOfDay();
        $running = $today->between($from, $to);
        $share = $running ? $today->day / $from->daysInMonth : 1.0;

        $rows = [];
        $totals = ['revenue_budget' => 0, 'revenue_actual' => 0, 'cost_budget' => 0, 'cost_actual' => 0];
        foreach ($this->statements->accounts() as $account) {
            if (! $account->type->isTemporary() || ! $account->is_active) {
                continue;
            }
            $revenue = $account->type === AccountType::Revenue;
            $contra = $account->subtype === 'contra';
            $act = $actual[$account->id] ?? 0;
            if ($contra) {
                $act = -$act; // shown as the amount taken off revenue
            }
            $bud = (int) ($budgets[$account->id] ?? 0);
            if ($act === 0 && $bud === 0 && ! in_array($account->subtype, ['sales', 'cogs', 'fixed', 'variable'], true)) {
                continue;
            }
            $expected = (int) round($bud * $share);
            $variance = $act - $expected;
            // Above plan is good for revenue, bad for costs and contra revenue.
            $favourable = $revenue && ! $contra ? $variance >= 0 : $variance <= 0;
            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name_en' => $account->name_en,
                'name_ar' => $account->name_ar,
                'kind' => $revenue ? ($contra ? 'contra' : 'revenue') : 'cost',
                'budget' => $bud,
                'expected' => $expected,
                'actual' => $act,
                'variance' => $variance,
                'pct' => $expected > 0 ? round($act * 100 / $expected, 1) : null,
                'favourable' => $favourable,
            ];
            if ($revenue) {
                $totals['revenue_budget'] += $contra ? -$bud : $bud;
                $totals['revenue_actual'] += $contra ? -$act : $act;
            } else {
                $totals['cost_budget'] += $bud;
                $totals['cost_actual'] += $act;
            }
        }

        return [
            'month' => $from->format('Y-m'),
            'rows' => $rows,
            'totals' => $totals + [
                'profit_budget' => $totals['revenue_budget'] - $totals['cost_budget'],
                'profit_actual' => $totals['revenue_actual'] - $totals['cost_actual'],
            ],
            'has_budget' => $budgets->isNotEmpty(),
            'running' => $running,
            'share' => round($share, 3),
        ];
    }

    /** @param  array<int, int>  $amounts  account id => piasters (0 removes the line) */
    public function save(Carbon $month, array $amounts): void
    {
        $key = $month->copy()->startOfMonth()->toDateString();
        $valid = Account::query()->whereIn('type', [AccountType::Revenue, AccountType::Expense])->pluck('id')->all();

        DB::transaction(function () use ($key, $amounts, $valid) {
            foreach ($amounts as $accountId => $amount) {
                if (! in_array((int) $accountId, $valid, true)) {
                    continue;
                }
                if ($amount <= 0) {
                    Budget::query()->where('month', $key)->where('account_id', $accountId)->delete();

                    continue;
                }
                Budget::query()->updateOrCreate(['month' => $key, 'account_id' => (int) $accountId], ['amount' => $amount]);
            }
        });
        $this->activity->log('budget.saved', null, ['month' => substr($key, 0, 7), 'lines' => count($amounts)]);
    }

    /**
     * Fill a month's budget from the previous month's budget, or from its actuals when there was none.
     *
     * @return int lines written
     */
    public function copyFromPrevious(Carbon $month): int
    {
        $prev = $month->copy()->startOfMonth()->subMonthNoOverflow();
        $source = Budget::query()->where('month', $prev->toDateString())->pluck('amount', 'account_id')->map(fn ($v) => (int) $v)->all();
        if ($source === []) {
            foreach ($this->report($prev)['rows'] as $row) {
                if ($row['actual'] > 0) {
                    $source[$row['account_id']] = $row['actual'];
                }
            }
        }
        $this->save($month, $source);

        return count($source);
    }
}
