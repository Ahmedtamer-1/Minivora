<?php

namespace App\Modules\Cart\Data;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Validated add-to-cart input DTO. FR-CRT-02
 */
#[TypeScript]
class AddToCartData extends Data
{
    public function __construct(
        #[Min(1)]
        public int $product_id,
        public ?int $variant_id = null,
        #[Min(1)]
        public int $quantity = 1,
    ) {}
}
