<?php

namespace App\Modules\Fpa\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Ledger\Models\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Planned amount for one account in one month. FR-FPA-01
 *
 * @property int $id
 * @property int $store_id
 * @property Carbon $month
 * @property int $account_id
 * @property int $amount
 */
class Budget extends Model
{
    use BelongsToStore;

    protected $fillable = ['month', 'account_id', 'amount'];

    protected function casts(): array
    {
        return ['month' => DateOnly::class, 'amount' => 'integer'];
    }
}
