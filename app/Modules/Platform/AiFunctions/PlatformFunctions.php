<?php

namespace App\Modules\Platform\AiFunctions;

use App\Ai\Registry\AiFunction;
use App\Core\Permissions\Permission;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Services\SetupChecklistService;

/**
 * What the AI team may ask the Platform module. Read-only for now.
 */
class PlatformFunctions
{
    public function __construct(private readonly SetupChecklistService $checklist) {}

    /** @return array<string, mixed> */
    #[AiFunction('store.profile', 'Store name, address, status, language and what it sells (onboarding answers).', Permission::ProductsView)]
    public function profile(Store $store): array
    {
        return [
            'name' => $store->name,
            'subdomain' => $store->subdomain,
            'url' => $store->url(),
            'status' => $store->status()->value,
            'default_locale' => $store->default_locale,
            'profile' => $store->profileData()->toArray(),
            'created_at' => $store->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    #[AiFunction('store.setup_checklist', 'Setup progress and the next step the owner should take.', Permission::ProductsView, agents: ['ceo'])]
    public function setupChecklist(Store $store): array
    {
        return $this->checklist->for($store);
    }
}
