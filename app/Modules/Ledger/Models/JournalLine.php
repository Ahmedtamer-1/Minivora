<?php

namespace App\Modules\Ledger\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Ledger\Models\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One side of a journal entry: a debit or a credit to one account, never both. FR-LED-03
 *
 * @property int $id
 * @property int $store_id
 * @property int $journal_entry_id
 * @property int $account_id
 * @property int $debit
 * @property int $credit
 * @property string|null $memo
 * @property Carbon $entry_date
 */
class JournalLine extends Model
{
    use BelongsToStore;

    protected $fillable = ['journal_entry_id', 'account_id', 'debit', 'credit', 'memo', 'entry_date'];

    protected function casts(): array
    {
        return ['debit' => 'integer', 'credit' => 'integer', 'entry_date' => DateOnly::class];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
