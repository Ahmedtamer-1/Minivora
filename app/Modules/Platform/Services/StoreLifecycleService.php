<?php

namespace App\Modules\Platform\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Events\StorePublished;
use App\Modules\Platform\Events\StoreSuspended;
use App\Modules\Platform\Models\Store;
use Illuminate\Validation\ValidationException;

/**
 * Store settings and lifecycle: rename, language, opening-soon password, publish, suspend, restore.
 * FR-ONB-06, FR-ONB-07, FR-ACC-08
 */
class StoreLifecycleService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function updateGeneral(Store $store, string $name, string $defaultLocale): Store
    {
        $store->fill(['name' => $name, 'default_locale' => $defaultLocale])->save();
        $this->activity->log('store.settings_updated', $store, ['name' => $name, 'default_locale' => $defaultLocale], storeId: $store->getKey());

        return $store;
    }

    /** Password shoppers enter on the "opening soon" page; null removes it (preview link only). */
    public function setStorefrontPassword(Store $store, ?string $password): Store
    {
        $store->storefront_password = $password;
        $store->save();
        $this->activity->log('store.storefront_password_changed', $store, storeId: $store->getKey());

        return $store;
    }

    /**
     * Publishing needs a verified email (FR-ONB-07); building does not.
     */
    public function publish(Store $store, User $by): Store
    {
        if (! $by->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['publish' => __('dashboard.publish.verify_email_first')]);
        }

        if ($store->isSuspended()) {
            throw ValidationException::withMessages(['publish' => __('dashboard.publish.suspended')]);
        }

        if ($store->published_at === null) {
            $store->published_at = now();
            $store->save();
            $this->activity->log('store.published', $store, storeId: $store->getKey());
            event(new StorePublished($store));
        }

        return $store;
    }

    public function unpublish(Store $store): Store
    {
        $store->published_at = null;
        $store->save();
        $this->activity->log('store.unpublished', $store, storeId: $store->getKey());

        return $store;
    }

    public function suspend(Store $store, ?string $reason = null): Store
    {
        $store->fill(['suspended_at' => now(), 'suspended_reason' => $reason])->save();
        $this->activity->log('store.suspended', $store, ['reason' => $reason], storeId: $store->getKey());
        event(new StoreSuspended($store));

        return $store;
    }

    public function restore(Store $store): Store
    {
        $store->fill(['suspended_at' => null, 'suspended_reason' => null])->save();
        $this->activity->log('store.restored', $store, storeId: $store->getKey());

        return $store;
    }
}
