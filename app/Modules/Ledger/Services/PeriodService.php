<?php

namespace App\Modules\Ledger\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Services\InventoryService;
use App\Modules\Ledger\Models\Expense;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Models\Period;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Month close (UC-10, FR-LED-07). The checklist an accountant runs before locking a month:
 *
 *   1. every paid or shipped order is in the books       (blocker; "catch up" fixes it)
 *   2. the books balance                                  (blocker)
 *   3. the month before is closed                         (blocker: close in order)
 *   4. the bank statement was matched to the bank account (the person confirms it)
 *   5. stock on the shelf at cost matches inventory in the books   (warning)
 *   6. bills due in the month are paid                    (warning)
 *
 * A closed month refuses new entries; late events land in the first open day instead.
 */
class PeriodService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly OrderPosting $posting,
        private readonly StatementService $statements,
        private readonly InventoryService $inventory,
        private readonly ChartOfAccounts $chart,
        private readonly LedgerService $ledger,
        private readonly ActivityLogger $activity,
        private readonly CurrentStore $current,
    ) {}

    /** Post whatever orders are missing from the books. @return int entries posted */
    public function catchUp(): int
    {
        $posted = 0;
        foreach ($this->orders->ledgerSnapshots() as $snapshot) {
            $posted += $this->posting->sync($snapshot);
        }

        return $posted;
    }

    public function unsyncedOrders(): int
    {
        $keys = array_fill_keys(JournalEntry::query()->where('source_type', 'order')->pluck('source_key')->all(), true);
        $n = 0;
        foreach ($this->orders->ledgerSnapshots() as $snapshot) {
            if (! $this->posting->isSynced($snapshot, $keys)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @return array{month: string, closed: bool, closed_at: string|null, can_close: bool, checks: list<array{key: string, status: string, value?: int, detail?: int}>}
     */
    public function checklist(Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $period = Period::query()->where('month', $month->toDateString())->first();

        $unsynced = $this->unsyncedOrders();
        $tb = $this->statements->trialBalance($end);
        $prev = $month->copy()->subMonthNoOverflow();
        $hasEarlierEntries = $this->statements->totals(null, $prev->copy()->endOfMonth()) !== [];
        $prevClosed = ! $hasEarlierEntries || Period::query()->where('month', $prev->toDateString())->whereNotNull('closed_at')->exists();

        $stock = $this->inventory->stockValue();
        $inventory = $this->chart->get($this->storeId(), ChartOfAccounts::INVENTORY);
        $books = $this->statements->balances(null, $end)[$inventory->id] ?? 0;
        $diff = $stock['value'] - $books;

        $unpaid = Expense::query()->whereNull('paid_at')->whereNull('voided_at')->where('paid_from', 'payable')
            ->where('due_date', '<=', $end->toDateString())->count();

        $checks = [
            ['key' => 'orders', 'status' => $unsynced === 0 ? 'ok' : 'blocker', 'value' => $unsynced],
            ['key' => 'balanced', 'status' => $tb['balanced'] ? 'ok' : 'blocker'],
            ['key' => 'previous', 'status' => $prevClosed ? 'ok' : 'blocker'],
            ['key' => 'bank', 'status' => 'confirm'],
            ['key' => 'inventory', 'status' => abs($diff) < 100 ? 'ok' : 'warning', 'value' => $diff, 'detail' => $stock['missing_cost']],
            ['key' => 'bills', 'status' => $unpaid === 0 ? 'ok' : 'warning', 'value' => $unpaid],
        ];

        return [
            'month' => $month->format('Y-m'),
            'closed' => $period?->closed_at !== null,
            'closed_at' => $period?->closed_at?->toIso8601String(),
            'can_close' => $period?->closed_at === null && ! in_array('blocker', array_column($checks, 'status'), true) && $month->lt(Carbon::now('Africa/Cairo')->startOfMonth()),
            'checks' => $checks,
        ];
    }

    public function close(Carbon $month, bool $bankConfirmed, ?User $by = null): Period
    {
        $list = $this->checklist($month);
        if ($list['closed']) {
            throw ValidationException::withMessages(['month' => __('finance.already_closed')]);
        }
        if (! $bankConfirmed) {
            throw ValidationException::withMessages(['bank_confirmed' => __('finance.confirm_bank')]);
        }
        if (! $list['can_close']) {
            throw ValidationException::withMessages(['month' => __('finance.cannot_close')]);
        }

        $period = Period::query()->firstOrNew(['month' => $month->copy()->startOfMonth()->toDateString()]);
        $period->forceFill(['closed_at' => Carbon::now(), 'closed_by' => $by?->id, 'checks' => $list['checks']])->save();
        $this->activity->log('ledger.period_closed', $period, ['month' => $list['month']]);

        return $period;
    }

    public function reopen(Carbon $month, ?User $by = null): void
    {
        $period = Period::query()->where('month', $month->copy()->startOfMonth()->toDateString())->whereNotNull('closed_at')->first();
        if ($period === null) {
            return;
        }
        // Reopening a month reopens every later one too, so the books never have a closed month after an open one.
        $later = Period::query()->where('month', '>', $period->month->toDateString())->whereNotNull('closed_at')->exists();
        if ($later) {
            throw ValidationException::withMessages(['month' => __('finance.reopen_latest_first')]);
        }
        $period->forceFill(['closed_at' => null, 'closed_by' => null])->save();
        $this->activity->log('ledger.period_reopened', $period, ['month' => $period->month->format('Y-m')]);
    }

    /**
     * Bring inventory in the books up (or down) to the stock on the shelf at cost. The difference
     * goes to opening balance equity the first time, which is how a store that sold before
     * Minivora starts its books.
     */
    public function syncInventory(?User $by = null): ?int
    {
        $end = Carbon::now('Africa/Cairo');
        $account = $this->chart->get($this->storeId(), ChartOfAccounts::INVENTORY);
        $books = $this->statements->balances(null, $end)[$account->id] ?? 0;
        $diff = $this->inventory->stockValue()['value'] - $books;
        if ($diff === 0) {
            return null;
        }
        $abs = abs($diff);
        $lines = $diff > 0
            ? [['code' => ChartOfAccounts::INVENTORY, 'debit' => $abs], ['code' => ChartOfAccounts::OPENING, 'credit' => $abs]]
            : [['code' => ChartOfAccounts::OPENING, 'debit' => $abs], ['code' => ChartOfAccounts::INVENTORY, 'credit' => $abs]];
        $this->ledger->post($end, __('finance.memo_inventory_sync'), $lines, ['source_type' => 'adjustment', 'by' => $by]);

        return $diff;
    }

    /** @return list<array{month: string, closed_at: string}> */
    public function closedMonths(): array
    {
        return Period::query()->whereNotNull('closed_at')->orderByDesc('month')->limit(24)->get()
            ->map(fn (Period $p) => ['month' => $p->month->format('Y-m'), 'closed_at' => $p->closed_at->toIso8601String()])->all();
    }

    private function storeId(): int
    {
        return (int) $this->current->getOrFail()->getKey();
    }
}
