<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Setup\PendingModuleStep;
use App\Modules\Platform\Setup\PublishStep;
use App\Modules\Platform\Setup\SetupStep;
use App\Modules\Platform\Setup\VerifyEmailStep;

/**
 * Setup checklist: add products, connect payment, set shipping, publish (FR-ONB-10).
 */
class SetupChecklistService
{
    /** @return list<SetupStep> */
    public function steps(): array
    {
        return [
            new VerifyEmailStep,
            new PendingModuleStep('add_products'),    // Catalog module replaces this
            new PendingModuleStep('connect_payment'), // Checkout module replaces this
            new PendingModuleStep('set_shipping'),    // Orders module replaces this
            new PublishStep,
        ];
    }

    /**
     * @return array{steps: list<array{key: string, complete: bool, available: bool, route: string|null}>, done: int, total: int, next: string|null}
     */
    public function for(Store $store): array
    {
        $steps = array_map(fn (SetupStep $step) => [
            'key' => $step->key(),
            'complete' => $step->isComplete($store),
            'available' => $step->isAvailable(),
            'route' => $step->route(),
        ], $this->steps());

        $next = null;
        foreach ($steps as $step) {
            if (! $step['complete'] && $step['available']) {
                $next = $step['key'];
                break;
            }
        }

        return [
            'steps' => $steps,
            'done' => count(array_filter($steps, fn ($s) => $s['complete'])),
            'total' => count($steps),
            'next' => $next,
        ];
    }
}
