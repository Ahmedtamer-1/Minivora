<?php

namespace App\Modules\Ledger\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Modules\Accounts\Models\User;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Ledger\Models\Expense;
use App\Modules\Ledger\Models\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recording costs (UC-10, FR-LED-05). Paid now: Dr expense (+ Dr VAT recoverable), Cr cash, bank
 * or card. Paid later: Cr accounts payable, then "mark paid" posts Dr payable, Cr cash or bank.
 * Voiding reverses whatever was posted; nothing is ever deleted from the books.
 */
class ExpenseService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @param  array{expense_date: string, vendor?: string|null, description: string, account_id: int, paid_from: string, amount: int, vat?: int, due_date?: string|null}  $data  piasters
     */
    public function record(array $data, ?User $by = null): Expense
    {
        $account = Account::query()->find($data['account_id']);
        if ($account === null || $account->type !== AccountType::Expense) {
            throw ValidationException::withMessages(['account_id' => __('finance.expense_account_needed')]);
        }
        $vat = (int) ($data['vat'] ?? 0);
        if ($vat < 0 || $vat >= $data['amount']) {
            throw ValidationException::withMessages(['vat' => __('finance.vat_too_big')]);
        }

        return DB::transaction(function () use ($data, $account, $vat, $by) {
            $paidNow = $data['paid_from'] !== 'payable';
            $expense = Expense::query()->create([
                'expense_date' => $data['expense_date'],
                'vendor' => isset($data['vendor']) && trim($data['vendor']) !== '' ? trim($data['vendor']) : null,
                'description' => trim($data['description']),
                'account_id' => $account->id,
                'paid_from' => $data['paid_from'],
                'amount' => $data['amount'],
                'vat' => $vat,
                'due_date' => $paidNow ? null : ($data['due_date'] ?? null),
                'paid_at' => $paidNow ? Carbon::parse($data['expense_date']) : null,
                'paid_with' => $paidNow ? $data['paid_from'] : null,
                'created_by' => $by?->id,
            ]);

            $entry = $this->ledger->post($data['expense_date'], $this->memo($expense), PostingRules::expense($account->id, $expense->amount, $vat, $expense->paid_from), [
                'source_type' => 'expense', 'source_id' => $expense->id, 'source_key' => "expense:{$expense->id}", 'by' => $by,
            ]);
            $expense->journal_entry_id = $entry?->id;
            $expense->save();
            $this->activity->log('expense.recorded', $expense, ['amount' => $expense->amount, 'account' => $account->code]);

            return $expense;
        });
    }

    /** Pay a bill that was recorded as "pay later". */
    public function markPaid(Expense $expense, string $paidWith, string $date, ?User $by = null): Expense
    {
        if (! $expense->isUnpaidBill()) {
            throw ValidationException::withMessages(['expense' => __('finance.not_unpaid_bill')]);
        }

        return DB::transaction(function () use ($expense, $paidWith, $date, $by) {
            $entry = $this->ledger->post($date, __('finance.memo_bill_paid', ['what' => $this->memo($expense)]), PostingRules::billPayment($expense->amount, $paidWith), [
                'source_type' => 'expense', 'source_id' => $expense->id, 'source_key' => "expense:{$expense->id}:payment", 'by' => $by,
            ]);
            $expense->forceFill(['paid_at' => Carbon::parse($date), 'paid_with' => $paidWith, 'payment_entry_id' => $entry?->id])->save();
            $this->activity->log('expense.paid', $expense, ['amount' => $expense->amount]);

            return $expense;
        });
    }

    public function void(Expense $expense, ?User $by = null): Expense
    {
        if ($expense->voided_at !== null) {
            return $expense;
        }

        return DB::transaction(function () use ($expense, $by) {
            foreach ([$expense->payment_entry_id, $expense->journal_entry_id] as $id) {
                $entry = $id !== null ? JournalEntry::query()->find($id) : null;
                if ($entry !== null && $entry->reversed_by_id === null) {
                    $this->ledger->reverse($entry, $by);
                }
            }
            $expense->forceFill(['voided_at' => Carbon::now()])->save();
            $this->activity->log('expense.voided', $expense, ['amount' => $expense->amount]);

            return $expense;
        });
    }

    /** @return array<string, mixed> */
    public function toRow(Expense $e, string $locale): array
    {
        return [
            'id' => $e->id,
            'date' => $e->expense_date->toDateString(),
            'vendor' => $e->vendor,
            'description' => $e->description,
            'account_id' => $e->account_id,
            'account' => $e->account?->name($locale),
            'account_code' => $e->account?->code,
            'paid_from' => $e->paid_from,
            'amount' => $e->amount,
            'vat' => $e->vat,
            'due_date' => $e->due_date?->toDateString(),
            'paid_at' => $e->paid_at?->toDateString(),
            'paid_with' => $e->paid_with,
            'unpaid' => $e->isUnpaidBill(),
            'overdue' => $e->isUnpaidBill() && $e->due_date !== null && $e->due_date->lt(Carbon::now('Africa/Cairo')->startOfDay()),
            'voided' => $e->voided_at !== null,
            'entry_id' => $e->journal_entry_id,
        ];
    }

    private function memo(Expense $e): string
    {
        return $e->vendor !== null ? "{$e->description} · {$e->vendor}" : $e->description;
    }
}
