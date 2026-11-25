<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\DemandService;
use App\Modules\Analytics\Services\KpiService;
use App\Modules\Analytics\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Dashboard analytics overview: KPIs, product demand ranking, and recent visit sessions.
 * FR-ANA-02, FR-ANA-04
 */
class AnalyticsController extends Controller
{
    public function __construct(
        private readonly KpiService $kpis,
        private readonly DemandService $demand,
        private readonly TrackingService $tracking,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $from = $request->query('from')
            ? Carbon::parse((string) $request->query('from'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        $to = $request->query('to')
            ? Carbon::parse((string) $request->query('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $limit = max(1, min(100, (int) $request->query('limit', 20)));

        return response()->json([
            'kpis' => $this->kpis->summary($from, $to),
            'demand' => $this->demand->rankProducts($limit),
            'recent_sessions' => $this->tracking->recentSessions(20),
        ]);
    }
}
