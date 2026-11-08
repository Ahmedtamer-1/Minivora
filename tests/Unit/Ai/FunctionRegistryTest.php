<?php

use App\Ai\Registry\AiFunctionDenied;
use App\Ai\Registry\FunctionRegistry;
use App\Core\AuditLog\ActivityLog;
use App\Core\Permissions\Role;
use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Models\Store;

test('FR-AI-06 modules register AI functions with a permission', function () {
    $registry = app(FunctionRegistry::class);

    expect(array_keys($registry->all()))->toContain('store.profile', 'store.setup_checklist')
        ->and($registry->get('store.profile')->toSchema())->toMatchArray(['name' => 'store.profile', 'needs_approval' => false, 'parameters' => []]);
});

test('FR-AI-06 agents call functions with the asking user\'s permissions, in that user\'s store', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->withMember($owner)->create(['name' => 'Nile Crafts', 'profile' => ['sells' => 'pottery', 'location' => null, 'language' => 'en']]);

    $result = app(FunctionRegistry::class)->call('store.profile', [], $owner, $store, 'ceo');

    expect($result['name'])->toBe('Nile Crafts')
        ->and($result['profile']['sells'])->toBe('pottery')
        ->and($result['status'])->toBe('opening_soon');

    // FR-AI-10: the call is logged with the agent and the user it acted for.
    $log = ActivityLog::query()->latest('id')->first();
    expect($log->action)->toBe('ai.function_called')
        ->and($log->actor_name)->toBe('ceo')
        ->and($log->actor_id)->toBe($owner->id);
});

test('FR-AI-06 a user outside the store, or an agent outside the scope, is refused', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $store = Store::factory()->withMember($owner)->create();
    $registry = app(FunctionRegistry::class);

    expect(fn () => $registry->call('store.profile', [], $stranger, $store, 'ceo'))->toThrow(AiFunctionDenied::class)
        ->and(fn () => $registry->call('store.setup_checklist', [], $owner, $store, 'cmo'))->toThrow(AiFunctionDenied::class)
        ->and($registry->call('store.setup_checklist', [], $owner, $store, 'ceo')['total'])->toBe(5);
});

test('FR-AI-06 the functions visible to a user follow their role', function () {
    $staff = User::factory()->create();
    $store = Store::factory()->withMember($staff, Role::Staff)->create();

    $names = array_map(fn ($f) => $f->meta->name, app(FunctionRegistry::class)->availableTo($staff, $store, 'cmo'));

    expect($names)->toBe(['store.profile']);
});
