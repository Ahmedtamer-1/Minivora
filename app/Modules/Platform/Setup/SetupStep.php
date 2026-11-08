<?php

namespace App\Modules\Platform\Setup;

use App\Modules\Platform\Models\Store;

/**
 * One item on the setup checklist (FR-ONB-10). Each module contributes its own step, so the checklist
 * grows as modules ship. The AI CEO reads the first incomplete step to nudge the owner.
 */
interface SetupStep
{
    /** Stable key, e.g. "add_products". */
    public function key(): string;

    public function isComplete(Store $store): bool;

    /** False while the module behind this step is not built yet. */
    public function isAvailable(): bool;

    /** Dashboard route name that completes this step, if any. */
    public function route(): ?string;
}
