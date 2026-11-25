<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Services\PixelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard tracking pixel and CAPI configuration endpoints. FR-MKT-03
 */
class PixelController extends Controller
{
    public function __construct(private readonly PixelService $pixels) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'pixel' => $this->pixels->current(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'meta_pixel_id' => ['nullable', 'string', 'max:64'],
            'meta_capi_token' => ['nullable', 'string', 'max:1000'],
            'meta_ad_account_id' => ['nullable', 'string', 'max:64'],
            'tiktok_pixel_id' => ['nullable', 'string', 'max:64'],
            'snap_pixel_id' => ['nullable', 'string', 'max:64'],
            'ga4_measurement_id' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json([
            'pixel' => $this->pixels->update($data),
        ]);
    }
}
