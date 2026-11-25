<?php

namespace App\Modules\Builder\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Store theme settings: colors, fonts, corner radius, light/dark mode, and starter template.
 * FR-BLD-03, FR-BLD-08
 *
 * @property int $id
 * @property int $store_id
 * @property string $primary_color
 * @property string $accent_color
 * @property string $font_heading
 * @property string $font_body
 * @property string $corner_radius
 * @property string $color_mode
 * @property string|null $template
 * @property string|null $custom_css
 * @property array<string, mixed>|null $settings
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Theme extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'primary_color',
        'accent_color',
        'font_heading',
        'font_body',
        'corner_radius',
        'color_mode',
        'template',
        'custom_css',
        'settings',
    ];

    /** Extra theme settings and their defaults. */
    public const SETTING_DEFAULTS = [
        'background_color' => '',
        'text_color' => '',
        'page_width' => 1280,
        'base_font_size' => 16,
        'heading_weight' => 600,
        'heading_uppercase' => false,
        'heading_letter_spacing' => 0,
        'button_style' => 'solid',
        'button_uppercase' => false,
        'card_style' => 'plain',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /** @return array<string, mixed> */
    public function resolvedSettings(): array
    {
        return array_replace(self::SETTING_DEFAULTS, array_intersect_key($this->settings ?? [], self::SETTING_DEFAULTS));
    }

    protected $attributes = [
        'primary_color' => '#111827',
        'accent_color' => '#2563eb',
        'font_heading' => 'Cairo',
        'font_body' => 'Cairo',
        'corner_radius' => 'md',
        'color_mode' => 'light',
    ];

    /**
     * CSS custom properties for rendering the storefront theme.
     *
     * @return array<string, string>
     */
    public function cssVariables(): array
    {
        $radiusMap = [
            'none' => '0px',
            'sm' => '0.25rem',
            'md' => '0.5rem',
            'lg' => '1rem',
            'full' => '9999px',
        ];

        return [
            '--color-primary' => $this->primary_color,
            '--color-accent' => $this->accent_color,
            '--font-heading' => $this->font_heading,
            '--font-body' => $this->font_body,
            '--radius' => $radiusMap[$this->corner_radius] ?? '0.5rem',
            '--color-mode' => $this->color_mode,
        ];
    }
}
