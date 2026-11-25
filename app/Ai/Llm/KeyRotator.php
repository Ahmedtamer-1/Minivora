<?php

namespace App\Ai\Llm;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rotates API keys across ordered free LLM providers (gemini, groq, openrouter) and fails over
 * automatically on rate limits, 5xx responses, or provider unavailability. FR-AI-11, NFR-REL-02, V-14
 */
class KeyRotator
{
    /** @var list<string> */
    public const DEFAULT_PROVIDER_ORDER = ['gemini', 'groq', 'openrouter'];

    /** @var array<string, array{keys: list<string>, model?: string, url?: string}>|null */
    private ?array $providers;

    /** @var list<string> */
    private array $providerOrder;

    /** @var array<string, bool> */
    private array $exhaustedKeys = [];

    private ?string $lastProvider = null;

    private ?string $lastKey = null;

    /**
     * @param  array<string, array<string, mixed>>|null  $providers
     * @param  list<string>  $providerOrder
     */
    public function __construct(?array $providers = null, array $providerOrder = self::DEFAULT_PROVIDER_ORDER)
    {
        $this->providers = $providers;
        $this->providerOrder = $providerOrder;
    }

    /**
     * Ordered map of configured providers and their non-empty keys.
     *
     * @return array<string, array{keys: list<string>, model?: string, url?: string}>
     */
    public function providers(): array
    {
        $raw = $this->providers ?? (array) config('minivora.ai.providers', []);
        $ordered = [];

        foreach ($this->providerOrder as $name) {
            if (isset($raw[$name]) && is_array($raw[$name])) {
                $ordered[$name] = $this->normalizeProvider($raw[$name]);
            }
        }

        foreach ($raw as $name => $provider) {
            if (! isset($ordered[$name]) && is_array($provider)) {
                $ordered[(string) $name] = $this->normalizeProvider($provider);
            }
        }

        return $ordered;
    }

    public function hasAvailableKeys(): bool
    {
        foreach ($this->providers() as $name => $provider) {
            foreach ($provider['keys'] as $key) {
                if (! $this->isExhausted($name, $key)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Execute an LLM call across ordered providers and keys, failing over on errors or null results.
     *
     * @template T
     *
     * @param  callable(string, string, array<string, mixed>): T  $invoker
     * @return T
     *
     * @throws AiUnavailableException
     */
    public function execute(callable $invoker): mixed
    {
        $errors = [];

        foreach ($this->providers() as $providerName => $providerConfig) {
            foreach ($providerConfig['keys'] as $key) {
                if ($this->isExhausted($providerName, $key)) {
                    continue;
                }

                try {
                    $result = $invoker($providerName, $key, $providerConfig);

                    if ($result !== null) {
                        $this->lastProvider = $providerName;
                        $this->lastKey = $key;

                        return $result;
                    }

                    $this->markExhausted($providerName, $key);
                    $errors[] = "{$providerName}: empty response";
                } catch (Throwable $e) {
                    $this->markExhausted($providerName, $key);
                    $errors[] = "{$providerName}: {$e->getMessage()}";
                    Log::warning('AI provider key failed, failing over', [
                        'provider' => $providerName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $detail = $errors === [] ? 'no configured provider keys' : implode('; ', $errors);

        throw new AiUnavailableException("All AI providers are unavailable ({$detail}).");
    }

    public function reset(): void
    {
        $this->exhaustedKeys = [];
    }

    public function lastUsedProvider(): ?string
    {
        return $this->lastProvider;
    }

    public function lastUsedKey(): ?string
    {
        return $this->lastKey;
    }

    private function markExhausted(string $provider, string $key): void
    {
        $this->exhaustedKeys["{$provider}:{$key}"] = true;
    }

    private function isExhausted(string $provider, string $key): bool
    {
        return isset($this->exhaustedKeys["{$provider}:{$key}"]);
    }

    /**
     * @param  array<string, mixed>  $provider
     * @return array{keys: list<string>, model?: string, url?: string}
     */
    private function normalizeProvider(array $provider): array
    {
        $keys = array_values(array_filter(
            array_map(static fn (mixed $k): string => trim((string) $k), (array) ($provider['keys'] ?? [])),
            static fn (string $k): bool => $k !== '',
        ));

        return [
            ...$provider,
            'keys' => $keys,
        ];
    }
}
