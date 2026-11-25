<?php

namespace App\Modules\Marketing\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Manual or imported ad spend entry in integer piasters. FR-MI-03
 *
 * @property int $id
 * @property int $store_id
 * @property string $channel
 * @property string $campaign_name
 * @property Carbon $spend_date
 * @property int $amount
 * @property int|null $journal_entry_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AdSpend extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'channel',
        'campaign_name',
        'spend_date',
        'amount',
        'journal_entry_id',
    ];

    protected $attributes = [
        'channel' => 'meta',
    ];

    protected function casts(): array
    {
        return [
            'spend_date' => 'date',
            'amount' => 'integer',
            'journal_entry_id' => 'integer',
        ];
    }
}
