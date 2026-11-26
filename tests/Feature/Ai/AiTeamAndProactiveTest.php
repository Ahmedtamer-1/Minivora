<?php

use App\Ai\Agents\Ceo\CeoAgent;
use App\Ai\Agents\Cfo\CfoAgent;
use App\Ai\Agents\Chro\ChroAgent;
use App\Ai\Agents\Cmo\CmoAgent;
use App\Ai\Agents\Coo\CooAgent;
use App\Ai\Agents\Cro\CroAgent;
use App\Ai\Agents\Cto\CtoAgent;
use App\Ai\Agents\Cxo\CxoAgent;
use App\Ai\Agents\ExecutiveAgent;
use App\Ai\Agents\Risk\RiskAgent;
use App\Ai\Approvals\ApprovalService;
use App\Ai\Eval\EvalRunner;
use App\Ai\Guards\NumberGuard;
use App\Ai\Http\Controllers\ApprovalController;
use App\Ai\Http\Controllers\InsightController;
use App\Ai\Llm\AiUnavailableException;
use App\Ai\Llm\KeyRotator;
use App\Ai\Llm\PiiMasker;
use App\Ai\Models\Approval;
use App\Ai\Models\Insight;
use App\Ai\Proactive\DailyChecks;
use App\Ai\Proactive\FeedRanker;
use App\Ai\Proactive\HourlyChecks;
use App\Ai\Proactive\MorningBrief;
use App\Ai\Proactive\WeeklyChecks;
use App\Ai\Understanding\QuestionRouter;
use App\Core\AuditLog\ActivityLog;
use App\Core\Permissions\Role;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Refund;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

test('FR-AI-05 all 9 executive agent descriptors are defined with distinct roles and domains', function () {
    $agents = [
        new CeoAgent,
        new CfoAgent,
        new CmoAgent,
        new CooAgent,
        new CroAgent,
        new CtoAgent,
        new CxoAgent,
        new ChroAgent,
        new RiskAgent,
    ];

    $keys = array_map(fn (ExecutiveAgent $a) => $a->key(), $agents);

    expect($keys)->toBe(['ceo', 'cfo', 'cmo', 'coo', 'cro', 'cto', 'cxo', 'chro', 'risk']);

    foreach ($agents as $agent) {
        $data = $agent->toArray();
        expect($data['title'])->not->toBeEmpty()
            ->and($data['title_ar'])->not->toBeEmpty()
            ->and($data['domain'])->not->toBeEmpty()
            ->and($data['functions'])->not->toBeEmpty();
    }
});

test('FR-AI-01 FR-AI-02 FR-AI-03 FR-AI-04 QuestionRouter maps Arabic/English questions with period and source and suggests on unknown', function () {
    $router = new QuestionRouter;

    $arSales = $router->route('بعت بكام النهاردة؟');
    expect($arSales['matched'])->toBeTrue()
        ->and($arSales['function'])->toBe('analytics.sales_summary')
        ->and($arSales['agent'])->toBe('cro')
        ->and($arSales['period'])->toBe('today')
        ->and($arSales['source'])->not->toBeEmpty()
        ->and($arSales['locale'])->toBe('ar');

    $enCash = $router->route('What is my cash balance today?');
    expect($enCash['matched'])->toBeTrue()
        ->and($enCash['function'])->toBe('finance.cash_balance')
        ->and($enCash['agent'])->toBe('cfo')
        ->and($enCash['period'])->toBe('today')
        ->and($enCash['source'])->toContain('ledger')
        ->and($enCash['is_financial'])->toBeTrue();

    $unknownAr = $router->route('احكيلي نكتة عن الفضاء');
    expect($unknownAr['matched'])->toBeFalse()
        ->and($unknownAr['function'])->toBeNull()
        ->and($unknownAr['suggestions'])->toHaveCount(3)
        ->and($unknownAr['locale'])->toBe('ar');

    $unknownEn = $router->route('What is the weather on Mars tomorrow?');
    expect($unknownEn['matched'])->toBeFalse()
        ->and($unknownEn['suggestions'])->toHaveCount(3)
        ->and($unknownEn['locale'])->toBe('en');
});

test('NFR-PRV-01 V-17 PiiMasker strips Egyptian phones, emails, cards, and national IDs across 100 sample prompts with 0 PII leaked', function () {
    $masker = new PiiMasker;
    $prefixes = ['010', '011', '012', '015'];

    for ($i = 0; $i < 100; $i++) {
        $prefix = $prefixes[$i % 4];
        $suffix = str_pad((string) (10000000 + $i * 7919), 8, '0', STR_PAD_LEFT);
        $phone = match ($i % 3) {
            0 => $prefix.$suffix,
            1 => '+20'.substr($prefix, 1).$suffix,
            default => '0020'.substr($prefix, 1).$suffix,
        };
        $email = "customer{$i}.egypt@example-store{$i}.com";
        $card = sprintf('4532-%04d-%04d-%04d', 1000 + $i, 2000 + $i, 3000 + $i);
        $nationalId = sprintf('29805%02d210%04d', ($i % 28) + 1, 1000 + $i);

        $prompt = "Order #{$i} by {$email}, call {$phone}, card {$card}, national ID {$nationalId}.";

        expect($masker->containsPii($prompt))->toBeTrue();

        $masked = $masker->mask($prompt);

        expect($masked)
            ->toContain('[EMAIL]')
            ->toContain('[PHONE]')
            ->toContain('[CARD]')
            ->toContain('[NATIONAL_ID]')
            ->not->toContain($email)
            ->not->toContain($phone)
            ->not->toContain($card)
            ->not->toContain($nationalId);

        expect($masker->containsPii($masked))->toBeFalse();
    }
});

test('FR-AI-11 NFR-REL-02 V-14 KeyRotator fails over across providers and throws AiUnavailableException while storefront and dashboard stay live', function () {
    $attempts = [];
    $rotator = new KeyRotator([
        'gemini' => ['keys' => ['gem-1']],
        'groq' => ['keys' => ['groq-1', 'groq-2']],
        'openrouter' => ['keys' => ['or-1']],
    ]);

    $answer = $rotator->execute(function (string $provider, string $key) use (&$attempts) {
        $attempts[] = "{$provider}:{$key}";
        if ($provider === 'gemini') {
            throw new RuntimeException('429 Too Many Requests');
        }
        if ($key === 'groq-1') {
            throw new RuntimeException('503 Service Unavailable');
        }

        return "reply-from-{$provider}-{$key}";
    });

    expect($answer)->toBe('reply-from-groq-groq-2')
        ->and($attempts)->toBe(['gemini:gem-1', 'groq:groq-1', 'groq:groq-2'])
        ->and($rotator->lastUsedProvider())->toBe('groq');

    // V-14: Disable all keys -> KeyRotator throws AiUnavailableException, storefront & dashboard still work.
    config()->set('minivora.ai.providers.gemini.keys', []);
    config()->set('minivora.ai.providers.groq.keys', []);
    config()->set('minivora.ai.providers.openrouter.keys', []);

    $emptyRotator = new KeyRotator;
    expect($emptyRotator->hasAvailableKeys())->toBeFalse()
        ->and(fn () => $emptyRotator->execute(fn () => 'never'))->toThrow(AiUnavailableException::class);

    [$owner, $store] = signedInMember();
    $this->get(route('dashboard'))->assertOk();
    $this->followingRedirects()->get("http://{$store->subdomain}.".config('minivora.domain'))->assertOk();
});

test('FR-AI-07 FR-AI-10 NFR-AIQ-08 V-15 every act-level function is refused without approval, logged, and role-gated', function () {
    [$owner, $store] = signedInMember(Role::Owner);
    $manager = User::factory()->create();
    $accountant = User::factory()->create();
    $staff = User::factory()->create();

    $store->members()->create(['user_id' => $manager->id, 'role' => Role::Manager->value]);
    $store->members()->create(['user_id' => $accountant->id, 'role' => Role::Accountant->value]);
    $store->members()->create(['user_id' => $staff->id, 'role' => Role::Staff->value]);

    $service = app(ApprovalService::class);
    $currentStore = app(CurrentStore::class);

    $currentStore->runAs($store, function () use ($service, $owner, $manager, $accountant, $staff) {
        // V-15: Every act-level function called without approval is refused 100% of the time and logged.
        foreach (ApprovalService::ACT_FUNCTIONS as $fn) {
            $ran = false;
            expect(fn () => $service->executeActFunction('cmo', $fn, ['test' => true], null, function () use (&$ran) {
                $ran = true;

                return ['ok' => true];
            }))->toThrow(AuthorizationException::class);

            expect($ran)->toBeFalse();

            $refusalLog = ActivityLog::query()->where('action', 'ai.act_refused')->latest('id')->first();
            expect($refusalLog)->not->toBeNull()
                ->and($refusalLog->properties['function'])->toBe($fn)
                ->and($refusalLog->properties['reason'])->toBe('missing_approval');
        }

        // Pending approval also refused; mismatched function also refused.
        $pending = $service->requestApproval('cmo', 'spend_budget', ['amount' => 50_000], 50_000, $owner);
        expect(fn () => $service->executeActFunction('cmo', 'spend_budget', ['amount' => 50_000], $pending, fn () => 'no'))
            ->toThrow(AuthorizationException::class);

        // Staff and Accountant receive 403 (AuthorizationException).
        expect(fn () => $service->approve($pending, $staff))->toThrow(AuthorizationException::class)
            ->and(fn () => $service->approve($pending, $accountant))->toThrow(AuthorizationException::class);

        // Manager cannot approve above limit, CAN approve within limit.
        $large = $service->requestApproval('cmo', 'spend_budget', ['amount' => 500_000], 500_000, $owner);
        expect(fn () => $service->approve($large, $manager, 100_000))->toThrow(AuthorizationException::class);

        $approvedSmall = $service->approve($pending, $manager, 100_000);
        expect($approvedSmall->status)->toBe('approved')
            ->and($approvedSmall->decided_by)->toBe($manager->id);

        // Mismatched function name is refused even with an approved record.
        expect(fn () => $service->executeActFunction('cmo', 'change_price', [], $approvedSmall, fn () => 'no'))
            ->toThrow(AuthorizationException::class);

        // Approved matching function executes, marks executed, and logs ai.act_executed (FR-AI-10).
        $result = $service->executeActFunction('cmo', 'spend_budget', ['amount' => 50_000], $approvedSmall, fn ($payload) => ['spent' => $payload['amount']]);
        expect($result)->toBe(['spent' => 50_000])
            ->and($approvedSmall->refresh()->status)->toBe('executed')
            ->and($approvedSmall->result)->toBe(['spent' => 50_000]);

        $execLog = ActivityLog::query()->where('action', 'ai.act_executed')->latest('id')->first();
        expect($execLog)->not->toBeNull()
            ->and($execLog->actor_name)->toBe('cmo')
            ->and($execLog->properties['function'])->toBe('spend_budget')
            ->and($execLog->properties['result'])->toBe(['spent' => 50_000]);

        // Owner can approve any amount regardless of manager limit.
        $approvedLarge = $service->approve($large, $owner, 100_000);
        expect($approvedLarge->status)->toBe('approved');

        // Reject works for owner/manager.
        $toReject = $service->requestApproval('cro', 'change_price', ['product_id' => 1], 20_000, $owner);
        $rejected = $service->reject($toReject, $owner);
        expect($rejected->status)->toBe('rejected');

        // ApprovalController returns 403 for staff and 200 for owner.
        $controller = app(ApprovalController::class);
        $forController = $service->requestApproval('cxo', 'issue_refund', ['order_id' => 1], 15_000, $owner);

        $staffReq = Request::create('/approvals/'.$forController->id.'/approve', 'POST');
        $staffReq->setUserResolver(fn () => $staff);
        expect($controller->approve($staffReq, $forController)->getStatusCode())->toBe(403);

        $ownerReq = Request::create('/approvals/'.$forController->id.'/approve', 'POST');
        $ownerReq->setUserResolver(fn () => $owner);
        expect($controller->approve($ownerReq, $forController)->getStatusCode())->toBe(200);

        $indexReq = Request::create('/approvals', 'GET', ['status' => 'approved']);
        $indexReq->setUserResolver(fn () => $owner);
        $indexData = $controller->index($indexReq)->getData(true);
        expect(count($indexData['data']))->toBeGreaterThanOrEqual(2);
    });
});

test('FR-PRO-01 FR-PRO-02 FR-PRO-03 FR-AI-09 hourly, daily, weekly, and event-triggered checks create grounded Insight cards', function () {
    [$owner, $store] = signedInMember();
    $currentStore = app(CurrentStore::class);
    $asOf = Carbon::create(2026, 11, 25, 14, 0, 0, 'Africa/Cairo');

    $currentStore->runAs($store, function () use ($asOf) {
        // 1. Low stock product
        Product::factory()->live()->create(['name' => 'Cairo Linen Shirt', 'price' => 80_000, 'stock' => 2]);

        // 2. Orders for 4 prior same-weekdays (100,000 piasters each) vs today (40,000 -> 40% < 70%)
        for ($w = 1; $w <= 4; $w++) {
            Order::query()->create([
                'number' => 2000 + $w,
                'status' => 'delivered',
                'total' => 100_000,
                'subtotal' => 100_000,
                'placed_at' => $asOf->copy()->subWeeks($w)->utc(),
            ]);
        }
        $todayOrder = Order::query()->create([
            'number' => 2010,
            'status' => 'pending',
            'total' => 40_000,
            'subtotal' => 40_000,
            'placed_at' => $asOf->copy()->utc(),
        ]);

        // 3. Refunds: 10,000/week average across prior 4 weeks (total 40,000) vs 35,000 in last 7 days (3.5x > 2x)
        for ($w = 1; $w <= 4; $w++) {
            $r = Refund::query()->create([
                'order_id' => $todayOrder->id,
                'amount' => 10_000,
            ]);
            $r->forceFill(['created_at' => $asOf->copy()->subDays(7 + $w * 6)->utc()])->save();
        }
        $recentRefund = Refund::query()->create([
            'order_id' => $todayOrder->id,
            'amount' => 35_000,
        ]);
        $recentRefund->forceFill(['created_at' => $asOf->copy()->subDays(2)->utc()])->save();
    });

    $hourly = app(HourlyChecks::class)->run($store, [
        ['type' => 'payment_failure', 'gateway' => 'paymob', 'failures' => 5],
        ['type' => 'flagged_phone', 'order_number' => '2010', 'refusals' => 3],
        ['type' => 'spike_404', 'path' => '/old-promo', 'count' => 42],
        ['type' => 'low_roas', 'campaign' => 'Winter Drop', 'roas' => 1.1, 'threshold' => 2.5, 'spend_piasters' => 50_000],
    ]);

    expect($hourly)->toHaveCount(5)
        ->and(collect($hourly)->pluck('type')->all())->toBe(['low_stock', 'payment_failure', 'flagged_phone', 'spike_404', 'low_roas']);

    $daily = app(DailyChecks::class)->run($store, $asOf);
    expect($daily)->toHaveCount(1)
        ->and($daily[0]->type)->toBe('revenue_drop')
        ->and($daily[0]->agent)->toBe('cro')
        ->and($daily[0]->evidence['ratio_pct'])->toEqual(40.0);

    $weekly = app(WeeklyChecks::class)->run($store, $asOf);
    expect($weekly)->toHaveCount(1)
        ->and($weekly[0]->type)->toBe('refund_spike')
        ->and($weekly[0]->agent)->toBe('cxo')
        ->and($weekly[0]->evidence['multiplier'])->toBe(3.5);
});

test('FR-PRO-04 FR-PRO-05 FR-PRO-06 FR-PRO-09 FeedRanker enforces daily cap and quiet hours with urgent bypass, InsightController acts, and MorningBrief compiles', function () {
    [$owner, $store] = signedInMember();
    $currentStore = app(CurrentStore::class);
    $today = Carbon::create(2026, 11, 25, 11, 0, 0, 'Africa/Cairo');

    $currentStore->runAs($store, function () use ($store, $owner, $today) {
        // Yesterday's orders for MorningBrief
        Order::query()->create([
            'number' => 3001,
            'status' => 'delivered',
            'total' => 75_000,
            'subtotal' => 75_000,
            'placed_at' => $today->copy()->subDay()->setTime(15, 0)->utc(),
        ]);

        // Pending approval for MorningBrief
        app(ApprovalService::class)->requestApproval('cmo', 'approve_campaign', ['id' => 1], 25_000, $owner);

        // 7 open insights (6 non-urgent + 1 urgent)
        for ($i = 1; $i <= 6; $i++) {
            Insight::query()->create([
                'store_id' => $store->id,
                'agent' => 'cro',
                'cadence' => 'daily',
                'type' => "signal_{$i}",
                'urgency' => 40 + $i * 5,
                'estimated_impact' => $i * 50_000,
                'title' => "Insight {$i}",
                'summary' => 'Summary',
                'evidence' => ['metric' => $i],
                'recommended_action' => $i === 6 ? ['function' => 'spend_budget', 'payload' => ['delta' => -10_000]] : null,
                'status' => 'open',
                'is_urgent' => false,
            ]);
        }

        Insight::query()->create([
            'store_id' => $store->id,
            'agent' => 'cto',
            'cadence' => 'event',
            'type' => 'payment_failure',
            'urgency' => 95,
            'estimated_impact' => 300_000,
            'title' => 'Gateway Down',
            'summary' => 'Immediate action required',
            'evidence' => ['failures' => 10],
            'status' => 'open',
            'is_urgent' => true,
        ]);
    });

    $ranker = app(FeedRanker::class);

    // Daytime: capped at 5, urgent first, sorted by score desc.
    $daytime = $ranker->rank($store, 5, $today);
    expect($daytime)->toHaveCount(5)
        ->and($daytime->first()->is_urgent)->toBeTrue()
        ->and($daytime->first()->type)->toBe('payment_failure');

    // Quiet hours (23:30 Cairo): only urgent insights break through.
    $quietNight = Carbon::create(2026, 11, 25, 23, 30, 0, 'Africa/Cairo');
    $nightFeed = $ranker->rank($store, 5, $quietNight);
    expect($nightFeed)->toHaveCount(1)
        ->and($nightFeed->first()->is_urgent)->toBeTrue();

    // MorningBrief (FR-PRO-06)
    $brief = app(MorningBrief::class)->compile($store, $today);
    expect($brief['yesterday']['orders_count'])->toBe(1)
        ->and($brief['yesterday']['sales_piasters'])->toBe(75_000)
        ->and($brief['pending_approvals_count'])->toBe(1)
        ->and(count($brief['priorities']))->toBe(5);

    // InsightController actions: approve, snooze, dismiss (FR-PRO-05)
    $currentStore->runAs($store, function () use ($owner) {
        $controller = app(InsightController::class);
        $actionable = Insight::query()->where('type', 'signal_6')->firstOrFail();
        $toSnooze = Insight::query()->where('type', 'signal_5')->firstOrFail();
        $toDismiss = Insight::query()->where('type', 'signal_4')->firstOrFail();

        $approveReq = Request::create('/insights/'.$actionable->id.'/act', 'POST', ['action' => 'approve']);
        $approveReq->setUserResolver(fn () => $owner);
        $approveResp = $controller->act($approveReq, $actionable)->getData(true);
        expect($approveResp['data']['status'])->toBe('approved')
            ->and($approveResp['approval']['function_name'])->toBe('spend_budget');

        $snoozeReq = Request::create('/insights/'.$toSnooze->id.'/act', 'POST', ['action' => 'snooze', 'snooze_hours' => 12]);
        $snoozeReq->setUserResolver(fn () => $owner);
        $snoozeResp = $controller->act($snoozeReq, $toSnooze)->getData(true);
        expect($snoozeResp['data']['status'])->toBe('snoozed')
            ->and($snoozeResp['data']['snoozed_until'])->not->toBeNull();

        $dismissReq = Request::create('/insights/'.$toDismiss->id.'/act', 'POST', ['action' => 'dismiss']);
        $dismissReq->setUserResolver(fn () => $owner);
        $dismissResp = $controller->act($dismissReq, $toDismiss)->getData(true);
        expect($dismissResp['data']['status'])->toBe('dismissed');

        $briefReq = Request::create('/insights/morning-brief', 'GET');
        $briefReq->setUserResolver(fn () => $owner);
        expect($controller->morningBrief($briefReq)->getStatusCode())->toBe(200);
    });
});

test('NFR-AIQ-01 V-09 EvalRunner achieves >=85% routing accuracy, 100% exact figures, and interpretation on all 50 questions', function () {
    $runner = new EvalRunner(new QuestionRouter, new NumberGuard);
    $report = $runner->run();

    expect($report['total'])->toBe(50)
        ->and($report['arabic_count'])->toBeGreaterThanOrEqual(20)
        ->and($report['held_out_count'])->toBe(20)
        ->and($report['routing_accuracy'])->toBeGreaterThanOrEqual(85.0)
        ->and($report['held_out_accuracy'])->toBeGreaterThanOrEqual(85.0)
        ->and($report['exact_figures_accuracy'])->toBe(100.0)
        ->and($report['interpretation_accuracy'])->toBe(100.0);
});
