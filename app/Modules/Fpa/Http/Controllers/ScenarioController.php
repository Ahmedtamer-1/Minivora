<?php

namespace App\Modules\Fpa\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fpa\Models\Scenario;
use App\Modules\Fpa\Services\FpaCommentaryService;
use App\Modules\Fpa\Services\WhatIfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What-if scenario listing, simulation, and persistence endpoints. FR-FPA-03, FR-FPA-05, FR-FPA-06
 */
class ScenarioController extends Controller
{
    public function __construct(
        private readonly WhatIfService $whatIf,
        private readonly FpaCommentaryService $commentary,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'scenarios' => Scenario::query()->latest('id')->get(),
        ]);
    }

    public function simulate(Request $request): JsonResponse
    {
        $data = $request->validate($this->scenarioRules(requireName: false));

        $simulation = $this->whatIf->simulate(
            $data['baseline'],
            $data['assumptions'] ?? [],
        );

        return response()->json([
            'simulation' => $simulation,
            'commentary' => $this->commentary->commentOnScenario(
                $simulation,
                $data['llm_draft'] ?? null,
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->scenarioRules(requireName: true));

        $scenario = $this->whatIf->saveScenario(
            $data['name'],
            $data['baseline'],
            $data['assumptions'] ?? [],
            $request->user(),
        );

        return response()->json([
            'scenario' => $scenario,
        ], 201);
    }

    /** @return array<string, mixed> */
    private function scenarioRules(bool $requireName): array
    {
        $rules = [
            'baseline' => ['required', 'array'],
            'baseline.orders' => ['required', 'integer', 'min:0'],
            'baseline.avg_order_value' => ['required', 'integer', 'min:0'],
            'baseline.cogs_per_order' => ['required', 'integer', 'min:0'],
            'baseline.shipping_per_order' => ['required', 'integer', 'min:0'],
            'baseline.ad_spend' => ['required', 'integer', 'min:0'],
            'baseline.fixed_expenses' => ['required', 'integer', 'min:0'],
            'assumptions' => ['nullable', 'array'],
            'assumptions.price_change_pct' => ['nullable', 'numeric', 'between:-100,1000'],
            'assumptions.discount_pct' => ['nullable', 'numeric', 'between:0,100'],
            'assumptions.volume_change_pct' => ['nullable', 'numeric', 'between:-100,1000'],
            'assumptions.ad_spend_change_pct' => ['nullable', 'numeric', 'between:-100,1000'],
            'assumptions.shipping_change_pct' => ['nullable', 'numeric', 'between:-100,1000'],
            'llm_draft' => ['nullable', 'string', 'max:4000'],
        ];

        if ($requireName) {
            $rules['name'] = ['required', 'string', 'max:160'];
        }

        return $rules;
    }
}
