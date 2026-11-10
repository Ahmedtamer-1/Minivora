<?php

namespace App\Modules\Ledger\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fpa\Services\ForecastService;
use App\Modules\Ledger\Http\Controllers\Concerns\ReadsFinanceInput;
use App\Modules\Ledger\Models\Expense;
use App\Modules\Ledger\Models\Period;
use App\Modules\Ledger\Services\ChartOfAccounts;
use App\Modules\Ledger\Services\FinanceSettings;
use App\Modules\Ledger\Services\PeriodService;
use App\Modules\Ledger\Services\StatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Finance overview and the financial statements with CSV export (UC-11, FR-LED-04, FR-LED-08,
 * FR-FPA-04). Every figure is computed from posted journal lines by StatementService.
 */
class FinanceController extends Controller
{
    use ReadsFinanceInput;

    public const REPORTS = ['pl', 'balance', 'cash', 'trial', 'vat'];

    public function __construct(
        private readonly StatementService $statements,
        private readonly ForecastService $forecast,
        private readonly FinanceSettings $settings,
        private readonly PeriodService $periods,
    ) {}

    public function overview(Request $request): Response
    {
        [$from, $to, $key, $prevFrom, $prevTo] = $this->period($request);
        $pl = $this->statements->profitAndLoss($from, $to);
        $prev = $this->statements->profitAndLoss($prevFrom, $prevTo);
        $today = Carbon::now('Africa/Cairo');
        $balances = $this->statements->balances(null, $today);
        $accounts = $this->statements->accounts();
        $balanceOf = fn (string $code) => $balances[$accounts->get($code)?->id] ?? 0;
        $settings = $this->settings->current();
        $forecast = $this->forecast->cash($today);

        $overdue = Expense::query()->whereNull('paid_at')->whereNull('voided_at')->where('paid_from', 'payable')
            ->whereNotNull('due_date')->where('due_date', '<', $today->toDateString());
        $lastMonth = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonthHasEntries = $this->statements->totals($lastMonth, $lastMonth->copy()->endOfMonth()) !== [];
        $lastClosed = Period::query()->where('month', $lastMonth->toDateString())->whereNotNull('closed_at')->exists();

        $alerts = [];
        if ($forecast['first_below'] !== null) {
            $alerts[] = ['key' => 'low_cash', 'tone' => 'danger', 'month' => $forecast['first_below'], 'amount' => $forecast['safety']];
        }
        if (($n = (clone $overdue)->count()) > 0) {
            $alerts[] = ['key' => 'overdue_bills', 'tone' => 'warning', 'count' => $n, 'amount' => (int) (clone $overdue)->sum('amount')];
        }
        if (($unsynced = $this->periods->unsyncedOrders()) > 0) {
            $alerts[] = ['key' => 'unsynced', 'tone' => 'warning', 'count' => $unsynced];
        }
        if ($lastMonthHasEntries && ! $lastClosed) {
            $alerts[] = ['key' => 'close_month', 'tone' => 'info', 'month' => $lastMonth->format('Y-m')];
        }
        if ($balanceOf(ChartOfAccounts::CLEARING) > 0) {
            $alerts[] = ['key' => 'payout', 'tone' => 'info', 'amount' => $balanceOf(ChartOfAccounts::CLEARING)];
        }
        if ($settings->vat_registered && ($vat = $this->statements->vat($lastMonth, $lastMonth->copy()->endOfMonth()))['net'] > 0) {
            $alerts[] = ['key' => 'vat', 'tone' => 'info', 'month' => $lastMonth->format('Y-m'), 'amount' => $vat['net']];
        }

        return Inertia::render('finance/index', [
            'period' => ['key' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'pl' => $pl,
            'previous' => [
                'net_revenue' => $prev['net_revenue'],
                'gross_profit' => $prev['gross_profit'],
                'net_profit' => $prev['net_profit'],
                'total_costs' => $prev['total_cogs'] + $prev['total_variable'] + $prev['total_fixed'],
            ],
            'money' => [
                'total' => $forecast['cash_now'],
                'cash' => $balanceOf(ChartOfAccounts::CASH),
                'bank' => $balanceOf(ChartOfAccounts::BANK),
                'clearing' => $balanceOf(ChartOfAccounts::CLEARING),
                'receivable' => $balanceOf(ChartOfAccounts::RECEIVABLE),
                'payable' => $balanceOf(ChartOfAccounts::PAYABLE) + $balanceOf(ChartOfAccounts::CARD_PAYABLE),
                'inventory' => $balanceOf(ChartOfAccounts::INVENTORY),
            ],
            'monthly' => $this->statements->monthly(12, $today),
            'forecast' => $forecast,
            'breakEven' => $this->forecast->breakEvenStatus($today),
            'alerts' => $alerts,
            'hasEntries' => $this->statements->totals(null, $today) !== [],
            'vatRegistered' => $settings->vat_registered,
        ]);
    }

    public function reports(Request $request): Response
    {
        [$from, $to, $key, $prevFrom, $prevTo] = $this->period($request);
        $report = in_array($request->query('report'), self::REPORTS, true) ? (string) $request->query('report') : 'pl';
        $compare = $request->boolean('compare', true);

        $data = $this->build($report, $from, $to);
        $previous = $compare && $report !== 'trial' ? $this->build($report, $prevFrom, $prevTo) : null;

        return Inertia::render('finance/reports', [
            'report' => $report,
            'period' => ['key' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'prev_from' => $prevFrom->toDateString(), 'prev_to' => $prevTo->toDateString()],
            'compare' => $compare,
            'data' => $data,
            'previous' => $previous,
            'vatRegistered' => $this->settings->current()->vat_registered,
        ]);
    }

    /** CSV of any statement, opened in Excel with Arabic intact (UTF-8 BOM). FR-LED-08 */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $report = in_array($request->query('report'), self::REPORTS, true) ? (string) $request->query('report') : 'pl';
        $locale = app()->getLocale();
        $data = $this->build($report, $from, $to);
        $name = fn (array $r) => $locale === 'ar' && ($r['name_ar'] ?? '') !== '' ? $r['name_ar'] : ($r['name_en'] ?? '');
        $money = fn (int $p) => number_format($p / 100, 2, '.', '');
        $label = fn (string $k) => __('finance.csv_'.$k);

        $rows = [[$label('report_'.$report), $from->toDateString(), $to->toDateString()], []];
        $section = function (string $title, array $lines, ?int $total = null) use (&$rows, $name, $money, $label) {
            $rows[] = [$label($title)];
            foreach ($lines as $l) {
                $rows[] = [$l['code'], $name($l), $money($l['amount'])];
            }
            if ($total !== null) {
                $rows[] = ['', $label('total_'.$title), $money($total)];
            }
            $rows[] = [];
        };

        match ($report) {
            'pl' => (function () use ($data, $section, &$rows, $money, $label) {
                $section('revenue', $data['revenue'], $data['gross_revenue']);
                $section('contra', $data['contra']);
                $rows[] = ['', $label('net_revenue'), $money($data['net_revenue'])];
                $section('cogs', $data['cogs'], $data['total_cogs']);
                $rows[] = ['', $label('gross_profit'), $money($data['gross_profit'])];
                $section('variable', $data['variable'], $data['total_variable']);
                $rows[] = ['', $label('contribution'), $money($data['contribution'])];
                $section('fixed', $data['fixed'], $data['total_fixed']);
                $rows[] = ['', $label('net_profit'), $money($data['net_profit'])];
            })(),
            'balance' => (function () use ($data, $section, &$rows, $money, $label) {
                $section('assets', $data['assets'], $data['total_assets']);
                $section('liabilities', $data['liabilities'], $data['total_liabilities']);
                $section('equity', $data['equity']);
                $rows[] = ['', $label('profit_to_date'), $money($data['profit_to_date'])];
                $rows[] = ['', $label('total_equity'), $money($data['total_equity'])];
            })(),
            'cash' => (function () use ($data, $section, &$rows, $money, $label) {
                $rows[] = ['', $label('opening'), $money($data['opening'])];
                $section('operating', $data['operating'], $data['total_operating']);
                $section('investing', $data['investing'], $data['total_investing']);
                $section('financing', $data['financing'], $data['total_financing']);
                $rows[] = ['', $label('closing'), $money($data['closing'])];
            })(),
            'trial' => (function () use ($data, &$rows, $name, $money, $label) {
                $rows[] = [$label('code'), $label('account'), $label('debit'), $label('credit')];
                foreach ($data['rows'] as $r) {
                    $rows[] = [$r['code'], $name($r), $money($r['debit']), $money($r['credit'])];
                }
                $rows[] = ['', $label('total'), $money($data['debit']), $money($data['credit'])];
            })(),
            'vat' => (function () use ($data, &$rows, $money, $label) {
                foreach (['output', 'input', 'net', 'sales_net', 'purchases_net'] as $k) {
                    $rows[] = [$label('vat_'.$k), $money($data[$k])];
                }
            })(),
        };

        $file = "minivora-{$report}-{$from->toDateString()}-{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                // Neutralise spreadsheet formulas in text cells.
                fputcsv($out, array_map(fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '@'], true) ? "'".$v : $v, $row));
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function build(string $report, Carbon $from, Carbon $to): array
    {
        return match ($report) {
            'balance' => $this->statements->balanceSheet($to),
            'cash' => $this->statements->cashFlow($from, $to),
            'trial' => $this->statements->trialBalance($to),
            'vat' => $this->statements->vat($from, $to),
            default => $this->statements->profitAndLoss($from, $to),
        };
    }
}
