<?php

namespace App\Modules\Ledger\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Ledger\Models\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A cost the store paid (or owes): rent, salaries, packaging, courier invoices, ads. Each one posts
 * a journal entry; a bill paid later also posts its payment. FR-LED-05
 *
 * @property int $id
 * @property int $store_id
 * @property Carbon $expense_date
 * @property string|null $vendor
 * @property string $description
 * @property int $account_id
 * @property string $paid_from
 * @property int $amount
 * @property int $vat
 * @property Carbon|null $due_date
 * @property Carbon|null $paid_at
 * @property string|null $paid_with
 * @property int|null $journal_entry_id
 * @property int|null $payment_entry_id
 * @property Carbon|null $voided_at
 * @property int|null $created_by
 */
class Expense extends Model
{
    use BelongsToStore;

    public const PAID_FROM = ['cash', 'bank', 'card', 'payable'];

    protected $fillable = [
        'expense_date', 'vendor', 'description', 'account_id', 'paid_from', 'amount', 'vat', 'due_date',
        'paid_at', 'paid_with', 'journal_entry_id', 'payment_entry_id', 'voided_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => DateOnly::class,
            'due_date' => DateOnly::class,
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'amount' => 'integer',
            'vat' => 'integer',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isUnpaidBill(): bool
    {
        return $this->paid_from === 'payable' && $this->paid_at === null && $this->voided_at === null;
    }
}
