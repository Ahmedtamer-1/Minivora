<?php

use App\Core\AuditLog\ActivityLog;
use App\Core\AuditLog\ActivityLogger;
use App\Core\AuditLog\Actor;
use App\Core\AuditLog\ActorType;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;

test('FR-ACC-05 actions are logged with who did them, including AI agents', function () {
    $store = Store::factory()->create();
    $logger = app(ActivityLogger::class);

    $logger->log('store.published', $store, [], Actor::user(7), $store->id);
    $logger->log('ai.function_called', $store, ['function' => 'store.profile'], Actor::agent('ceo', 7), $store->id);

    $agentRow = ActivityLog::query()->latest('id')->first();
    expect($agentRow->actor_type)->toBe(ActorType::Agent)
        ->and($agentRow->actor_name)->toBe('ceo')
        ->and($agentRow->actor_id)->toBe(7);
});

test('NFR-SEC-10 the activity log is append-only', function () {
    $row = app(ActivityLogger::class)->log('x');

    expect(fn () => $row->update(['action' => 'y']))->toThrow(LogicException::class)
        ->and(fn () => $row->delete())->toThrow(LogicException::class);
});

test('NFR-SEC-10 the verify command reports edited and deleted entries', function () {
    $logger = app(ActivityLogger::class);
    foreach (['a', 'b', 'c', 'd'] as $action) {
        $logger->log($action, properties: ['n' => $action]);
    }

    $this->artisan('activity-log:verify')->assertSuccessful();

    $ids = ActivityLog::query()->orderBy('id')->pluck('id')->all();
    DB::table('activity_logs')->where('id', $ids[1])->update(['action' => 'tampered']);
    expect($logger->verify())->toBe([$ids[1]]);
    $this->artisan('activity-log:verify')->assertFailed();

    DB::table('activity_logs')->where('id', $ids[1])->update(['action' => 'b']);
    DB::table('activity_logs')->where('id', $ids[2])->delete();
    expect($logger->verify())->toBe([$ids[3]]);
});
