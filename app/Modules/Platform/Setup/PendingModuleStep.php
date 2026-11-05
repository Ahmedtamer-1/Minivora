<?php

namespace App\Modules\Platform\Setup;

use App\Modules\Platform\Models\Store;

/**
 * Placeholder step for a module that is not built yet (catalog, payments, shipping).
 * The module replaces it with a real step in config/minivora.php when it ships.
 */
class PendingModuleStep implements SetupStep
{
    public function __construct(private readonly string $key) {}

    public function key(): string
    {
        return $this->key;
    }

    public function isComplete(Store $store): bool
    {
        return false;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function route(): ?string
    {
        return null;
    }
}
