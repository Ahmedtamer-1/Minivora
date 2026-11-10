<?php

namespace App\Modules\Ledger\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Ledger\Models\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A balanced journal entry: a header plus two or more lines. Only LedgerService creates these;
 * once saved an entry is never edited, only reversed. FR-LED-02, FR-LED-03
 *
 * @property int $id
 * @property int $store_id
 * @property int $number
 * @property Carbon $entry_date
 * @property string $memo
 * @property string $source_type
 * @property int|null $source_id
 * @property string|null $source_key
 * @property int|null $reversal_of_id
 * @property int|null $reversed_by_id
 * @property int $total_debit
 * @property int $total_credit
 * @property int|null $posted_by
 * @property string $posted_by_type
 * @property Carbon $created_at
 */
class JournalEntry extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'number', 'entry_date', 'memo', 'source_type', 'source_id', 'source_key', 'reversal_of_id',
        'total_debit', 'total_credit', 'posted_by', 'posted_by_type',
    ];

    protected $attributes = ['source_type' => 'manual', 'posted_by_type' => 'system', 'memo' => ''];

    protected function casts(): array
    {
        return [
            'entry_date' => DateOnly::class,
            'number' => 'integer',
            'total_debit' => 'integer',
            'total_credit' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Second guard behind the service (the database check is the third on MySQL). FR-LED-03
        static::saving(function (self $entry): void {
            if ($entry->total_debit !== $entry->total_credit) {
                throw new LogicException("Journal entry is not balanced: debits {$entry->total_debit}, credits {$entry->total_credit}.");
            }
        });
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderByDesc('debit')->orderBy('id');
    }

    public function isBalanced(): bool
    {
        return $this->total_debit === $this->total_credit;
    }
}
