<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Events\AdSpendRecorded;
use App\Modules\Marketing\Models\AdSpend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manual ad spend tracking endpoint (integer piasters).
 */
class AdSpendController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'ad_spends' => AdSpend::query()->latest('spend_date')->latest('id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['nullable', 'string', 'max:32'],
            'campaign_name' => ['required', 'string', 'max:160'],
            'spend_date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $adSpend = AdSpend::query()->create([
            'channel' => $data['channel'] ?? 'meta',
            'campaign_name' => trim($data['campaign_name']),
            'spend_date' => $data['spend_date'],
            'amount' => (int) $data['amount'],
        ]);

        AdSpendRecorded::dispatch($adSpend);

        return response()->json(['ad_spend' => $adSpend], 201);
    }
}
