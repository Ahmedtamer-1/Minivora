<?php

use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Fpa\Http\Controllers\ScenarioController;
use App\Modules\Fpa\Models\Scenario;
use App\Modules\Fpa\Services\FpaCommentaryService;
use App\Modules\Fpa\Services\VarianceService;
use App\Modules\Fpa\Services\WhatIfService;
use App\Modules\Ledger\Services\ChartOfAccounts as C;
use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Platform\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

function fpaStore(Store $store, Closure $fn): mixed
{
    return app(CurrentStore::class)->runAs($store, $fn);
}

test('FR-FPA-01 FR-FPA-02 monthly variance compares per-account budgets against actual ledger balances in integer piasters', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->withMember($owner)->create();

    $result = fpaStore($store, function () use ($store) {
        $chart = app(C::class);
        $sales = $chart->get($store->id, C::SALES);       // 4000 (Revenue)
        $marketing = $chart->get($store->id, '6000');     // Marketing & ads (Expense)
        $rent = $chart->get($store->id, '6100');          // Rent (Expense)

        $varianceService = app(VarianceService::class);
        $varianceService->save(Carbon::parse('2026-10-01'), [
            $sales->id => 1_000_000,      // 10,000.00 EGP planned revenue
            $marketing->id => 200_000,    // 2,000.00 EGP planned ad spend
            $rent->id => 300_000,         // 3,000.00 EGP planned rent
        ]);

        $ledger = app(LedgerService::class);
        // Sales actual: 12,000.00 EGP (+2,000.00 favorable, +20.0%)
        $ledger->post('2026-10-10', 'October sales', [
            ['code' => C::BANK, 'debit' => 1_200_000],
            ['code' => C::SALES, 'credit' => 1_200_000],
        ]);
        // Marketing actual: 2,500.00 EGP (+500.00 unfavorable, +25.0%)
        $ledger->post('2026-10-12', 'October ads', [
            ['code' => '6000', 'debit' => 250_000],
            ['code' => C::BANK, 'credit' => 250_000],
        ]);
        // Rent actual: 2,700.00 EGP (-300.00 favorable, -10.0%)
        $ledger->post('2026-10-15', 'October rent', [
            ['code' => '6100', 'debit' => 270_000],
            ['code' => C::BANK, 'credit' => 270_000],
        ]);
        // Entry in a different month must NOT leak into 2026-10
        $ledger->post('2026-11-02', 'November sales', [
            ['code' => C::BANK, 'debit' => 500_000],
            ['code' => C::SALES, 'credit' => 500_000],
        ]);

        return $varianceService->monthlyVariance('2026-10');
    });

    expect($result['month'])->toBe('2026-10')
        ->and($result['total_budget'])->toBe(1_500_000)
        ->and($result['total_actual'])->toBe(1_720_000)
        ->and($result['total_variance'])->toBe(220_000);

    $byCode = collect($result['rows'])->keyBy('account_code');

    expect($byCode->get(C::SALES))->toMatchArray([
        'account_code' => C::SALES,
        'budget' => 1_000_000,
        'actual' => 1_200_000,
        'variance' => 200_000,
        'variance_pct' => 20.0,
        'favorable' => true,
    ]);

    expect($byCode->get('6000'))->toMatchArray([
        'account_code' => '6000',
        'budget' => 200_000,
        'actual' => 250_000,
        'variance' => 50_000,
        'variance_pct' => 25.0,
        'favorable' => false,
    ]);

    expect($byCode->get('6100'))->toMatchArray([
        'account_code' => '6100',
        'budget' => 300_000,
        'actual' => 270_000,
        'variance' => -30_000,
        'variance_pct' => -10.0,
        'favorable' => true,
    ]);
});

test('FR-FPA-03 what-if scenario simulates price, discount, volume, ad spend, and shipping with exact piaster profit delta and saves scenario', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->withMember($owner)->create();

    $baseline = [
        'orders' => 100,
        'avg_order_value' => 50_000,     // 500.00 EGP
        'cogs_per_order' => 20_000,      // 200.00 EGP
        'shipping_per_order' => 5_000,   // 50.00 EGP
        'ad_spend' => 500_000,           // 5,000.00 EGP
        'fixed_expenses' => 1_000_000,   // 10,000.00 EGP
    ];

    $assumptions = [
        'price_change_pct' => 10,        // +10% price => 55,000
        'discount_pct' => 5,             // -5% discount => 52,250
        'volume_change_pct' => 20,       // +20% volume => 120 orders
        'ad_spend_change_pct' => 15,     // +15% ad spend => 575,000
        'shipping_change_pct' => 10,     // +10% shipping/order => 5,500
    ];

    $whatIf = app(WhatIfService::class);
    $sim = $whatIf->simulate($baseline, $assumptions);

    // Baseline: rev 5,000,000 - cogs 2,000,000 - shipping 500,000 - ads 500,000 - fixed 1,000,000 = 1,000,000
    expect($sim['baseline']['revenue'])->toBe(5_000_000)
        ->and($sim['baseline']['cogs'])->toBe(2_000_000)
        ->and($sim['baseline']['shipping'])->toBe(500_000)
        ->and($sim['baseline']['ad_spend'])->toBe(500_000)
        ->and($sim['baseline']['fixed_expenses'])->toBe(1_000_000)
        ->and($sim['baseline']['net_profit'])->toBe(1_000_000);

    // Projected: 120 orders * 52,250 = 6,270,000 rev
    // cogs = 120 * 20,000 = 2,400,000; shipping = 120 * 5,500 = 660,000; ads = 575,000; fixed = 1,000,000
    // net_profit = 6,270,000 - 2,400,000 - 660,000 - 575,000 - 1,000,000 = 1,635,000
    // profit_delta = 1,635,000 - 1,000,000 = +635,000
    expect($sim['projected']['orders'])->toBe(120)
        ->and($sim['projected']['avg_order_value'])->toBe(52_250)
        ->and($sim['projected']['revenue'])->toBe(6_270_000)
        ->and($sim['projected']['cogs'])->toBe(2_400_000)
        ->and($sim['projected']['shipping'])->toBe(660_000)
        ->and($sim['projected']['ad_spend'])->toBe(575_000)
        ->and($sim['projected']['fixed_expenses'])->toBe(1_000_000)
        ->and($sim['projected']['net_profit'])->toBe(1_635_000)
        ->and($sim['profit_delta'])->toBe(635_000);

    $scenario = fpaStore($store, fn () => $whatIf->saveScenario('Q4 Promo Push', $baseline, $assumptions, $owner));

    expect($scenario)->toBeInstanceOf(Scenario::class)
        ->and($scenario->store_id)->toBe($store->id)
        ->and($scenario->created_by)->toBe($owner->id)
        ->and($scenario->name)->toBe('Q4 Promo Push')
        ->and($scenario->projected['profit_delta'])->toBe(635_000);

    // Verify ScenarioController index, simulate, and store endpoints
    fpaStore($store, function () use ($baseline, $assumptions, $owner) {
        $controller = app(ScenarioController::class);

        $indexResp = $controller->index();
        expect($indexResp->getStatusCode())->toBe(200)
            ->and($indexResp->getData(true)['scenarios'])->toHaveCount(1);

        $simRequest = Request::create('/scenarios/simulate', 'POST', [
            'baseline' => $baseline,
            'assumptions' => $assumptions,
        ]);
        $simResp = $controller->simulate($simRequest);
        expect($simResp->getStatusCode())->toBe(200)
            ->and($simResp->getData(true)['simulation']['profit_delta'])->toBe(635_000)
            ->and($simResp->getData(true)['commentary']['source'])->toBe('ledger_and_fpa_code');

        $storeRequest = Request::create('/scenarios', 'POST', [
            'name' => 'Ramadan Sale',
            'baseline' => $baseline,
            'assumptions' => $assumptions,
        ]);
        $storeRequest->setUserResolver(fn () => $owner);
        $storeResp = $controller->store($storeRequest);
        expect($storeResp->getStatusCode())->toBe(201)
            ->and($storeResp->getData(true)['scenario']['name'])->toBe('Ramadan Sale');
    });
});

test('FR-FPA-05 FR-FPA-06 commentary accepts grounded draft and rejects hallucinated figures with deterministic fallback', function () {
    $commentary = app(FpaCommentaryService::class);

    $variance = [
        'month' => '2026-10',
        'rows' => [
            [
                'account_code' => '4000',
                'account_name' => 'Sales',
                'budget' => 1_000_000,
                'actual' => 1_200_000,
                'variance' => 200_000,
                'variance_pct' => 20.0,
                'favorable' => true,
            ],
        ],
        'total_budget' => 1_000_000,
        'total_actual' => 1_200_000,
        'total_variance' => 200_000,
    ];

    // Grounded draft uses only computed figures (2026-10, 12,000.00, 10,000.00, 2,000.00, 20.0%)
    $groundedVarianceDraft = 'In 2026-10, Sales reached 12,000.00 EGP against a budget of 10,000.00 EGP, delivering a favorable variance of 2,000.00 EGP (20.0%).';
    $accepted = $commentary->commentOnVariance($variance, $groundedVarianceDraft);

    expect($accepted)->toBe([
        'text' => $groundedVarianceDraft,
        'used_fallback' => false,
        'source' => 'ledger_and_fpa_code',
    ]);

    // Hallucinated draft invents 99,999.00 EGP and 47.5% -> rejected and replaced by deterministic fallback
    $hallucinatedVarianceDraft = 'Sales surged to 99,999.00 EGP, beating budget by 47.5%.';
    $rejected = $commentary->commentOnVariance($variance, $hallucinatedVarianceDraft);

    expect($rejected['used_fallback'])->toBeTrue()
        ->and($rejected['source'])->toBe('ledger_and_fpa_code')
        ->and($rejected['text'])->toContain('10,000.00 EGP')
        ->and($rejected['text'])->toContain('12,000.00 EGP')
        ->and($rejected['text'])->toContain('2,000.00 EGP')
        ->and($rejected['text'])->not->toContain('99,999.00');

    // Null draft also uses deterministic fallback
    $nullFallback = $commentary->commentOnVariance($variance, null);
    expect($nullFallback['used_fallback'])->toBeTrue()
        ->and($nullFallback['source'])->toBe('ledger_and_fpa_code');

    // Scenario commentary: grounded accepted, hallucinated rejected
    $simulation = app(WhatIfService::class)->simulate([
        'orders' => 100,
        'avg_order_value' => 50_000,
        'cogs_per_order' => 20_000,
        'shipping_per_order' => 5_000,
        'ad_spend' => 500_000,
        'fixed_expenses' => 1_000_000,
    ], [
        'price_change_pct' => 10,
    ]);

    // Baseline net profit: 10,000.00 EGP; Projected net profit: 15,000.00 EGP; Delta: +5,000.00 EGP
    $groundedScenarioDraft = 'Raising prices by 10% lifts net profit from 10,000.00 EGP to 15,000.00 EGP, a gain of 5,000.00 EGP.';
    $scenarioAccepted = $commentary->commentOnScenario($simulation, $groundedScenarioDraft);

    expect($scenarioAccepted)->toBe([
        'text' => $groundedScenarioDraft,
        'used_fallback' => false,
        'source' => 'ledger_and_fpa_code',
    ]);

    $hallucinatedScenarioDraft = 'Raising prices by 10% creates 88,888.00 EGP in extra profit.';
    $scenarioRejected = $commentary->commentOnScenario($simulation, $hallucinatedScenarioDraft);

    expect($scenarioRejected['used_fallback'])->toBeTrue()
        ->and($scenarioRejected['source'])->toBe('ledger_and_fpa_code')
        ->and($scenarioRejected['text'])->toContain('10,000.00 EGP')
        ->and($scenarioRejected['text'])->toContain('15,000.00 EGP')
        ->and($scenarioRejected['text'])->toContain('5,000.00 EGP')
        ->and($scenarioRejected['text'])->not->toContain('88,888.00');
});
