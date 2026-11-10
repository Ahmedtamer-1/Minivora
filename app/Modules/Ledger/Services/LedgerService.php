<?php

namespace App\Modules\Ledger\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Models\Period;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only way money gets into the books. Writes an entry and its lines in one transaction and
 * refuses anything unbalanced, one-sided, zero, negative, or dated inside a closed month.
 * Automatic postings carry a source key, so posting the same business event twice is a no-op.
 * FR-LED-02, FR-LED-03, FR-LED-07
 */
class LedgerService
{
    public function __construct(
        private readonly CurrentStore $current,
        private readonly ChartOfAccounts $chart,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Post a journal entry.
     *
     * @param  list<array{code?: string, account_id?: int, debit?: int, credit?: int, memo?: string|null}>  $lines  piasters
     * @param  array{source_type?: string, source_id?: int|null, source_key?: string|null, by?: User|null, actor?: string, auto?: bool, reversal_of_id?: int|null}  $meta
     *                                                                                                                                                                     auto: an event-driven posting; if its date falls in a closed month it moves to today instead of failing
     * @return JournalEntry|null null when every line is zero (nothing happened in money terms)
     */
    public function post(Carbon|string $date, string $memo, array $lines, array $meta = []): ?JournalEntry
    {
        $storeId = $this->current->getOrFail()->getKey();
        $key = $meta['source_key'] ?? null;
        if ($key !== null && ($existing = JournalEntry::query()->where('source_key', $key)->first())) {
            return $existing;
        }

        $date = Carbon::parse($date)->startOfDay();
        $accounts = $this->chart->all($storeId)->keyBy('id');

        $clean = [];
        $debit = 0;
        $credit = 0;
        foreach ($lines as $i => $line) {
            $d = (int) ($line['debit'] ?? 0);
            $c = (int) ($line['credit'] ?? 0);
            if ($d < 0 || $c < 0) {
                throw ValidationException::withMessages(["lines.{$i}" => __('finance.line_negative')]);
            }
            if ($d === 0 && $c === 0) {
                continue;
            }
            if ($d > 0 && $c > 0) {
                throw ValidationException::withMessages(["lines.{$i}" => __('finance.line_both_sides')]);
            }
            $account = isset($line['code']) ? $this->chart->get($storeId, $line['code']) : $accounts->get((int) ($line['account_id'] ?? 0));
            if (! $account instanceof Account) {
                throw ValidationException::withMessages(["lines.{$i}.account_id" => __('finance.account_unknown')]);
            }
            $clean[] = ['account_id' => $account->id, 'debit' => $d, 'credit' => $c, 'memo' => $line['memo'] ?? null];
            $debit += $d;
            $credit += $c;
        }

        if ($clean === []) {
            return null;
        }
        if (count($clean) < 2 || $debit !== $credit) {
            throw ValidationException::withMessages(['lines' => __('finance.unbalanced', [
                'debit' => number_format($debit / 100, 2), 'credit' => number_format($credit / 100, 2),
            ])]);
        }

        if ($this->isClosed($date)) {
            if (! ($meta['auto'] ?? false)) {
                throw ValidationException::withMessages(['entry_date' => __('finance.period_closed', ['month' => $date->format('Y-m')])]);
            }
            // A late event (a refund for last month's order) lands in the first open day.
            $date = $this->firstOpenDate(Carbon::now('Africa/Cairo')->startOfDay());
        }

        $entry = DB::transaction(function () use ($date, $memo, $clean, $debit, $credit, $meta, $key) {
            $entry = JournalEntry::query()->create([
                'number' => $this->nextNumber(),
                'entry_date' => $date->toDateString(),
                'memo' => mb_substr($memo, 0, 255),
                'source_type' => $meta['source_type'] ?? 'manual',
                'source_id' => $meta['source_id'] ?? null,
                'source_key' => $key,
                'reversal_of_id' => $meta['reversal_of_id'] ?? null,
                'total_debit' => $debit,
                'total_credit' => $credit,
                'posted_by' => isset($meta['by']) ? $meta['by']->id : null,
                'posted_by_type' => $meta['actor'] ?? (isset($meta['by']) ? 'user' : 'system'),
            ]);
            $entry->lines()->createMany(array_map(fn ($l) => $l + ['entry_date' => $date->toDateString()], $clean));

            return $entry;
        });

        if (($meta['source_type'] ?? 'manual') === 'manual') {
            $this->activity->log('ledger.entry_posted', $entry, ['number' => $entry->number, 'amount' => $debit]);
        }

        return $entry;
    }

    /** Undo an entry with its mirror image. The original stays, so the history is complete. */
    public function reverse(JournalEntry $entry, ?User $by = null, Carbon|string|null $date = null, ?string $memo = null): JournalEntry
    {
        if ($entry->reversed_by_id !== null) {
            throw ValidationException::withMessages(['entry' => __('finance.already_reversed')]);
        }
        if ($entry->reversal_of_id !== null) {
            throw ValidationException::withMessages(['entry' => __('finance.cannot_reverse_reversal')]);
        }
        $date = $date !== null ? Carbon::parse($date) : Carbon::now('Africa/Cairo');
        if ($this->isClosed($date)) {
            $date = $this->firstOpenDate($date);
        }

        return DB::transaction(function () use ($entry, $by, $date, $memo) {
            $reversal = $this->post($date, $memo ?? __('finance.reversal_memo', ['number' => $entry->number, 'memo' => $entry->memo]), $entry->lines()->get()->map(fn ($l) => [
                'account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'memo' => $l->memo,
            ])->all(), [
                'source_type' => 'reversal',
                'source_id' => $entry->id,
                'source_key' => 'reversal:'.$entry->id,
                'reversal_of_id' => $entry->id,
                'by' => $by,
                'auto' => true,
            ]);
            $entry->reversed_by_id = $reversal?->id;
            $entry->save();
            $this->activity->log('ledger.entry_reversed', $entry, ['number' => $entry->number]);

            return $reversal;
        });
    }

    public function isClosed(Carbon $date): bool
    {
        return Period::query()->where('month', $date->copy()->startOfMonth()->toDateString())->whereNotNull('closed_at')->exists();
    }

    /** The first date on or after $from that is not inside a closed month. */
    public function firstOpenDate(Carbon $from): Carbon
    {
        $date = $from->copy()->startOfDay();
        for ($i = 0; $i < 36 && $this->isClosed($date); $i++) {
            $date = $date->copy()->addMonthNoOverflow()->startOfMonth();
        }

        return $date;
    }

    private function nextNumber(): int
    {
        return (int) JournalEntry::query()->lockForUpdate()->max('number') + 1;
    }
}
