<?php

namespace App\Modules\Ledger\AiFunctions;

use App\Ai\Registry\AiFunction;
use App\Core\Permissions\Permission;
use App\Modules\Fpa\Services\ForecastService;
use App\Modules\Ledger\Services\StatementService;
use Illuminate\Support\Carbon;

/**
 * What the AI CFO may ask the Ledger. Read-only; every figure is computed here from posted journal
 * lines and returned with its period and source, so the number guard can check the wording
 * (FR-AI-03, NFR-AIQ-02). Money is in piasters.
 */
class LedgerFunctions
{
    public function __construct(
        private readonly StatementService $statements,
        private readonly ForecastService $forecast,
    ) {}

    /** @return array<string, mixed> */
    #[AiFunction('finance.profit_and_loss', 'Revenue, cost of goods, gross profit, costs and net profit for a period (dates YYYY-MM-DD).', Permission::LedgerView, agents: ['cfo', 'ceo'])]
    public function profitAndLoss(string $period_start, string $period_end): array
    {
        $pl = $this->statements->profitAndLoss(Carbon::parse($period_start), Carbon::parse($period_end));

        return [
            'period' => ['start' => $pl['from'], 'end' => $pl['to']],
            'net_revenue' => $pl['net_revenue'],
            'cost_of_goods' => $pl['total_cogs'],
            'gross_profit' => $pl['gross_profit'],
            'gross_margin_pct' => $pl['gross_margin'],
            'variable_costs' => $pl['total_variable'],
            'operating_costs' => $pl['total_fixed'],
            'net_profit' => $pl['net_profit'],
            'source' => 'ledger',
        ];
    }

    /** @return array<string, mixed> */
    #[AiFunction('finance.cash_position', 'Money available now, the next three months of forecast cash, and when it may drop below the safety level.', Permission::LedgerView, agents: ['cfo', 'ceo'])]
    public function cashPosition(): array
    {
        $f = $this->forecast->cash();

        return [
            'as_of' => Carbon::now('Africa/Cairo')->toDateString(),
            'cash_now' => $f['cash_now'],
            'expected_monthly_flow' => $f['monthly_flow'],
            'forecast' => $f['months'],
            'safety_level' => $f['safety'],
            'first_month_below_safety' => $f['first_below'],
            'source' => 'ledger',
        ];
    }

    /** @return array<string, mixed> */
    #[AiFunction('finance.break_even', 'Monthly sales needed to cover all costs, and progress this month.', Permission::BudgetsView, agents: ['cfo', 'ceo'])]
    public function breakEven(): array
    {
        return $this->forecast->breakEvenStatus() + ['source' => 'ledger'];
    }
}
