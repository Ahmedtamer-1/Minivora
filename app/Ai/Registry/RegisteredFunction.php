<?php

namespace App\Ai\Registry;

/**
 * A discovered AI function: its metadata plus where to call it.
 */
final class RegisteredFunction
{
    /**
     * @param  class-string  $class
     * @param  list<array{name: string, type: string, optional: bool}>  $parameters
     */
    public function __construct(
        public readonly AiFunction $meta,
        public readonly string $class,
        public readonly string $method,
        public readonly array $parameters,
    ) {}

    /** Shape handed to the LLM tool list later (no values, only the contract). */
    public function toSchema(): array
    {
        return [
            'name' => $this->meta->name,
            'description' => $this->meta->description,
            'needs_approval' => $this->meta->needsApproval,
            'parameters' => $this->parameters,
        ];
    }
}
