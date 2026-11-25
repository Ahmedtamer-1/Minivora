<?php

namespace App\Modules\Fpa\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saved what-if financial scenario with baseline, assumptions, and projected piaster figures. FR-FPA-03
 *
 * @property int $id
 * @property int $store_id
 * @property string $name
 * @property array<string, float> $assumptions
 * @property array<string, int> $baseline
 * @property array<string, int> $projected
 * @property int|null $created_by
 */
class Scenario extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'name',
        'assumptions',
        'baseline',
        'projected',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'assumptions' => 'array',
            'baseline' => 'array',
            'projected' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
