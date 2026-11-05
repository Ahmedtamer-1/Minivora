<?php

namespace App\Ai\Registry;

use App\Core\Permissions\Permission;
use Attribute;

/**
 * Marks a public method in a module's AiFunctions class as callable by the AI team.
 *
 *     #[AiFunction('store.profile', 'What the store sells, where and in which language', Permission::ProductsView)]
 *     public function profile(Store $store): array { ... }
 *
 * - name: stable dotted id the Question Understanding step picks (FR-AI-02)
 * - permission: checked against the asking user's role (FR-AI-06)
 * - needsApproval: spends money, changes prices, refunds, publishes or messages customers (FR-AI-07)
 * - agents: which executives may call it (empty = any agent with the permission)
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class AiFunction
{
    /**
     * @param  list<string>  $agents
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly Permission $permission,
        public readonly bool $needsApproval = false,
        public readonly array $agents = [],
    ) {}
}
