<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\ShippingZone;
use App\Modules\Orders\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dashboard: manage shipping zones (governorate rate, delivery days, display order). FR-ORD-06
 */
class ShippingZoneController extends Controller
{
    public function __construct(private readonly ShippingService $shipping) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'zones' => $this->shipping->listZones()->values(),
            'governorates' => ShippingService::GOVERNORATES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'governorate' => ['required', 'string', 'max:40', Rule::in(ShippingService::GOVERNORATES)],
            'rate' => ['required', 'integer', 'min:0'],
            'delivery_days' => ['required', 'integer', 'min:1', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $zone = $this->shipping->upsertZone(
            $data['governorate'],
            (int) $data['rate'],
            (int) $data['delivery_days'],
            (int) ($data['sort_order'] ?? 0),
        );

        return response()->json(['zone' => $zone], 201);
    }

    public function update(Request $request, int $zone): JsonResponse
    {
        $model = ShippingZone::query()->findOrFail($zone);

        $data = $request->validate([
            'governorate' => ['sometimes', 'string', 'max:40', Rule::in(ShippingService::GOVERNORATES)],
            'rate' => ['sometimes', 'integer', 'min:0'],
            'delivery_days' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);

        $model->fill($data)->save();

        return response()->json(['zone' => $model]);
    }

    public function destroy(int $zone): JsonResponse
    {
        $model = ShippingZone::query()->findOrFail($zone);
        $this->shipping->deleteZone($model);

        return response()->json(['deleted' => true]);
    }
}
