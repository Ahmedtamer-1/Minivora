<?php

namespace App\Modules\Platform\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Modules\Platform\Data\StoreProfileData;
use App\Modules\Platform\Events\StoreProfileUpdated;
use App\Modules\Platform\Models\Store;

/**
 * Saves the onboarding answers (FR-ONB-04) and hands them to the Store Builder via an event (FR-ONB-05).
 */
class StoreProfileService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function update(Store $store, StoreProfileData $profile): Store
    {
        $store->profile = $profile->toArray();
        $store->onboarding_completed_at ??= now();

        if ($profile->language !== null) {
            $store->default_locale = $profile->language;
        }

        $store->save();

        $this->activity->log('store.profile_updated', $store, $profile->toArray(), storeId: $store->getKey());

        if (! $profile->isEmpty()) {
            event(new StoreProfileUpdated($store, $profile));
        }

        return $store;
    }

    /** The owner skipped the questions; don't ask again. */
    public function skip(Store $store): Store
    {
        $store->onboarding_completed_at ??= now();
        $store->save();

        return $store;
    }
}
