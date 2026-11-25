<?php

namespace App\Modules\Builder\Storefront;

use App\Modules\Builder\Models\Page;
use App\Modules\Builder\Models\PageVersion;
use App\Modules\Builder\Models\Section;
use App\Modules\Builder\Services\CssSanitizer;
use App\Modules\Builder\Services\SectionRegistry;
use Illuminate\Support\Facades\View;
use Throwable;

/**
 * Turns builder pages into storefront HTML.
 *
 * Shoppers see the latest published version of each page (FR-BLD-06/07: drafts never leak); the
 * editor's preview renders the current draft. The header and footer are section groups stored on
 * the system pages `__header` and `__footer`.
 */
final class StorefrontRenderer
{
    public const HEADER_SLUG = '__header';

    public const FOOTER_SLUG = '__footer';

    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly StorefrontCatalog $catalog,
    ) {}

    /**
     * Sections of a page as plain arrays: id, type, is_visible, settings.
     *
     * @return list<array{id: int|string, type: string, is_visible: bool, settings: array<string, mixed>}>
     */
    public function sectionsFor(?Page $page, bool $preview): array
    {
        if ($page === null) {
            return [];
        }

        if ($preview) {
            return $page->sections()->get()->map(fn (Section $s) => [
                'id' => $s->id,
                'type' => $s->type,
                'is_visible' => $s->is_visible,
                'settings' => $s->settings ?? [],
            ])->values()->all();
        }

        /** @var PageVersion|null $version */
        $version = $page->versions()->first();
        if ($version === null) {
            return [];
        }

        $sections = is_array($version->snapshot['sections'] ?? null) ? $version->snapshot['sections'] : [];

        return array_values(array_map(fn (array $s, int $i) => [
            'id' => $s['id'] ?? 'v'.$version->id.'-'.$i,
            'type' => (string) ($s['type'] ?? ''),
            'is_visible' => (bool) ($s['is_visible'] ?? true),
            'settings' => is_array($s['settings'] ?? null) ? $s['settings'] : [],
        ], $sections, array_keys($sections)));
    }

    /** Whether shoppers can see this page: it has been published at least once. */
    public function isLive(Page $page): bool
    {
        return $page->versions()->exists();
    }

    /**
     * Header or footer group sections; built-in defaults until the owner edits them.
     *
     * @return list<array{id: int|string, type: string, is_visible: bool, settings: array<string, mixed>}>
     */
    public function group(string $slug, bool $preview): array
    {
        $page = Page::query()->where('slug', $slug)->first();
        if ($page !== null && ($preview || $this->isLive($page))) {
            return $this->sectionsFor($page, $preview);
        }

        $types = $slug === self::HEADER_SLUG ? ['announcement_bar', 'header'] : ['footer'];

        return array_map(fn (string $type) => [
            'id' => $type,
            'type' => $type,
            'is_visible' => $type !== 'announcement_bar',
            'settings' => $this->registry->defaults($type),
        ], $types);
    }

    /** @param  list<array{id: int|string, type: string, is_visible: bool, settings: array<string, mixed>}>  $sections */
    public function render(array $sections, StorefrontContext $sf): string
    {
        $html = '';
        foreach ($sections as $index => $section) {
            if (! $this->registry->isValid($section['type'])) {
                continue;
            }
            if (! $section['is_visible'] && ! $sf->preview) {
                continue;
            }
            $html .= $this->renderOne($section, $sf, $index);
        }

        return $html;
    }

    /** @param  array{id: int|string, type: string, is_visible: bool, settings: array<string, mixed>}  $section */
    public function renderOne(array $section, StorefrontContext $sf, int $index = 0): string
    {
        $def = $this->registry->get($section['type']);
        $settings = array_replace($def['defaults'], $section['settings']);
        $style = array_replace(
            ['width' => 'contained', 'padding_top' => 48, 'padding_bottom' => 48, 'background' => '', 'text_color' => '', 'background_image' => null, 'text_align' => '', 'animation' => 'none', 'hide_on' => '', 'anchor' => '', 'custom_class' => ''],
            is_array($def['defaults']['style'] ?? null) ? $def['defaults']['style'] : [],
            is_array($section['settings']['style'] ?? null) ? $section['settings']['style'] : [],
        );

        $blocks = array_values(array_filter(
            is_array($settings['blocks'] ?? null) ? $settings['blocks'] : [],
            fn ($b) => is_array($b) && isset($def['blocks'][$b['type'] ?? '']) && empty($b['hidden']),
        ));
        $blocks = array_map(fn (array $b) => [
            'id' => (string) ($b['id'] ?? ''),
            'type' => (string) $b['type'],
            'settings' => array_replace(self::blockDefaults($def['blocks'][$b['type']]['fields']), is_array($b['settings'] ?? null) ? $b['settings'] : []),
        ], $blocks);

        $id = 'mv-s-'.preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $section['id']);
        $view = 'storefront.sections.'.str_replace('_', '-', $section['type']);

        try {
            $inner = View::make($view, [
                's' => $settings,
                'blocks' => $blocks,
                'sid' => $id,
                'index' => $index,
                'sf' => $sf,
                'catalog' => $this->catalog,
            ])->render();
        } catch (Throwable $e) {
            report($e);
            $inner = $sf->preview ? '<div class="mv-container mv-error">'.e($def['label']).': '.e($e->getMessage()).'</div>' : '';
        }

        $classes = ['mv-section', 'mv-section--'.str_replace('_', '-', $section['type']), 'mv-w-'.preg_replace('/[^a-z]/', '', (string) $style['width'])];
        if ($style['hide_on'] !== '') {
            $classes[] = 'mv-hide-'.preg_replace('/[^a-z]/', '', (string) $style['hide_on']);
        }
        if ($style['text_align'] !== '') {
            $classes[] = 'mv-ta-'.preg_replace('/[^a-z]/', '', (string) $style['text_align']);
        }
        if (! $section['is_visible']) {
            $classes[] = 'mv-is-hidden';
        }
        foreach (preg_split('/\s+/', (string) $style['custom_class']) ?: [] as $class) {
            if (preg_match('/^[A-Za-z_-][A-Za-z0-9_-]{0,60}$/', $class)) {
                $classes[] = $class;
            }
        }

        $vars = ['--pt:'.max(0, min(400, (int) $style['padding_top'])).'px', '--pb:'.max(0, min(400, (int) $style['padding_bottom'])).'px'];
        if (self::isColor($style['background'])) {
            $vars[] = '--section-bg:'.$style['background'];
        }
        if (self::isColor($style['text_color'])) {
            $vars[] = '--section-fg:'.$style['text_color'];
        }
        $bgImage = $sf->media($style['background_image']);
        if ($bgImage !== null) {
            $vars[] = "--section-image:url('".str_replace(["'", '\\', ')'], '', $bgImage)."')";
            $classes[] = 'mv-has-bg-image';
        }

        $attrs = 'id="'.e($id).'" class="'.e(implode(' ', $classes)).'" style="'.e(implode(';', $vars)).'"';
        if ($style['animation'] !== 'none') {
            $attrs .= ' data-animate="'.e((string) $style['animation']).'"';
        }
        if ($sf->preview) {
            $attrs .= ' data-mv-section="'.e((string) $section['id']).'" data-mv-label="'.e($sf->isAr() ? $def['label_ar'] : $def['label']).'"';
        }

        $html = ($style['anchor'] !== '' ? '<span id="'.e(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $style['anchor'])).'" class="mv-anchor"></span>' : '')
            .'<section '.$attrs.'>'.$inner.'</section>';

        $css = trim((string) ($settings['custom_css'] ?? ''));
        if ($css !== '') {
            $html .= '<style>'.CssSanitizer::scope($css, '#'.$id).'</style>';
        }

        return $html;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private static function blockDefaults(array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            $out[$field['id']] = $field['default'] ?? (($field['type'] ?? '') === 'checkbox' ? false : '');
            if (! empty($field['tr'])) {
                $out[$field['id'].'_ar'] = $field['default_ar'] ?? '';
            }
        }

        return $out;
    }

    public static function isColor(mixed $value): bool
    {
        return is_string($value) && (bool) preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s.,%]+\)|transparent)$/', $value);
    }
}
