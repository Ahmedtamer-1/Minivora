<?php

namespace App\Modules\Builder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Builder\Services\ThemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dashboard theme settings endpoints: read and update store colors, fonts, radius, color mode.
 * FR-BLD-03
 */
class ThemeController extends Controller
{
    public function __construct(
        private readonly ThemeService $themes,
    ) {}

    public function show(): JsonResponse
    {
        $theme = $this->themes->current();

        return response()->json([
            'theme' => $theme,
            'css_variables' => $theme->cssVariables(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'primary_color' => ['sometimes', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'accent_color' => ['sometimes', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'font_heading' => ['sometimes', 'string', 'max:80'],
            'font_body' => ['sometimes', 'string', 'max:80'],
            'corner_radius' => ['sometimes', 'string', Rule::in(ThemeService::VALID_CORNER_RADII)],
            'color_mode' => ['sometimes', 'string', Rule::in(ThemeService::VALID_COLOR_MODES)],
            'template' => ['sometimes', 'nullable', 'string', 'max:32'],
            'custom_css' => ['sometimes', 'nullable', 'string', 'max:60000'],
            'settings' => ['sometimes', 'array'],
        ]);

        $theme = $this->themes->update($data);

        return response()->json([
            'theme' => $theme,
            'css_variables' => $theme->cssVariables(),
        ]);
    }
}
