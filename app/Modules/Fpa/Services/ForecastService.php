<?php

namespace App\Modules\Fpa\Services;

use App\Modules\Ledger\Models\Expense;
use App\Modules\Ledger\Services\FinanceSettings;
use App\Modules\Ledger\Services\StatementService;
use Illuminate\Support\Carbon;

/**
 * Cash forecast and break-even (FR-FPA-04, Documentation §6.2). Deliberately simple methods an owner
 * can follow and an examiner can check by hand:
 *
 *   next month's net cash flow  ŷ(t+1) = α·y(t) + (1 − α)·ŷ(t), α = 0.5, over the last 6 months
 *   projected cash              cash now + k·ŷ − bills falling due in those months
 *   break-even sales            BE = F / (1 − c/p), F = average fixed costs, c/p = variable cost ratio
 */
class ForecastService
{
    public const ALPHA = 0.5;

    public function __construct(
        private readonly StatementService $statements,
        private readonly FinanceSettings $settings,
    ) {}

    /** Simple exponential smoothing; returns the one-step-ahead forecast. @param  list<int>  $series */
    public static function smooth(array $series, float $alpha = self::ALPHA): int
    {
        if ($series === []) {
            return 0;
        }
        $forecast = (float) $series[0];
        foreach ($series as $y) {
            $forecast = $alpha * $y + (1 - $alpha) * $forecast;
        }

        return (int) round($forecast);
    }

    /** BE = F / (1 − c/p); null when every pound of sales is eaten by variable costs. */
    public static function breakEven(int $fixed, int $variable, int $revenue): ?int
    {
        if ($revenue <= 0) {
            return null;
        }
        $ratio = $variable / $revenue;
        if ($ratio >= 1) {
            return null;
        }

        return (int) round($fixed / (1 - $ratio));
    }

    /**
     * @return array{cash_now: int, safety: int, monthly_flow: int, history: list<array<string, mixed>>, months: list<array{month: string, cash: int, bills: int}>, first_below: string|null, runway_months: float|null}
     */
    public function cash(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::now('Africa/Cairo'))->copy();
        $history = $this->statements->monthly(7, $today);
        // Completed months only: the running month would drag the trend down.
        $complete = array_slice($history, 0, -1);
        $flows = array_values(array_map(fn ($m) => $m['cash_change'], array_filter($complete, fn ($m) => $m['revenue'] !== 0 || $m['costs'] !== 0 || $m['cash_change'] !== 0)));
        $flow = self::smooth($flows);

        $cash = $this->statements->moneyBalance($today);
        $safety = $this->settings->current()->cash_safety_level;

        $months = [];
        $firstBelow = null;
        $projected = $cash;
        for ($k = 1; $k <= 3; $k++) {
            $m = $today->copy()->startOfMonth()->addMonthsNoOverflow($k);
            $bills = (int) Expense::query()->whereNull('paid_at')->whereNull('voided_at')->where('paid_from', 'payable')
                ->when($k === 1, fn ($q) => $q->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '<=', $m->copy()->endOfMonth()->toDateString())))
                ->when($k > 1, fn ($q) => $q->whereBetween('due_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()]))
                ->sum('amount');
            $projected += $flow - $bills;
            $months[] = ['month' => $m->format('Y-m'), 'cash' => $projected, 'bills' => $bills];
            if ($firstBelow === null && $projected < $safety) {
                $firstBelow = $m->format('Y-m');
            }
        }

        return [
            'cash_now' => $cash,
            'safety' => $safety,
            'monthly_flow' => $flow,
            'history' => $complete,
            'months' => $months,
            'first_below' => $firstBelow,
            'runway_months' => $flow < 0 && $cash > 0 ? round($cash / -$flow, 1) : null,
        ];
    }

    /**
     * Break-even from the last three complete months (the current month if there is no history yet),
     * and how far this month's sales have got.
     *
     * @return array{fixed: int, variable_ratio: float|null, break_even: int|null, month_sales: int, progress: float|null, average_order: int|null, orders_needed: int|null, basis_months: int}
     */
    public function breakEvenStatus(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::now('Africa/Cairo'))->copy();
        $to = $today->copy()->startOfMonth()->subDay();
        $from = $to->copy()->subMonthsNoOverflow(2)->startOfMonth();
        $pl = $this->statements->profitAndLoss($from, $to);
        $months = 3;
        if ($pl['net_revenue'] <= 0) {
            $from = $today->copy()->startOfMonth();
            $to = $today->copy();
            $pl = $this->statements->profitAndLoss($from, $to);
            $months = 1;
        }

        $fixed = intdiv($pl['total_fixed'], $months);
        $variable = $pl['total_cogs'] + $pl['total_variable'];
        $be = self::breakEven($fixed, $variable, $pl['net_revenue']);

        $now = $this->statements->profitAndLoss($today->copy()->startOfMonth(), $today);
        $orders = $this->statements->paidOrders($from, $to);
        $aov = $orders > 0 ? intdiv($pl['gross_revenue'], $orders) : null;

        return [
            'fixed' => $fixed,
            'variable_ratio' => $pl['net_revenue'] > 0 ? round($variable / $pl['net_revenue'], 4) : null,
            'break_even' => $be,
            'month_sales' => $now['net_revenue'],
            'progress' => $be !== null && $be > 0 ? round(min(999, $now['net_revenue'] * 100 / $be), 1) : null,
            'average_order' => $aov,
            'orders_needed' => $be !== null && $aov !== null && $aov > 0 ? (int) ceil($be / $aov) : null,
            'basis_months' => $months,
        ];
    }

    /**
     * Base numbers for the what-if panel: the last complete month (or this month so far), split
     * into the parts a price, volume or cost change acts on. The arithmetic runs in the browser
     * from these figures, so the owner sees the result move as they drag.
     *
     * @return array{month: string, revenue: int, cogs: int, variable: int, fixed: int, marketing: int, profit: int}
     */
    public function whatIfBase(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::now('Africa/Cairo'))->copy();
        $from = $today->copy()->startOfMonth()->subMonthNoOverflow();
        $to = $from->copy()->endOfMonth();
        $pl = $this->statements->profitAndLoss($from, $to);
        if ($pl['net_revenue'] <= 0) {
            $from = $today->copy()->startOfMonth();
            $to = $today->copy();
            $pl = $this->statements->profitAndLoss($from, $to);
        }
        $marketing = (int) array_sum(array_column(array_filter($pl['fixed'], fn ($r) => $r['code'] === '6000'), 'amount'));

        return [
            'month' => $from->format('Y-m'),
            'revenue' => $pl['net_revenue'],
            'cogs' => $pl['total_cogs'],
            'variable' => $pl['total_variable'],
            'fixed' => $pl['total_fixed'] - $marketing,
            'marketing' => $marketing,
            'profit' => $pl['net_profit'],
        ];
    }
}
