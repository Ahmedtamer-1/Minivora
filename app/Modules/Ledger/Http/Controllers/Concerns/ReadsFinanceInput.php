<?php

namespace App\Modules\Ledger\Http\Controllers\Concerns;

use App\Core\Money\Piasters;
use App\Modules\Accounts\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Shared input handling for the finance pages: report periods (Cairo time) and amounts in pounds. */
trait ReadsFinanceInput
{
    public const PERIODS = ['this_month', 'last_month', 'this_quarter', 'last_quarter', 'ytd', 'last_year', 'last_12', 'custom'];

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: Carbon, 4: Carbon} from, to, key, previous from, previous to
     */
    protected function period(Request $request, string $default = 'this_month'): array
    {
        $key = (string) $request->query('period', $default);
        if (! in_array($key, self::PERIODS, true)) {
            $key = $default;
        }
        $now = Carbon::now('Africa/Cairo');

        [$from, $to] = match ($key) {
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->copy()->firstOfQuarter(), $now->copy()],
            'last_quarter' => [$now->copy()->subQuarterNoOverflow()->firstOfQuarter(), $now->copy()->subQuarterNoOverflow()->lastOfQuarter()],
            'ytd' => [$now->copy()->startOfYear(), $now->copy()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'last_12' => [$now->copy()->subMonthsNoOverflow(11)->startOfMonth(), $now->copy()],
            'custom' => $this->customRange($request, $now),
            default => [$now->copy()->startOfMonth(), $now->copy()],
        };
        $from = $from->startOfDay();
        $to = $to->startOfDay();

        // The same length of time just before, for the comparison column.
        $days = (int) $from->diffInDays($to) + 1;
        $prevTo = $from->copy()->subDay();
        $prevFrom = $key === 'this_month' || $key === 'last_month'
            ? $from->copy()->subMonthNoOverflow()->startOfMonth()
            : $prevTo->copy()->subDays($days - 1);
        if ($key === 'this_month') {
            // Month to date vs the same days last month.
            $prevTo = $prevFrom->copy()->addDays(min($days, $prevFrom->daysInMonth) - 1);
        }

        return [$from, $to, $key, $prevFrom, $prevTo];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function customRange(Request $request, Carbon $now): array
    {
        try {
            $from = Carbon::parse((string) $request->query('from', $now->copy()->startOfMonth()->toDateString()), 'Africa/Cairo');
            $to = Carbon::parse((string) $request->query('to', $now->toDateString()), 'Africa/Cairo');
        } catch (\Throwable) {
            return [$now->copy()->startOfMonth(), $now->copy()];
        }
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 3700) {
            $from = $to->copy()->subYears(10);
        }

        return [$from, $to];
    }

    protected function month(Request $request, string $field = 'month'): Carbon
    {
        $value = (string) $request->input($field, '');
        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01', 'Africa/Cairo')->startOfDay();
        }

        return Carbon::now('Africa/Cairo')->startOfMonth();
    }

    protected function piasters(?string $pounds, string $field, bool $allowZero = false): int
    {
        try {
            $amount = Piasters::fromPounds((string) $pounds)->amount;
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([$field => __('finance.invalid_amount')]);
        }
        if ($amount < 0 || (! $allowZero && $amount === 0)) {
            throw ValidationException::withMessages([$field => __('finance.invalid_amount')]);
        }

        return $amount;
    }

    protected function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
