<?php

namespace App\Modules\Builder\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Modules\Builder\Models\Theme;
use InvalidArgumentException;

/**
 * Store theme defaults, updates (colors, fonts, corner radius, light/dark mode), and CSS variables.
 * FR-BLD-03
 */
class ThemeService
{
    public const VALID_CORNER_RADII = ['none', 'sm', 'md', 'lg', 'full'];

    public const VALID_COLOR_MODES = ['light', 'dark'];

    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function current(): Theme
    {
        $theme = Theme::query()->first();

        if ($theme !== null) {
            return $theme;
        }

        return Theme::query()->create([]);
    }

    /**
     * Update the active store's theme attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): Theme
    {
        $theme = $this->current();

        $allowed = array_intersect_key($attributes, array_flip([
            'primary_color',
            'accent_color',
            'font_heading',
            'font_body',
            'corner_radius',
            'color_mode',
            'template',
        ]));

        foreach (['primary_color', 'accent_color'] as $colorKey) {
            if (array_key_exists($colorKey, $allowed)) {
                $value = trim((string) $allowed[$colorKey]);
                if (! preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
                    throw new InvalidArgumentException("Invalid hex color for {$colorKey}: {$value}");
                }
                $allowed[$colorKey] = strtolower($value);
            }
        }

        if (array_key_exists('corner_radius', $allowed) && ! in_array($allowed['corner_radius'], self::VALID_CORNER_RADII, true)) {
            throw new InvalidArgumentException("Invalid corner_radius: {$allowed['corner_radius']}");
        }

        if (array_key_exists('color_mode', $allowed) && ! in_array($allowed['color_mode'], self::VALID_COLOR_MODES, true)) {
            throw new InvalidArgumentException("Invalid color_mode: {$allowed['color_mode']}");
        }

        foreach (['font_heading', 'font_body'] as $fontKey) {
            if (array_key_exists($fontKey, $allowed)) {
                $font = trim((string) $allowed[$fontKey]);
                if ($font === '') {
                    throw new InvalidArgumentException("{$fontKey} cannot be empty.");
                }
                $allowed[$fontKey] = $font;
            }
        }

        $theme->fill($allowed);
        $theme->save();

        $this->activity->log('theme.updated', $theme, array_keys($allowed));

        return $theme->refresh();
    }
}
