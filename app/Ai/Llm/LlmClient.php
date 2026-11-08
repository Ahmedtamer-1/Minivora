<?php

// One entry point for all model calls; picks provider, rotates keys, retries on limits. FR-AI-11

namespace App\Ai\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmClient
{
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
                        : $this->openAiCompatible($provider, $key, $system, $messages);
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

    /** @param array<string, mixed> $provider */
    private function openAiCompatible(array $provider, string $key, string $system, array $messages): ?string
    {
        $response = Http::withToken($key)->timeout(25)->acceptJson()->post($provider['url'], [
            'model' => $provider['model'],
            'temperature' => 0.4,
            'max_tokens' => 600,
            'messages' => [['role' => 'system', 'content' => $system], ...$messages],
        ]);

        if (! $response->successful()) {
            Log::warning('AI provider call failed', ['status' => $response->status(), 'url' => $provider['url']]);

            return null; // Rate limit or bad key: try the next key.
        }

        return $response->json('choices.0.message.content');
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
