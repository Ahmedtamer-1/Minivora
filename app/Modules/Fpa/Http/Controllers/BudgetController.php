<?php

namespace App\Modules\Fpa\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fpa\Services\ForecastService;
use App\Modules\Fpa\Services\VarianceService;
use App\Modules\Ledger\Http\Controllers\Concerns\ReadsFinanceInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Budgets, budget-versus-actual, break-even, pricing and what-if (FR-FPA-01..05).
 */
class BudgetController extends Controller
{
    use ReadsFinanceInput;

    public function __construct(
        private readonly VarianceService $variance,
        private readonly ForecastService $forecast,
    ) {}

    public function index(Request $request): Response
    {
        $month = $this->month($request);

        return Inertia::render('finance/budgets', [
            'month' => $month->format('Y-m'),
            'currentMonth' => Carbon::now('Africa/Cairo')->format('Y-m'),
            'report' => $this->variance->report($month),
            'breakEven' => $this->forecast->breakEvenStatus(),
            'whatIf' => $this->forecast->whatIfBase(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'amounts' => ['array', 'max:200'],
            'amounts.*' => ['nullable', 'string', 'max:20'],
        ]);
        $amounts = [];
        foreach ($data['amounts'] ?? [] as $accountId => $pounds) {
            $amounts[(int) $accountId] = trim((string) $pounds) === '' ? 0 : $this->piasters($pounds, "amounts.{$accountId}", true);
        }
        $this->variance->save($this->month($request), $amounts);

        return back()->with('success', __('finance.budget_saved'));
    }

    public function copy(Request $request): RedirectResponse
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        $n = $this->variance->copyFromPrevious($this->month($request));

        return back()->with('success', __('finance.budget_copied', ['count' => $n]));
    }
}
