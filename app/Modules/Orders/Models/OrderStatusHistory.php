<?php

namespace App\Modules\Orders\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Orders\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One status change: from, to, who (user, AI agent or system) and when. FR-ORD-02
 *
 * @property int $id
 * @property int $order_id
 * @property OrderStatus|null $from_status
 * @property OrderStatus $to_status
 * @property string $actor_type
 * @property int|null $actor_id
 * @property string|null $actor_name
 * @property string|null $note
 * @property Carbon $created_at
 */
class OrderStatusHistory extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'from_status', 'to_status', 'actor_type', 'actor_id', 'actor_name', 'note'];

    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
            'actor_id' => 'integer',
        ];
    }
}
