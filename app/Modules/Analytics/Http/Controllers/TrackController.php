<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * First-party storefront page-view tracking endpoint with explicit consent enforcement.
 * FR-ANA-01, FR-ANA-06
 */
class TrackController extends Controller
{
    public function __construct(private readonly TrackingService $tracking) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'consent_granted' => ['required', 'boolean'],
            'session_key' => ['nullable', 'string', 'max:100'],
            'path' => ['required', 'string', 'max:500'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'referrer' => ['nullable', 'string', 'max:500'],
            'utm_source' => ['nullable', 'string', 'max:80'],
            'utm_medium' => ['nullable', 'string', 'max:80'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'device_type' => ['nullable', 'string', 'max:20'],
        ]);

        $pageView = $this->tracking->recordPageView($data, (bool) $data['consent_granted']);

        if ($pageView === null) {
            return response()->json(['recorded' => false], 200);
        }

        return response()->json([
            'recorded' => true,
            'page_view_id' => $pageView->id,
        ], 201);
    }
}
