<?php

namespace App\Modules\Platform\Events;

use App\Modules\Platform\Data\StoreProfileData;
use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when the owner answers (or changes) the onboarding questions. The Store Builder agent will
 * listen to this to draft categories, products, theme and homepage in the background (FR-ONB-05).
 */
class StoreProfileUpdated
{
    use Dispatchable;

    public function __construct(public readonly Store $store, public readonly StoreProfileData $profile) {}
}
