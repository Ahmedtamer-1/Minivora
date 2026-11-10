<?php

namespace App\Modules\Ledger\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Ledger\Models\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A month of the books. Once closed, nothing can be posted with a date inside it. FR-LED-07
 *
 * @property int $id
 * @property int $store_id
 * @property Carbon $month
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property array<string, mixed>|null $checks
 */
class Period extends Model
{
    use BelongsToStore;

    protected $fillable = ['month', 'closed_at', 'closed_by', 'checks'];

    protected function casts(): array
    {
        return ['month' => DateOnly::class, 'closed_at' => 'datetime', 'checks' => 'array'];
    }
}
