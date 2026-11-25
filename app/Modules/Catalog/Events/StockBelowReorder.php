<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when product or variant stock drops at or below the product's reorder threshold. FR-CAT-17
 */
class StockBelowReorder
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly ?ProductVariant $variant,
        public readonly int $stock,
    ) {}
}
