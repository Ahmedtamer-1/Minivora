<?php

namespace App\Modules\Builder\Events;

use App\Modules\Builder\Models\Page;
use App\Modules\Builder\Models\PageVersion;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched whenever a store page is published (e.g. SEO refreshes sitemaps and feeds).
 * FR-BLD-06, FR-BLD-07
 */
class PagePublished
{
    use Dispatchable;

    public function __construct(
        public readonly Page $page,
        public readonly PageVersion $version,
    ) {}
}
