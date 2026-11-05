<?php

namespace App\Core\AuditLog;

/**
 * Who did something: a user, an AI agent (acting for a user) or the system.
 */
final class Actor
{
    public function __construct(
        public readonly ActorType $type,
        public readonly ?int $userId = null,
        public readonly ?string $name = null,
    ) {}

    public static function user(int $userId): self
    {
        return new self(ActorType::User, $userId);
    }

    /** An AI agent (e.g. "cfo", "store_builder") acting on behalf of a user, or on a schedule. */
    public static function agent(string $agent, ?int $onBehalfOfUserId = null): self
    {
        return new self(ActorType::Agent, $onBehalfOfUserId, $agent);
    }

    public static function system(string $name = 'system'): self
    {
        return new self(ActorType::System, null, $name);
    }
}
