<?php

use App\Core\Tenancy\CurrentStore;
use App\Modules\Analytics\AiFunctions\AnalyticsFunctions;
use App\Modules\Analytics\Http\Controllers\AnalyticsController;
use App\Modules\Analytics\Http\Controllers\TrackController;
use App\Modules\Analytics\Models\PageView;
use App\Modules\Analytics\Models\VisitSession;
use App\Modules\Analytics\Services\DemandService;
use App\Modules\Analytics\Services\KpiService;
use App\Modules\Analytics\Services\TrackingService;
use App\Modules\Cart\Models\CartEvent;
use App\Modules\Catalog\Models\Product;
use App\Modules\Checkout\Models\Payment;
use App\Modules\Marketing\AiFunctions\MarketingFunctions;
use App\Modules\Marketing\Events\AdSpendRecorded;
use App\Modules\Marketing\Http\Controllers\AdSpendController;
use App\Modules\Marketing\Http\Controllers\CampaignController;
use App\Modules\Marketing\Http\Controllers\PixelController;
use App\Modules\Marketing\Http\Controllers\SubscriberController;
use App\Modules\Marketing\Models\AbandonedCheckout;
use App\Modules\Marketing\Models\AdSpend;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\Pixel;
use App\Modules\Marketing\Services\AbandonedCartService;
use App\Modules\Marketing\Services\CampaignProposalService;
use App\Modules\Marketing\Services\CampaignVerdictService;
use App\Modules\Marketing\Services\ConversionsApiService;
use App\Modules\Marketing\Services\MetaImportService;
use App\Modules\Marketing\Services\NewsletterService;
use App\Modules\Marketing\Services\PixelService;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Platform\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

test('FR-ANA-01 FR-ANA-06 records page views and sessions only when consent is granted', function () {
    $store = Store::factory()->create();

    app(CurrentStore::class)->runAs($store, function (): void {
        $tracking = app(TrackingService::class);

        // Consent denied: returns null and writes nothing to DB.
        $denied = $tracking->recordPageView([
            'session_key' => 'sess-denied',
            'path' => '/ar/products/cotton-shirt',
            'utm_source' => 'meta',
            'utm_campaign' => 'summer_launch',
        ], false);

        expect($denied)->toBeNull()
            ->and(VisitSession::query()->count())->toBe(0)
            ->and(PageView::query()->count())->toBe(0);

        // Consent granted: creates session and page view.
        $view1 = $tracking->recordPageView([
            'session_key' => 'sess-001',
            'path' => '/ar/products/cotton-shirt',
            'product_id' => 42,
            'utm_source' => 'meta',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'summer_launch',
            'referrer' => 'https://l.instagram.com/',
            'device_type' => 'mobile',
        ], true);

        $view2 = $tracking->recordPageView([
            'session_key' => 'sess-001',
            'path' => '/ar/checkout',
            'device_type' => 'mobile',
        ], true);

        expect($view1)->not->toBeNull()
            ->and($view2)->not->toBeNull()
            ->and(VisitSession::query()->count())->toBe(1)
            ->and(PageView::query()->count())->toBe(2)
            ->and($view1->visit_session_id)->toBe($view2->visit_session_id);

        // Controller enforces consent_granted as well.
        $controller = app(TrackController::class);
        $blockedResp = $controller->store(Request::create('/track', 'POST', [
            'consent_granted' => false,
            'path' => '/ar',
        ]));
        expect($blockedResp->getStatusCode())->toBe(200)
            ->and($blockedResp->getData(true)['recorded'])->toBeFalse()
            ->and(PageView::query()->count())->toBe(2);
    });
});

test('FR-ANA-02 computes visitors, orders, revenue, conversion rate, AOV, and returning buyer rate', function () {
    $store = Store::factory()->create();

    app(CurrentStore::class)->runAs($store, function (): void {
        $tracking = app(TrackingService::class);
        $from = Carbon::parse('2026-11-01 00:00:00');
        $to = Carbon::parse('2026-11-30 23:59:59');

        // 4 distinct sessions in period.
        foreach (['s1', 's2', 's3', 's4'] as $key) {
            $tracking->recordPageView([
                'session_key' => $key,
                'path' => '/ar',
                'viewed_at' => '2026-11-10 12:00:00',
            ], true);
        }

        // Prior order before period for phone 01000000001 (makes them a returning buyer).
        Order::query()->create([
            'number' => 1001,
            'status' => OrderStatus::Delivered,
            'customer_name' => 'Salma',
            'phone' => '01000000001',
            'subtotal' => 20_000,
            'total' => 20_000,
            'placed_at' => Carbon::parse('2026-10-15 10:00:00'),
        ]);

        // Two sale orders in period: Salma (returning) and Omar (new).
        Order::query()->create([
            'number' => 1002,
            'status' => OrderStatus::Paid,
            'customer_name' => 'Salma',
            'phone' => '01000000001',
            'subtotal' => 40_000,
            'total' => 40_000,
            'placed_at' => Carbon::parse('2026-11-12 10:00:00'),
        ]);
        Order::query()->create([
            'number' => 1003,
            'status' => OrderStatus::Processing,
            'customer_name' => 'Omar',
            'phone' => '01000000002',
            'subtotal' => 60_000,
            'total' => 60_000,
            'placed_at' => Carbon::parse('2026-11-15 10:00:00'),
        ]);

        // Cancelled order must not count as a sale.
        Order::query()->create([
            'number' => 1004,
            'status' => OrderStatus::Cancelled,
            'customer_name' => 'Cancelled',
            'phone' => '01000000003',
            'subtotal' => 99_000,
            'total' => 99_000,
            'placed_at' => Carbon::parse('2026-11-16 10:00:00'),
        ]);

        $summary = app(KpiService::class)->summary($from, $to);

        expect($summary['visitors'])->toBe(4)
            ->and($summary['orders'])->toBe(2)
            ->and($summary['revenue'])->toBe(100_000)
            ->and($summary['conversion_rate'])->toBe(50.0)
            ->and($summary['aov'])->toBe(50_000)
            ->and($summary['returning_buyer_rate'])->toBe(50.0);

        $controllerResp = app(AnalyticsController::class)->index(Request::create('/analytics', 'GET', [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
        ]));
        expect($controllerResp->getData(true)['kpis']['revenue'])->toBe(100_000);
    });
});

test('FR-ANA-04 ranks products by views, cart adds, and units sold and flags sold_out_wanted', function () {
    $store = Store::factory()->create();

    app(CurrentStore::class)->runAs($store, function (): void {
        $bestSeller = Product::factory()->create(['name' => 'Cairo Linen Shirt', 'stock' => 15, 'price' => 45_000]);
        $soldOutHot = Product::factory()->create(['name' => 'Nile Leather Bag', 'stock' => 0, 'price' => 80_000]);
        $soldOutCold = Product::factory()->create(['name' => 'Old Scarf', 'stock' => 0, 'price' => 15_000]);

        $tracking = app(TrackingService::class);
        $tracking->recordPageView(['path' => '/p/1', 'product_id' => $bestSeller->id], true);
        $tracking->recordPageView(['path' => '/p/2', 'product_id' => $soldOutHot->id], true);
        $tracking->recordPageView(['path' => '/p/2', 'product_id' => $soldOutHot->id], true);

        CartEvent::query()->create([
            'product_id' => $soldOutHot->id,
            'event_type' => CartEvent::TYPE_ADD,
            'delta' => 1,
            'quantity_after' => 1,
            'unit_price' => 80_000,
        ]);

        $order = Order::query()->create([
            'number' => 1001,
            'status' => OrderStatus::Delivered,
            'customer_name' => 'Hana',
            'subtotal' => 135_000,
            'total' => 135_000,
            'placed_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $bestSeller->id,
            'name' => $bestSeller->name,
            'unit_price' => 45_000,
            'unit_cost' => 20_000,
            'quantity' => 3,
            'line_total' => 135_000,
        ]);

        $ranked = app(DemandService::class)->rankProducts(10);

        expect($ranked)->toHaveCount(3)
            ->and($ranked[0]['product_id'])->toBe($bestSeller->id)
            ->and($ranked[0]['units_sold'])->toBe(3)
            ->and($ranked[0]['sold_out_wanted'])->toBeFalse()
            ->and($ranked[1]['product_id'])->toBe($soldOutHot->id)
            ->and($ranked[1]['views'])->toBe(2)
            ->and($ranked[1]['cart_adds'])->toBe(1)
            ->and($ranked[1]['sold_out_wanted'])->toBeTrue()
            ->and($ranked[2]['product_id'])->toBe($soldOutCold->id)
            ->and($ranked[2]['sold_out_wanted'])->toBeFalse();
    });
});

test('FR-MKT-01 lists open abandoned checkouts and generates localized WhatsApp recovery URLs', function () {
    $store = Store::factory()->create();

    app(CurrentStore::class)->runAs($store, function (): void {
        $open = AbandonedCheckout::query()->create([
            'customer_name' => 'Youssef',
            'phone' => '01012345678',
            'items' => [['name' => 'Linen Shirt', 'qty' => 1, 'price' => 45_000]],
            'subtotal' => 45_000,
        ]);

        AbandonedCheckout::query()->create([
            'customer_name' => 'Recovered Customer',
            'phone' => '01099999999',
            'items' => [['name' => 'Bag', 'qty' => 1, 'price' => 30_000]],
            'subtotal' => 30_000,
            'recovered_order_id' => 99,
        ]);

        $service = app(AbandonedCartService::class);
        $list = $service->listOpen();

        expect($list)->toHaveCount(1)
            ->and($list->first()->id)->toBe($open->id);

        $arUrl = $service->whatsappRecoveryUrl($open, 'ar');
        $enUrl = $service->whatsappRecoveryUrl($open, 'en');

        expect($arUrl)->toStartWith('https://wa.me/201012345678?text=')
            ->and(urldecode($arUrl))->toContain('Youssef')
            ->and(urldecode($arUrl))->toContain('450.00')
            ->and($enUrl)->toStartWith('https://wa.me/201012345678?text=')
            ->and(urldecode($enUrl))->toContain('450.00 EGP');
    });
});

test('FR-MKT-03 manages per-store pixel IDs via PixelService and PixelController', function () {
    $store = Store::factory()->create();

    app(CurrentStore::class)->runAs($store, function (): void {
        $service = app(PixelService::class);
        $pixel = $service->update([
            'meta_pixel_id' => '1234567890',
            'meta_capi_token' => 'secret-capi-token',
            'tiktok_pixel_id' => 'TT-9988',
            'snap_pixel_id' => 'SNAP-77',
            'ga4_measurement_id' => 'G-MINIVORA1',
        ]);

        expect($pixel->meta_pixel_id)->toBe('1234567890')
            ->and($pixel->tiktok_pixel_id)->toBe('TT-9988')
            ->and($pixel->snap_pixel_id)->toBe('SNAP-77')
            ->and($pixel->ga4_measurement_id)->toBe('G-MINIVORA1')
            ->and($pixel->toArray())->not->toHaveKey('meta_capi_token');

        $resp = app(PixelController::class)->show();
        expect($resp->getData(true)['pixel']['meta_pixel_id'])->toBe('1234567890');
    });
});

test('FR-MKT-04 builds hashed CAPI event payload with deterministic browser/server deduplication key', function () {
    $capi = app(ConversionsApiService::class);

    $eventId1 = $capi->deduplicationKey('Purchase', 1055);
    $eventId2 = $capi->deduplicationKey('purchase', 1055);

    expect($eventId1)->toBe($eventId2)
        ->and($eventId1)->toBe('purchase:order:1055');

    $payload = $capi->buildEventPayload('Purchase', $eventId1, [
        'email' => '  Mona@Example.com ',
        'phone' => '+20 101-234-5678',
        'value_piasters' => 75_000,
        'order_id' => 1055,
        'content_ids' => ['10', '11'],
        'num_items' => 2,
        'event_source_url' => 'https://nile.minivora.test/ar/checkout',
    ]);

    expect($payload['event_name'])->toBe('Purchase')
        ->and($payload['event_id'])->toBe('purchase:order:1055')
        ->and($payload['user_data']['em'])->toBe([hash('sha256', 'mona@example.com')])
        ->and($payload['user_data']['ph'])->toBe([hash('sha256', '201012345678')])
        ->and($payload['custom_data']['currency'])->toBe('EGP')
        ->and($payload['custom_data']['value'])->toBe(750.0)
        ->and($payload['custom_data']['num_items'])->toBe(2);
});

test('FR-MKT-05 subscribes newsletter emails idempotently and exports UTF-8 BOM CSV', function () {
    $store = Store::factory()->create();

    app(CurrentStore::class)->runAs($store, function (): void {
        $newsletter = app(NewsletterService::class);
        $newsletter->subscribe('Nour@Example.com', 'نور أحمد');
        $newsletter->subscribe('nour@example.com', 'نور أحمد');

        $csv = $newsletter->exportCsv();

        expect(str_starts_with($csv, NewsletterService::UTF8_BOM))->toBeTrue()
            ->and($csv)->toContain('email,name,source,subscribed_at')
            ->and($csv)->toContain('nour@example.com')
            ->and($csv)->toContain('نور أحمد');

        $exportResp = app(SubscriberController::class)->export();
        expect($exportResp->getStatusCode())->toBe(200)
            ->and($exportResp->headers->get('Content-Type'))->toContain('text/csv');
    });
});

test('FR-MI-02 FR-MI-03 connects read-only Meta ad account and imports daily campaign metrics', function () {
    $store = Store::factory()->create();

    app(CurrentStore::class)->runAs($store, function (): void {
        $importer = app(MetaImportService::class);
        expect(MetaImportService::REQUIRED_SCOPE)->toBe('ads_read');

        $pixel = $importer->connectAdAccount('987654321');
        expect($pixel)->toBeInstanceOf(Pixel::class)
            ->and($pixel->meta_ad_account_id)->toBe('act_987654321');

        $campaign = $importer->importDailyMetrics('meta_cmp_1', 'Summer Linen Promo', 'summer_linen', [
            [
                'metric_date' => '2026-11-20',
                'spend' => 25_000,
                'impressions' => 5000,
                'reach' => 4000,
                'clicks' => 120,
                'meta_purchases' => 4,
                'meta_purchase_value' => 120_000,
            ],
            [
                'metric_date' => '2026-11-21',
                'spend' => 15_000,
                'impressions' => 3000,
                'reach' => 2500,
                'clicks' => 80,
                'meta_purchases' => 2,
                'meta_purchase_value' => 60_000,
            ],
        ]);

        expect($campaign)->toBeInstanceOf(Campaign::class)
            ->and($campaign->metrics)->toHaveCount(2)
            ->and((int) $campaign->metrics->sum('spend'))->toBe(40_000);
    });
});

test('FR-MI-04 FR-MI-05 FR-MI-06 computes store-verified profit/ROAS and enforces ordered Pause, Fix, Scale, Keep verdicts', function () {
    $store = Store::factory()->create();

    app(CurrentStore::class)->runAs($store, function (): void {
        $importer = app(MetaImportService::class);
        $verdicts = app(CampaignVerdictService::class);

        // 1. Pause: spend > 50,000 piasters (500 EGP) and 0 store-verified orders (even if CTR is low, Pause wins first).
        $pauseCampaign = $importer->importDailyMetrics('cmp_pause', 'Wasted Spend', 'utm_pause', [
            ['metric_date' => '2026-11-20', 'spend' => 60_000, 'impressions' => 5000, 'reach' => 4000, 'clicks' => 10],
        ]);

        // 2. Fix: CTR < 1.0% over >= 1000 impressions (has an order so Pause does not trigger).
        $fixCampaign = $importer->importDailyMetrics('cmp_fix', 'Low CTR Creative', 'utm_fix', [
            ['metric_date' => '2026-11-20', 'spend' => 20_000, 'impressions' => 2000, 'reach' => 1800, 'clicks' => 10],
        ]);
        $fixOrder = Order::query()->create([
            'number' => 2001,
            'status' => OrderStatus::Paid,
            'customer_name' => 'Fix Buyer',
            'subtotal' => 40_000,
            'shipping_fee' => 5_000,
            'total' => 45_000,
            'tags' => ['utm:utm_fix'],
            'placed_at' => now(),
        ]);
        $fixOrder->items()->create([
            'name' => 'Shirt',
            'unit_price' => 40_000,
            'unit_cost' => 15_000,
            'quantity' => 1,
            'line_total' => 40_000,
        ]);

        // 3. Scale: profit > 0 AND roas >= 2.5 AND frequency < 3.0.
        $scaleCampaign = $importer->importDailyMetrics('cmp_scale', 'Winning Campaign', 'utm_scale', [
            ['metric_date' => '2026-11-20', 'spend' => 40_000, 'impressions' => 4000, 'reach' => 2500, 'clicks' => 120, 'frequency' => 1.6],
        ]);
        VisitSession::query()->create([
            'session_key' => 'scale-sess-1',
            'utm_campaign' => 'utm_scale',
            'started_at' => now(),
        ]);
        $scaleOrder = Order::query()->create([
            'number' => 2002,
            'status' => OrderStatus::Delivered,
            'customer_name' => 'Scale Buyer',
            'subtotal' => 150_000,
            'shipping_fee' => 5_000,
            'total' => 155_000,
            'tags' => ['utm:utm_scale'],
            'placed_at' => now(),
        ]);
        $scaleOrder->items()->create([
            'name' => 'Jacket',
            'unit_price' => 150_000,
            'unit_cost' => 50_000,
            'quantity' => 1,
            'line_total' => 150_000,
        ]);
        Payment::query()->create([
            'order_id' => $scaleOrder->id,
            'gateway' => 'paymob',
            'status' => Payment::STATUS_PAID,
            'amount' => 155_000,
            'gateway_fee' => 3_000,
            'cod_fee' => 0,
        ]);

        // 4. Keep: profitable & good CTR, but frequency >= 3.0 so it does not qualify for Scale.
        $keepCampaign = $importer->importDailyMetrics('cmp_keep', 'Steady Campaign', 'utm_keep', [
            ['metric_date' => '2026-11-20', 'spend' => 30_000, 'impressions' => 3000, 'reach' => 800, 'clicks' => 90, 'frequency' => 3.75],
        ]);
        $keepOrder = Order::query()->create([
            'number' => 2003,
            'status' => OrderStatus::Delivered,
            'customer_name' => 'Keep Buyer',
            'subtotal' => 100_000,
            'shipping_fee' => 5_000,
            'total' => 105_000,
            'tags' => ['utm_keep'],
            'placed_at' => now(),
        ]);
        $keepOrder->items()->create([
            'name' => 'Shoes',
            'unit_price' => 100_000,
            'unit_cost' => 40_000,
            'quantity' => 1,
            'line_total' => 100_000,
        ]);

        $pauseEval = $verdicts->evaluate($pauseCampaign);
        $fixEval = $verdicts->evaluate($fixCampaign);
        $scaleEval = $verdicts->evaluate($scaleCampaign);
        $keepEval = $verdicts->evaluate($keepCampaign);

        expect($pauseEval['verdict'])->toBe('Pause')
            ->and($pauseEval['rule'])->toBe('spend_without_orders')
            ->and($fixEval['verdict'])->toBe('Fix')
            ->and($fixEval['rule'])->toBe('low_ctr')
            ->and($scaleEval['verdict'])->toBe('Scale')
            ->and($scaleEval['rule'])->toBe('profitable_high_roas')
            ->and($scaleEval['sessions'])->toBe(1)
            ->and($scaleEval['store_verified_orders'])->toBe(1)
            ->and($scaleEval['revenue'])->toBe(155_000)
            ->and($scaleEval['product_cost'])->toBe(50_000)
            ->and($scaleEval['shipping'])->toBe(5_000)
            ->and($scaleEval['fees'])->toBe(3_000)
            ->and($scaleEval['profit'])->toBe(57_000)
            ->and($scaleEval['roas'])->toBe(3.88)
            ->and($scaleEval['cpo'])->toBe(40_000)
            ->and($scaleEval['ctr'])->toBe(3.0)
            ->and($keepEval['verdict'])->toBe('Keep')
            ->and($keepEval['rule'])->toBe('maintain');

        // Ranking puts highest profit campaign first.
        $ranked = $verdicts->rankCampaigns();
        expect($ranked[0]['campaign_id'])->toBe($scaleCampaign->id)
            ->and($ranked[3]['campaign_id'])->toBe($pauseCampaign->id);
    });
});

test('FR-MI-07 FR-MI-08 builds code-computed campaign proposal awaiting owner approval', function () {
    $store = Store::factory()->create(['subdomain' => 'nile-craft']);

    app(CurrentStore::class)->runAs($store, function (): void {
        $product = Product::factory()->create([
            'name' => 'Handwoven Tote',
            'slug' => 'handwoven-tote',
            'price' => 100_000,
            'cost' => 40_000,
        ]);

        $proposal = app(CampaignProposalService::class)->propose(
            $product,
            1_000_000, // 10,000 EGP monthly marketing budget
            [
                'headline' => 'Complete your look with our Handwoven Tote',
                'primary_text' => 'Limited Egyptian craft stock — order today.',
            ],
            'cart_abandoners',
        );

        expect($proposal->is_proposal)->toBeTrue()
            ->and($proposal->status)->toBe('pending_approval')
            ->and($proposal->approved_at)->toBeNull()
            ->and($proposal->proposal_payload['total_budget'])->toBe(250_000)
            ->and($proposal->proposal_payload['duration_days'])->toBe(14)
            ->and($proposal->proposal_payload['daily_budget'])->toBe((int) floor(250_000 / 14))
            ->and($proposal->proposal_payload['unit_margin'])->toBe(60_000)
            ->and($proposal->proposal_payload['utm_url'])->toContain('utm_source=meta')
            ->and($proposal->proposal_payload['utm_url'])->toContain('utm_campaign='.$proposal->utm_campaign);
    });
});

test('FR-MI-01 read-only AnalyticsFunctions, MarketingFunctions, and AdSpendController work end-to-end', function () {
    Event::fake([AdSpendRecorded::class]);
    $store = Store::factory()->create();

    app(CurrentStore::class)->runAs($store, function (): void {
        $adSpendResp = app(AdSpendController::class)->store(Request::create('/ad-spends', 'POST', [
            'channel' => 'meta',
            'campaign_name' => 'November Retargeting',
            'spend_date' => '2026-11-22',
            'amount' => 35_000,
        ]));

        expect($adSpendResp->getStatusCode())->toBe(201)
            ->and(AdSpend::query()->count())->toBe(1);
        Event::assertDispatched(AdSpendRecorded::class);

        $analyticsFn = app(AnalyticsFunctions::class);
        $kpiResult = $analyticsFn->kpis('2026-11-01', '2026-11-30');
        $demandResult = $analyticsFn->demand(5);

        expect($kpiResult['source'])->toBe('analytics')
            ->and($demandResult['source'])->toBe('analytics');

        $marketingFn = app(MarketingFunctions::class);
        $spendSummary = $marketingFn->adSpendSummary('2026-11-01', '2026-11-30');
        $perfSummary = $marketingFn->campaignPerformance();
        $abandonedSummary = $marketingFn->abandonedCarts();

        expect($spendSummary['total_spend'])->toBe(35_000)
            ->and($spendSummary['by_channel']['meta'])->toBe(35_000)
            ->and($perfSummary['source'])->toBe('marketing')
            ->and($abandonedSummary['source'])->toBe('marketing');

        $campaignsResp = app(CampaignController::class)->index(Request::create('/marketing/campaigns', 'GET'));
        expect($campaignsResp->getStatusCode())->toBe(200);
    });
});
