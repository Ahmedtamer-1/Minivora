<?php

namespace App\Modules\Ledger\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Ledger\Enums\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One account in a store's chart of accounts. FR-LED-01
 *
 * @property int $id
 * @property int $store_id
 * @property string $code
 * @property string $name_en
 * @property string $name_ar
 * @property AccountType $type
 * @property string $subtype
 * @property bool $is_system
 * @property bool $is_active
 */
class Account extends Model
{
    use BelongsToStore;

    protected $fillable = ['code', 'name_en', 'name_ar', 'type', 'subtype', 'is_system', 'is_active'];

    protected $attributes = ['subtype' => 'other', 'is_system' => false, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function name(string $locale): string
    {
        return $locale === 'ar' && $this->name_ar !== '' ? $this->name_ar : $this->name_en;
    }

    /** Signed balance in the account's normal direction from debit and credit totals. */
    public function signed(int $debit, int $credit): int
    {
        return $this->type->debitNormal() ? $debit - $credit : $credit - $debit;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'type' => $this->type->value,
            'subtype' => $this->subtype,
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
        ];
    }
}
