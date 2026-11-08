<?php

// Dashboard AI chat: the full-screen assistant page and its message endpoint.

namespace App\Ai\Http\Controllers;

use App\Ai\Chat\StoreAssistant;
use App\Ai\Llm\LlmClient;
use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(
        private readonly CurrentStore $current,
        private readonly StoreAssistant $assistant,
        private readonly LlmClient $llm,
    ) {}

    public function show(Request $request): Response
    {
        $q = $request->query('q');

        return Inertia::render('ai-chat/index', [
            'connected' => $this->llm->available(),
            'initialQuestion' => is_string($q) ? mb_substr(trim($q), 0, 1000) : null,
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['array', 'max:12'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:4000'],
        ]);

        $store = $this->current->getOrFail();
        /** @var User $user */
        $user = $request->user();

        // Questions per store per day (NFR-SCL-02).
        $key = "ai-chat:{$store->id}:".now()->toDateString();
        if (RateLimiter::tooManyAttempts($key, (int) config('minivora.ai.daily_question_cap', 50))) {
            return response()->json(['message' => __('dashboard.ai.daily_cap')], 429);
        }
        RateLimiter::hit($key, 86400);

        return response()->json($this->assistant->answer(
            $store,
            $user,
            trim($data['message']),
            array_values($data['history'] ?? []),
            app()->getLocale(),
        ));
    }
}
