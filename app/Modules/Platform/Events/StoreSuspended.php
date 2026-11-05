<?php

namespace App\Modules\Platform\Events;

use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Events\Dispatchable;

class StoreSuspended
{
    use Dispatchable;

    public function __construct(public readonly Store $store) {}
}
