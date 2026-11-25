<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Ai\Models\Approval;
use App\Ai\Proactive\FeedRanker;
use App\Ai\Proactive\MorningBrief;
use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\DemandService;
use App\Modules\Analytics\Services\KpiService;
use App\Modules\Checkout\Models\DiscountCode;
use App\Modules\Checkout\Models\Lead;
use App\Modules\Marketing\Models\AbandonedCheckout;
use App\Modules\Marketing\Models\AdSpend;
use App\Modules\Marketing\Models\Subscriber;
use App\Modules\Marketing\Services\AbandonedCartService;
use App\Modules\Marketing\Services\CampaignVerdictService;
use App\Modules\Marketing\Services\PixelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inertia page controllers for Analytics, Marketing (Pixels, Campaigns, Discounts, Abandoned),
 * and AI Approvals / Proactive Insights.
 */
class GrowthAiPagesController extends Controller
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly KpiService $kpis,
        private readonly DemandService $demand,
        private readonly PixelService $pixels,
        private readonly CampaignVerdictService $verdicts,
        private readonly AbandonedCartService $abandonedCarts,
        private readonly FeedRanker $feedRanker,
        private readonly MorningBrief $morningBrief,
    ) {}

    public function analytics(Request $request): Response
    {
        $days = (int) $request->query('days', '30');
        if (! in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        return Inertia::render('analytics/index', [
            'days' => $days,
            'kpis' => $this->kpis->summary(now()->subDays($days), now()),
            'demand' => $this->demand->rankProducts(20),
        ]);
    }

    public function marketing(): Response
    {
        $pixel = $this->pixels->current();

        return Inertia::render('marketing/index', [
            'pixel' => [
                'id' => $pixel->id,
                'meta_pixel_id' => $pixel->meta_pixel_id,
                'has_meta_capi_token' => $pixel->meta_capi_token !== null && $pixel->meta_capi_token !== '',
                'meta_ad_account_id' => $pixel->meta_ad_account_id,
                'tiktok_pixel_id' => $pixel->tiktok_pixel_id,
                'snap_pixel_id' => $pixel->snap_pixel_id,
                'ga4_measurement_id' => $pixel->ga4_measurement_id,
            ],
            'subscribers' => Subscriber::query()->latest()->limit(50)->get(),
        ]);
    }

    public function campaigns(): Response
    {
        return Inertia::render('marketing/campaigns', [
            'campaigns' => $this->verdicts->rankCampaigns(),
            'adSpends' => AdSpend::query()->latest('spend_date')->latest('id')->limit(30)->get(),
        ]);
    }

    public function discounts(): Response
    {
        return Inertia::render('marketing/discounts', [
            'discounts' => DiscountCode::query()->latest()->get(),
        ]);
    }

    public function storeDiscount(Request $request): RedirectResponse
    {
        $store = $this->currentStore->getOrFail();

        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
        ]);

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('discount_codes', 'code')->where('store_id', $store->id),
            ],
            'type' => ['required', Rule::in([
                DiscountCode::TYPE_PERCENTAGE,
                DiscountCode::TYPE_FIXED,
                DiscountCode::TYPE_FREE_SHIPPING,
            ])],
            'value' => ['nullable', 'integer', 'min:0'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $type = (string) $data['type'];
        $value = $type === DiscountCode::TYPE_FREE_SHIPPING
            ? 0
            : (int) ($data['value'] ?? 0);

        if ($type === DiscountCode::TYPE_PERCENTAGE && $value > 100) {
            return back()->withErrors(['value' => 'Percentage discount cannot exceed 100%.']);
        }

        DiscountCode::query()->create([
            'code' => $data['code'],
            'type' => $type,
            'value' => $value,
            'min_order_amount' => (int) ($data['min_order_amount'] ?? 0),
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Discount code created.');
    }

    public function destroyDiscount(DiscountCode $discount): RedirectResponse
    {
        $discount->delete();

        return back()->with('success', 'Discount code deleted.');
    }

    public function abandoned(): Response
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        $abandoned = $this->abandonedCarts->listOpen()->map(function (AbandonedCheckout $checkout) use ($locale): array {
            return [
                'id' => $checkout->id,
                'lead_id' => $checkout->lead_id,
                'customer_name' => $checkout->customer_name,
                'phone' => $checkout->phone,
                'items' => $checkout->items ?? [],
                'subtotal' => $checkout->subtotal,
                'reminded_at' => $checkout->reminded_at?->toISOString(),
                'created_at' => $checkout->created_at?->toISOString(),
                'whatsapp_url' => $this->abandonedCarts->whatsappRecoveryUrl($checkout, $locale),
            ];
        })->values()->all();

        $leads = Lead::query()
            ->whereNull('converted_order_id')
            ->latest()
            ->limit(30)
            ->get()
            ->map(function (Lead $lead) use ($locale): array {
                $digits = preg_replace('/\D+/', '', $lead->phone) ?? '';
                if (str_starts_with($digits, '00')) {
                    $digits = substr($digits, 2);
                } elseif (strlen($digits) === 11 && str_starts_with($digits, '01')) {
                    $digits = '2'.$digits;
                }
                $name = trim((string) ($lead->name ?? ''));
                $message = $locale === 'ar'
                    ? ($name !== '' ? "أهلاً {$name}،" : 'أهلاً بك،').' لاحظنا أنك لم تكمل طلبك بعد. يسعدنا مساعدتك الآن!'
                    : ($name !== '' ? "Hi {$name}," : 'Hi,')." we noticed you didn't finish your checkout. Reply here if we can help!";

                return [
                    'id' => $lead->id,
                    'phone' => $lead->phone,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'governorate' => $lead->governorate,
                    'cart_snapshot' => $lead->cart_snapshot,
                    'captured_at' => $lead->captured_at?->toISOString(),
                    'created_at' => $lead->created_at?->toISOString(),
                    'whatsapp_url' => 'https://wa.me/'.$digits.'?text='.rawurlencode($message),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('marketing/abandoned', [
            'abandoned' => $abandoned,
            'leads' => $leads,
        ]);
    }

    public function approvals(): Response
    {
        $store = $this->currentStore->getOrFail();

        return Inertia::render('approvals/index', [
            'approvals' => Approval::query()->latest()->limit(30)->get(),
            'insights' => $this->feedRanker->rank($store, 20)->values(),
            'brief' => $this->morningBrief->compile($store),
        ]);
    }
}
