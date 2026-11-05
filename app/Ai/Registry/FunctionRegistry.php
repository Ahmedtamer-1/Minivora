<?php

namespace App\Ai\Registry;

use App\Core\AuditLog\ActivityLogger;
use App\Core\AuditLog\Actor;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Models\Store;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Collects every module's AiFunctions and calls them with permission checks. FR-AI-06
 *
 * The AI team never queries the database itself: it calls these functions, which call the same
 * services the dashboard uses. Functions that need approval are not run here; the caller must
 * route them through the approvals flow (FR-AI-07, built with the AI team).
 */
class FunctionRegistry
{
    /** @var array<string, RegisteredFunction> */
    private array $functions = [];

    /**
     * @param  list<class-string>  $classes
     */
    public function __construct(
        array $classes,
        private readonly CurrentStore $currentStore,
        private readonly ActivityLogger $activity,
    ) {
        foreach ($classes as $class) {
            $this->register($class);
        }
    }

    /** @param  class-string  $class */
    public function register(string $class): void
    {
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(AiFunction::class) as $attribute) {
                /** @var AiFunction $meta */
                $meta = $attribute->newInstance();

                if (isset($this->functions[$meta->name])) {
                    throw new InvalidArgumentException("AI function {$meta->name} is registered twice.");
                }

                $parameters = [];
                foreach ($method->getParameters() as $param) {
                    $type = $param->getType();
                    $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
                    if ($typeName === Store::class || $typeName === User::class) {
                        continue; // injected by the registry, never chosen by the model
                    }
                    $parameters[] = ['name' => $param->getName(), 'type' => $typeName, 'optional' => $param->isOptional()];
                }

                $this->functions[$meta->name] = new RegisteredFunction($meta, $class, $method->getName(), $parameters);
            }
        }
    }

    /** @return array<string, RegisteredFunction> */
    public function all(): array
    {
        return $this->functions;
    }

    public function get(string $name): RegisteredFunction
    {
        return $this->functions[$name] ?? throw new InvalidArgumentException("Unknown AI function {$name}.");
    }

    /**
     * Functions this user may call in this store (what the agents are allowed to see).
     *
     * @return list<RegisteredFunction>
     */
    public function availableTo(User $user, Store $store, ?string $agent = null): array
    {
        return array_values(array_filter(
            $this->functions,
            fn (RegisteredFunction $f) => $user->canIn($store, $f->meta->permission)
                && ($agent === null || $f->meta->agents === [] || in_array($agent, $f->meta->agents, true)),
        ));
    }

    /**
     * Run a read-only or already-approved function as an agent acting for a user.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function call(string $name, array $arguments, User $asker, Store $store, string $agent, bool $approved = false): mixed
    {
        $function = $this->get($name);

        if (! $asker->canIn($store, $function->meta->permission)) {
            throw new AiFunctionDenied("{$asker->email} may not call {$name} in store {$store->id}.");
        }

        if ($function->meta->agents !== [] && ! in_array($agent, $function->meta->agents, true)) {
            throw new AiFunctionDenied("Agent {$agent} may not call {$name}.");
        }

        if ($function->meta->needsApproval && ! $approved) {
            throw new AiFunctionDenied("{$name} needs owner approval before it runs.");
        }

        $result = $this->currentStore->runAs($store, function () use ($function, $arguments, $store, $asker) {
            $instance = app($function->class);
            $method = new ReflectionMethod($instance, $function->method);

            $args = [];
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
                $args[] = match (true) {
                    $typeName === Store::class => $store,
                    $typeName === User::class => $asker,
                    array_key_exists($param->getName(), $arguments) => $arguments[$param->getName()],
                    $param->isDefaultValueAvailable() => $param->getDefaultValue(),
                    default => throw new InvalidArgumentException("Missing argument {$param->getName()} for {$function->meta->name}."),
                };
            }

            return $method->invokeArgs($instance, $args);
        });

        $this->activity->log('ai.function_called', $store, [
            'function' => $name,
            'arguments' => $arguments,
        ], Actor::agent($agent, $asker->getKey()), $store->getKey());

        return $result;
    }
}
