<?php

namespace App\Modules\Platform\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The three optional onboarding answers (FR-ONB-04). The Store Builder agent reads this to draft the
 * store (FR-ONB-05, FR-BLD-05), so keep it structured and stable.
 */
#[TypeScript]
class StoreProfileData extends Data
{
    public function __construct(
        #[Max(500)]
        public ?string $sells = null,       // "what you sell", free text in Arabic or English
        #[Max(100)]
        public ?string $location = null,    // governorate or city
        #[In(['ar', 'en'])]
        public ?string $language = null,    // storefront language
    ) {}

    public function isEmpty(): bool
    {
        return $this->sells === null && $this->location === null && $this->language === null;
    }
}
