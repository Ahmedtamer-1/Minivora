<?php

// One entry point for all model calls; picks provider, rotates keys, retries on limits. FR-AI-11

namespace App\Ai\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmClient
{
    /** How many other models to try when the configured one is gone or not free. */
    private const MODEL_FALLBACKS = 2;

    /** True when at least one provider has a key. */
    public function available(): bool
    {
        foreach ((array) config('minivora.ai.providers', []) as $provider) {
            if (! empty($provider['keys'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send a chat and return the reply text, or null when every provider and key failed.
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     */
    public function chat(string $system, array $messages): ?string
    {
        foreach ((array) config('minivora.ai.providers', []) as $name => $provider) {
            foreach ((array) ($provider['keys'] ?? []) as $key) {
                try {
                    $reply = $name === 'gemini'
                        ? $this->gemini($provider, $key, $system, $messages)
                        : $this->openAiCompatible((string) $name, $provider, $key, $system, $messages);
                } catch (ConnectionException $e) {
                    Log::warning("AI provider {$name} unreachable", ['error' => $e->getMessage()]);

                    continue 2; // Provider down: skip its other keys.
                }

                if ($reply !== null && trim($reply) !== '') {
                    return trim($reply);
                }
            }
        }

        return null;
    }

    /**
     * OpenAI-style chat (Groq, OpenRouter). If the configured model no longer exists or is not
     * free (free models change often), ask the provider which models this key can use and try
     * a couple of those, so nobody has to edit model names on the server.
     *
     * @param  array<string, mixed>  $provider
     */
    private function openAiCompatible(string $name, array $provider, string $key, string $system, array $messages): ?string
    {
        $response = $this->postChat($provider, $key, (string) $provider['model'], $system, $messages);

        if (! $response->successful() && $this->modelProblem($response)) {
            foreach ($this->usableModels($name, $provider, $key) as $model) {
                $response = $this->postChat($provider, $key, $model, $system, $messages);
                if ($response->successful() || ! $this->modelProblem($response)) {
                    break;
                }
            }
        }

        if (! $response->successful()) {
            Log::warning('AI provider call failed', ['provider' => $name, 'status' => $response->status(), 'error' => mb_substr($response->body(), 0, 300)]);

            return null; // Rate limit or bad key: try the next key.
        }

        return $response->json('choices.0.message.content');
    }

    /** @param array<string, mixed> $provider */
    private function postChat(array $provider, string $key, string $model, string $system, array $messages): Response
    {
        return Http::withToken($key)->timeout(25)->acceptJson()->post($provider['url'], [
            'model' => $model,
            'temperature' => 0.4,
            'max_tokens' => 600,
            'messages' => [['role' => 'system', 'content' => $system], ...$messages],
        ]);
    }

    /** The request failed because of the model (missing, retired, or needs paid credits), not the key or a limit. */
    private function modelProblem(Response $response): bool
    {
        return in_array($response->status(), [400, 402, 403, 404, 410], true)
            && preg_match('/model|endpoint|credit|decommission|not.?found|does not exist/i', $response->body()) === 1;
    }

    /**
     * Chat models this key can use, cached for six hours. OpenRouter: only ":free" models.
     * Groq: everything except speech and safety models.
     *
     * @param  array<string, mixed>  $provider
     * @return list<string>
     */
    private function usableModels(string $name, array $provider, string $key): array
    {
        $cacheKey = "ai-models:{$name}";
        $models = Cache::get($cacheKey);
        if (! is_array($models)) {
            try {
                $list = Http::withToken($key)->timeout(15)->acceptJson()
                    ->get(str_replace('/chat/completions', '/models', (string) $provider['url']));
                $models = $list->successful() ? array_values(array_filter(array_map(fn ($m) => $m['id'] ?? null, (array) $list->json('data')))) : [];
            } catch (ConnectionException) {
                $models = [];
            }
            if ($models !== []) {
                Cache::put($cacheKey, $models, now()->addHours(6)); // Only cache a real list, so a failed lookup is retried.
            }
        }

        $usable = array_filter($models, fn (string $id) => $name === 'openrouter'
            ? str_ends_with($id, ':free')
            : preg_match('/whisper|guard|tts|orpheus|playai|distil|prompt|embed/i', $id) !== 1);

        return array_slice(array_values(array_diff($usable, [$provider['model']])), 0, self::MODEL_FALLBACKS);
    }

    /** @param array<string, mixed> $provider */
    private function gemini(array $provider, string $key, string $system, array $messages): ?string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$provider['model']}:generateContent";

        $response = Http::withHeaders(['x-goog-api-key' => $key])->timeout(25)->acceptJson()->post($url, [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => array_map(fn (array $m) => [
                'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]],
            ], $messages),
            'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 600],
        ]);

        if (! $response->successful()) {
            Log::warning('AI provider call failed', ['status' => $response->status(), 'provider' => 'gemini']);

            return null;
        }

        return $response->json('candidates.0.content.parts.0.text');
    }
}
