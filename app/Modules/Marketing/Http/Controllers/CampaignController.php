<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Marketing\Models\AbandonedCheckout;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Services\AbandonedCartService;
use App\Modules\Marketing\Services\CampaignProposalService;
use App\Modules\Marketing\Services\CampaignVerdictService;
use App\Modules\Marketing\Services\MetaImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marketing Intelligence campaign listing, Meta import, proposals, and abandoned carts.
 * FR-MKT-01, FR-MI-02..08
 */
class CampaignController extends Controller
{
    public function __construct(
        private readonly MetaImportService $metaImport,
        private readonly CampaignVerdictService $verdicts,
        private readonly CampaignProposalService $proposals,
        private readonly AbandonedCartService $abandonedCarts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $options = [];
        if ($request->filled('pause_spend_limit')) {
            $options['pause_spend_limit'] = (int) $request->query('pause_spend_limit');
        }
        if ($request->filled('target_roas')) {
            $options['target_roas'] = (float) $request->query('target_roas');
        }

        return response()->json([
            'campaigns' => $this->verdicts->rankCampaigns($options),
            'proposals' => Campaign::query()->where('is_proposal', true)->latest('id')->get(),
        ]);
    }

    public function connectMeta(Request $request): JsonResponse
    {
        $data = $request->validate([
            'meta_ad_account_id' => ['required', 'string', 'max:64'],
        ]);

        $pixel = $this->metaImport->connectAdAccount($data['meta_ad_account_id']);

        return response()->json([
            'pixel' => $pixel,
            'scope' => MetaImportService::REQUIRED_SCOPE,
        ]);
    }

    public function importMetrics(Request $request): JsonResponse
    {
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:200'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.metric_date' => ['required', 'date'],
            'rows.*.spend' => ['required', 'integer', 'min:0'],
            'rows.*.impressions' => ['required', 'integer', 'min:0'],
            'rows.*.reach' => ['nullable', 'integer', 'min:0'],
            'rows.*.clicks' => ['required', 'integer', 'min:0'],
            'rows.*.frequency' => ['nullable', 'numeric', 'min:0'],
            'rows.*.meta_purchases' => ['nullable', 'integer', 'min:0'],
            'rows.*.meta_purchase_value' => ['nullable', 'integer', 'min:0'],
        ]);

        $campaign = $this->metaImport->importDailyMetrics(
            $data['external_id'],
            $data['name'],
            $data['utm_campaign'] ?? null,
            $data['rows'],
        );

        return response()->json([
            'campaign' => $campaign,
            'evaluation' => $this->verdicts->evaluate($campaign),
        ], 201);
    }

    public function propose(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'monthly_marketing_budget' => ['required', 'integer', 'min:0'],
            'audience' => ['nullable', 'string', 'max:64'],
            'copy' => ['required', 'array'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);
        $proposal = $this->proposals->propose(
            $product,
            (int) $data['monthly_marketing_budget'],
            $data['copy'],
            (string) ($data['audience'] ?? 'cart_abandoners'),
        );

        return response()->json(['proposal' => $proposal], 201);
    }

    public function abandoned(Request $request): JsonResponse
    {
        $locale = $request->query('locale', 'ar') === 'en' ? 'en' : 'ar';

        $items = $this->abandonedCarts->listOpen()->map(
            fn (AbandonedCheckout $checkout): array => [
                'id' => $checkout->id,
                'customer_name' => $checkout->customer_name,
                'phone' => $checkout->phone,
                'subtotal' => $checkout->subtotal,
                'items' => $checkout->items,
                'reminded_at' => $checkout->reminded_at?->toIso8601String(),
                'whatsapp_url' => $this->abandonedCarts->whatsappRecoveryUrl($checkout, $locale),
            ]
        )->values();

        return response()->json(['abandoned_checkouts' => $items]);
    }
}
