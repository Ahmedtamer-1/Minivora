<?php

namespace App\Modules\Platform\Events;

use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired when a store leaves "opening soon". Later: SEO sitemap ping, AI CEO congratulation. */
class StorePublished
{
    use Dispatchable;

    public function __construct(public readonly Store $store) {}
}
