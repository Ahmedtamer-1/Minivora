<?php

namespace App\Core\Tenancy;

use App\Modules\Platform\Models\Store;
use Closure;

/**
 * Holds the store for the current request (set by middleware). Everything store-scoped reads it.
 * Bound as a singleton. NFR-SEC-05
 */
class CurrentStore
{
    private ?Store $store = null;

    public function set(?Store $store): void
    {
        $this->store = $store;
    }

    public function get(): ?Store
    {
        return $this->store;
    }

    public function id(): ?int
    {
        return $this->store?->getKey();
    }

    public function has(): bool
    {
        return $this->store !== null;
    }

    public function getOrFail(): Store
    {
        if ($this->store === null) {
            throw new NoCurrentStoreException('No store is selected for this request.');
        }

        return $this->store;
    }

    /**
     * Run a callback as if the given store were current, then restore the previous one.
     * Used by jobs, scheduled agents and tests.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(Store $store, Closure $callback): mixed
    {
        $previous = $this->store;
        $this->store = $store;

        try {
            return $callback();
        } finally {
            $this->store = $previous;
        }
    }
}
