<?php

use App\Core\Permissions\Role;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('minivora.ai.providers.groq.keys', []);
    config()->set('minivora.ai.providers.openrouter.keys', []);
    config()->set('minivora.ai.providers.gemini.keys', []);
});

test('the AI page opens inside the selected store', function () {
    signedInMember();

    $this->get(route('ai.chat', ['q' => 'What should I restock?']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('ai-chat/index')
            ->where('connected', false)
            ->where('initialQuestion', 'What should I restock?'));
});

test('without model keys the assistant answers from store data', function () {
    [, $store] = signedInMember();
    app(CurrentStore::class)->runAs($store, function () {
        Product::factory()->live()->create(['name' => 'Leather bag', 'stock' => 2]);
        Product::factory()->create(['name' => 'Scarf', 'stock' => 20]);
    });

    $this->postJson(route('ai.chat.send'), ['message' => 'What should I restock?'])
        ->assertOk()
        ->assertJsonPath('source', 'local')
        ->assertJsonPath('actions.0.href', '/products?filter=low')
        ->assertJson(fn ($json) => $json->where('reply', fn (string $r) => str_contains($r, 'Leather bag (2)'))->etc());

    $this->postJson(route('ai.chat.send'), ['message' => 'how many products do I have'])
        ->assertJsonPath('reply', 'You have 2 products: 1 live and 1 in drafts.');
});

test('the assistant never invents sales numbers', function () {
    signedInMember();

    $this->postJson(route('ai.chat.send'), ['message' => 'How were my sales today?'])
        ->assertOk()
        ->assertJson(fn ($json) => $json->where('reply', fn (string $r) => str_contains($r, "isn't live yet"))->etc());
});

test('"add …" offers a product draft only to members who can manage products', function () {
    signedInMember();
    $this->postJson(route('ai.chat.send'), ['message' => 'add a leather bag for 1250'])
        ->assertJsonPath('actions.0.post', '/products')
        ->assertJsonPath('actions.0.data.note', 'add a leather bag for 1250');

    signedInMember(Role::Accountant);
    $this->postJson(route('ai.chat.send'), ['message' => 'add a leather bag for 1250'])
        ->assertJsonPath('actions', []);
});

test('with a key the model phrases the reply from the store facts', function () {
    config()->set('minivora.ai.providers.groq.keys', ['k1', 'k2']);
    Http::fake([
        'api.groq.com/*' => Http::sequence()
            ->push(['error' => 'rate limited'], 429)
            ->push(['choices' => [['message' => ['content' => 'Hello from the model']]]]),
    ]);
    signedInMember();

    $this->postJson(route('ai.chat.send'), ['message' => 'hi', 'history' => [['role' => 'assistant', 'content' => 'Welcome']]])
        ->assertJsonPath('reply', 'Hello from the model')
        ->assertJsonPath('source', 'ai');

    Http::assertSentCount(2); // First key was rate limited, the second answered.
    Http::assertSent(fn ($request) => $request['messages'][0]['role'] === 'system'
        && str_contains($request['messages'][0]['content'], 'Never invent numbers')
        && end($request->data()['messages'])['content'] === 'hi');
});

test('a failing model falls back to built-in replies', function () {
    config()->set('minivora.ai.providers.groq.keys', ['k1']);
    Http::fake(['*' => Http::response('down', 500)]);
    signedInMember();

    $this->postJson(route('ai.chat.send'), ['message' => 'thanks'])
        ->assertJsonPath('source', 'local')
        ->assertJsonPath('reply', "Anytime. I'm here when you need me.");
});

test('questions are capped per store per day', function () {
    config()->set('minivora.ai.daily_question_cap', 2);
    signedInMember();

    $this->postJson(route('ai.chat.send'), ['message' => 'hi'])->assertOk();
    $this->postJson(route('ai.chat.send'), ['message' => 'hi'])->assertOk();
    $this->postJson(route('ai.chat.send'), ['message' => 'hi'])->assertStatus(429);
});

test('guests cannot use the assistant', function () {
    $this->postJson(route('ai.chat.send'), ['message' => 'hi'])->assertUnauthorized();
});

test('FR-AI-11 a retired or paid model is swapped for a free one the key can use', function () {
    config()->set('minivora.ai.providers.groq.keys', []);
    config()->set('minivora.ai.providers.openrouter.keys', ['or-key']);
    config()->set('minivora.ai.providers.openrouter.model', 'meta-llama/llama-3.3-70b-instruct');
    Http::fake([
        'openrouter.ai/api/v1/models' => Http::response(['data' => [
            ['id' => 'some/paid-model'],
            ['id' => 'acme/chat-model:free'],
        ]]),
        'openrouter.ai/api/v1/chat/completions' => function ($request) {
            return $request['model'] === 'acme/chat-model:free'
                ? Http::response(['choices' => [['message' => ['content' => 'Hi from a free model']]]])
                : Http::response(['error' => ['message' => 'Insufficient credits for this model']], 402);
        },
    ]);
    signedInMember();

    $this->postJson(route('ai.chat.send'), ['message' => 'hi'])
        ->assertJsonPath('reply', 'Hi from a free model')
        ->assertJsonPath('source', 'ai');

    Http::assertNotSent(fn ($request) => ($request['model'] ?? null) === 'some/paid-model');
});

test('FR-AI-11 a bad key is not treated as a model problem', function () {
    config()->set('minivora.ai.providers.groq.keys', ['bad']);
    Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'Invalid API Key']], 401)]);
    signedInMember();

    $this->postJson(route('ai.chat.send'), ['message' => 'thanks'])->assertJsonPath('source', 'local');

    Http::assertSentCount(1); // No model lookup for a bad key.
});
