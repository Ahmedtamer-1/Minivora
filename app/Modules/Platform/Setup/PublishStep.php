<?php

namespace App\Modules\Platform\Setup;

use App\Modules\Platform\Models\Store;

class PublishStep implements SetupStep
{
    public function key(): string
    {
        return 'publish';
    }

    public function isComplete(Store $store): bool
    {
        return $store->published_at !== null;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function route(): ?string
    {
        return 'settings.general';
    }
}
