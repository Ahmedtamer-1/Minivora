<?php

namespace App\Modules\Builder\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Modules\Accounts\Models\User;
use App\Modules\Builder\Events\PagePublished;
use App\Modules\Builder\Models\Page;
use App\Modules\Builder\Models\PageVersion;
use App\Modules\Builder\Models\Section;
use App\Modules\Builder\Models\Theme;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Store page builder: pages, sections, AI draft generation, margin-based pricing,
 * publish/version snapshots, restore, and starter templates.
 * FR-BLD-01, FR-BLD-02, FR-BLD-05, FR-BLD-06, FR-BLD-07, FR-BLD-08
 */
class PageBuilderService
{
    public const STARTER_TEMPLATES = ['fashion', 'food', 'handmade', 'electronics'];

    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly ThemeService $themes,
        private readonly ProductService $products,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Create a new draft page in the active store.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createPage(array $attributes): Page
    {
        $title = trim((string) ($attributes['title'] ?? 'Untitled Page'));
        $slug = $this->resolveSlug(isset($attributes['slug']) ? (string) $attributes['slug'] : null, $title);

        if (Page::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => __('validation.unique', ['attribute' => 'slug']),
            ]);
        }

        $isHome = (bool) ($attributes['is_home'] ?? false);

        return DB::transaction(function () use ($attributes, $title, $slug, $isHome): Page {
            if ($isHome) {
                Page::query()->where('is_home', true)->update(['is_home' => false]);
            }

            $page = Page::query()->create([
                'slug' => $slug,
                'title' => $title,
                'title_ar' => isset($attributes['title_ar']) ? trim((string) $attributes['title_ar']) : null,
                'is_home' => $isHome,
                'status' => Page::STATUS_DRAFT,
                'is_ai_draft' => (bool) ($attributes['is_ai_draft'] ?? false),
                'published_at' => null,
            ]);

            $this->activity->log('page.created', $page, ['slug' => $page->slug]);

            return $page;
        });
    }

    /**
     * Add a validated section to a page with merged registry defaults. FR-BLD-01, FR-BLD-02
     *
     * @param  array<string, mixed>  $settings
     */
    public function addSection(
        Page $page,
        string $type,
        array $settings = [],
        ?int $sortOrder = null,
        bool $isVisible = true,
    ): Section {
        if (! $this->registry->isValid($type)) {
            throw new InvalidArgumentException("Unsupported section type: {$type}");
        }

        $mergedSettings = array_replace($this->registry->defaults($type), $settings);
        $order = $sortOrder ?? ((int) ($page->sections()->max('sort_order') ?? -1) + 1);

        $section = Section::query()->create([
            'page_id' => $page->id,
            'type' => $type,
            'sort_order' => $order,
            'is_visible' => $isVisible,
            'settings' => $mergedSettings,
        ]);

        $this->activity->log('section.added', $section, ['page_id' => $page->id, 'type' => $type]);

        return $section;
    }

    /**
     * Update a section's settings (and optionally visibility). FR-BLD-02
     *
     * @param  array<string, mixed>  $settings
     */
    public function updateSection(Section $section, array $settings, ?bool $isVisible = null): Section
    {
        $section->settings = array_replace($section->settings ?? [], $settings);

        if ($isVisible !== null) {
            $section->is_visible = $isVisible;
        }

        $section->save();

        $this->activity->log('section.updated', $section, ['page_id' => $section->page_id, 'type' => $section->type]);

        return $section->refresh();
    }

    /**
     * Toggle or explicitly set a section's visibility. FR-BLD-02
     */
    public function toggleSectionVisibility(Section $section, ?bool $visible = null): Section
    {
        $section->is_visible = $visible ?? ! $section->is_visible;
        $section->save();

        $this->activity->log('section.visibility_toggled', $section, [
            'page_id' => $section->page_id,
            'is_visible' => $section->is_visible,
        ]);

        return $section->refresh();
    }

    /**
     * Persist a new display order for a page's sections. FR-BLD-02
     *
     * @param  list<int>  $orderedSectionIds
     * @return Collection<int, Section>
     */
    public function reorderSections(Page $page, array $orderedSectionIds): Collection
    {
        DB::transaction(function () use ($page, $orderedSectionIds): void {
            foreach (array_values($orderedSectionIds) as $position => $sectionId) {
                Section::query()
                    ->where('page_id', $page->id)
                    ->whereKey((int) $sectionId)
                    ->update(['sort_order' => $position]);
            }
        });

        $this->activity->log('section.reordered', $page, ['order' => array_values($orderedSectionIds)]);

        return $page->sections()->get();
    }

    /**
     * Remove a section from its page. FR-BLD-02
     */
    public function deleteSection(Section $section): void
    {
        $pageId = $section->page_id;
        $type = $section->type;

        $section->delete();

        $this->activity->log('section.deleted', null, ['page_id' => $pageId, 'type' => $type]);
    }

    /**
     * Pure-code margin pricing formula: round(unitCost / (1 - margin)) half-up in integer piasters.
     * Refuses invalid margins (< 0 or >= 1) and negative unit costs. FR-BLD-05
     */
    public function computePriceFromMargin(int $unitCostPiasters, float $targetMarginRatio): int
    {
        if ($unitCostPiasters < 0) {
            throw new InvalidArgumentException('Unit cost in piasters cannot be negative.');
        }

        if (! is_finite($targetMarginRatio) || $targetMarginRatio < 0.0 || $targetMarginRatio >= 1.0) {
            throw new InvalidArgumentException('Target margin ratio must be in [0, 1).');
        }

        return (int) round($unitCostPiasters / (1.0 - $targetMarginRatio), 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Turn a bilingual (AR/EN) business description and structured spec into:
     * - a draft homepage with validated sections (never auto-published, is_ai_draft = true)
     * - a draft store theme
     * - code-priced draft products (priced strictly via computePriceFromMargin)
     * FR-BLD-05, FR-BLD-06
     *
     * @param  array<string, mixed>  $spec
     * @return array{page: Page, theme: Theme, products: list<Product>}
     */
    public function createAiDraft(string $description, array $spec, float $targetMargin = 0.40): array
    {
        // Validate default margin immediately before writing anything.
        $this->computePriceFromMargin(0, $targetMargin);

        $trimmedDescription = trim($description);
        if ($trimmedDescription === '') {
            throw new InvalidArgumentException('Business description cannot be empty.');
        }

        return DB::transaction(function () use ($trimmedDescription, $spec, $targetMargin): array {
            $themeInput = is_array($spec['theme'] ?? null) ? $spec['theme'] : [];
            $theme = $themeInput !== []
                ? $this->themes->update($themeInput)
                : $this->themes->current();

            $hasExistingHome = Page::query()->where('is_home', true)->exists();
            $requestedSlug = isset($spec['slug']) ? trim((string) $spec['slug']) : 'home';
            $slug = $this->uniqueSlug($requestedSlug !== '' ? $requestedSlug : 'home');

            $page = Page::query()->create([
                'slug' => $slug,
                'title' => trim((string) ($spec['title'] ?? 'Home')),
                'title_ar' => isset($spec['title_ar']) ? trim((string) $spec['title_ar']) : 'الرئيسية',
                'is_home' => ! $hasExistingHome,
                'status' => Page::STATUS_DRAFT,
                'is_ai_draft' => true,
                'published_at' => null,
            ]);

            $sectionSpecs = is_array($spec['sections'] ?? null) && $spec['sections'] !== []
                ? $spec['sections']
                : [
                    [
                        'type' => 'hero',
                        'settings' => [
                            'headline' => (string) ($spec['title'] ?? 'Welcome'),
                            'headline_ar' => (string) ($spec['title_ar'] ?? 'أهلاً بك'),
                            'subheadline' => $trimmedDescription,
                            'subheadline_ar' => $trimmedDescription,
                        ],
                    ],
                    ['type' => 'featured_products', 'settings' => []],
                    [
                        'type' => 'brand_story',
                        'settings' => [
                            'body' => $trimmedDescription,
                            'body_ar' => $trimmedDescription,
                        ],
                    ],
                    ['type' => 'testimonials', 'settings' => []],
                ];

            foreach (array_values($sectionSpecs) as $index => $sectionSpec) {
                $type = (string) ($sectionSpec['type'] ?? '');
                $settings = is_array($sectionSpec['settings'] ?? null) ? $sectionSpec['settings'] : [];
                $this->addSection($page, $type, $settings, $index, (bool) ($sectionSpec['is_visible'] ?? true));
            }

            $createdProducts = [];
            $productSpecs = is_array($spec['products'] ?? null) ? $spec['products'] : [];

            foreach ($productSpecs as $item) {
                $unitCost = (int) ($item['unit_cost'] ?? $item['cost'] ?? 0);
                $margin = isset($item['target_margin']) ? (float) $item['target_margin'] : $targetMargin;
                $computedPrice = $this->computePriceFromMargin($unitCost, $margin);

                $draft = $this->products->startDraft();
                $updated = $this->products->update($draft, [
                    'name' => trim((string) ($item['name'] ?? 'Draft Product')),
                    'name_ar' => isset($item['name_ar']) ? trim((string) $item['name_ar']) : null,
                    'description' => isset($item['description']) ? (string) $item['description'] : $trimmedDescription,
                    'category' => isset($item['category']) ? (string) $item['category'] : null,
                    'cost' => $unitCost,
                    'price' => $computedPrice,
                    'stock' => max(0, (int) ($item['stock'] ?? 0)),
                ], []);

                $createdProducts[] = $updated;
            }

            $this->activity->log('page.ai_draft_created', $page, [
                'sections_count' => count($sectionSpecs),
                'products_count' => count($createdProducts),
            ]);

            return [
                'page' => $page->load('sections'),
                'theme' => $theme,
                'products' => $createdProducts,
            ];
        });
    }

    /**
     * Owner approves and publishes a page, saving an immutable PageVersion snapshot
     * and dispatching PagePublished. FR-BLD-06, FR-BLD-07
     */
    public function publishPage(Page $page, User $owner): PageVersion
    {
        return DB::transaction(function () use ($page, $owner): PageVersion {
            $page->status = Page::STATUS_PUBLISHED;
            $page->published_at = Carbon::now();
            $page->save();

            $sections = $page->sections()->get();
            $nextVersion = (int) ($page->versions()->max('version_number') ?? 0) + 1;

            $snapshot = [
                'slug' => $page->slug,
                'title' => $page->title,
                'title_ar' => $page->title_ar,
                'is_home' => $page->is_home,
                'sections' => $sections->map(fn (Section $section): array => [
                    'type' => $section->type,
                    'sort_order' => $section->sort_order,
                    'is_visible' => $section->is_visible,
                    'settings' => $section->settings ?? [],
                ])->values()->all(),
            ];

            $version = PageVersion::query()->create([
                'page_id' => $page->id,
                'version_number' => $nextVersion,
                'snapshot' => $snapshot,
                'published_by' => $owner->id,
            ]);

            $this->activity->log('page.published', $page, [
                'version_number' => $version->version_number,
                'published_by' => $owner->id,
            ]);

            PagePublished::dispatch($page, $version);

            return $version;
        });
    }

    /**
     * Restore a page's sections and metadata from a saved PageVersion snapshot. FR-BLD-07
     */
    public function restoreVersion(Page $page, PageVersion $version): Page
    {
        if ($version->page_id !== $page->id) {
            throw new InvalidArgumentException('PageVersion does not belong to the specified Page.');
        }

        return DB::transaction(function () use ($page, $version): Page {
            $snapshot = $version->snapshot;

            if (isset($snapshot['title'])) {
                $page->title = (string) $snapshot['title'];
            }
            if (array_key_exists('title_ar', $snapshot)) {
                $page->title_ar = $snapshot['title_ar'] !== null ? (string) $snapshot['title_ar'] : null;
            }
            $page->save();

            Section::query()->where('page_id', $page->id)->delete();

            $sections = is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : [];
            foreach (array_values($sections) as $index => $sectionData) {
                $type = (string) ($sectionData['type'] ?? '');
                if (! $this->registry->isValid($type)) {
                    continue;
                }

                Section::query()->create([
                    'page_id' => $page->id,
                    'type' => $type,
                    'sort_order' => (int) ($sectionData['sort_order'] ?? $index),
                    'is_visible' => (bool) ($sectionData['is_visible'] ?? true),
                    'settings' => is_array($sectionData['settings'] ?? null) ? $sectionData['settings'] : [],
                ]);
            }

            $this->activity->log('page.version_restored', $page, [
                'version_number' => $version->version_number,
            ]);

            return $page->fresh('sections');
        });
    }

    /**
     * Apply a starter template for one of the 4 supported business types:
     * fashion, food, handmade, electronics. FR-BLD-08
     *
     * @return array{page: Page, theme: Theme, template: string}
     */
    public function applyStarterTemplate(string $businessType): array
    {
        $type = strtolower(trim($businessType));
        $templates = $this->starterTemplateDefinitions();

        if (! array_key_exists($type, $templates)) {
            throw new InvalidArgumentException("Unsupported starter template: {$businessType}");
        }

        $definition = $templates[$type];

        return DB::transaction(function () use ($type, $definition): array {
            $theme = $this->themes->update($definition['theme'] + ['template' => $type]);

            $page = Page::query()->where('is_home', true)->first();

            if ($page === null) {
                $page = Page::query()->create([
                    'slug' => $this->uniqueSlug('home'),
                    'title' => $definition['title'],
                    'title_ar' => $definition['title_ar'],
                    'is_home' => true,
                    'status' => Page::STATUS_DRAFT,
                    'is_ai_draft' => false,
                ]);
            } else {
                $page->title = $definition['title'];
                $page->title_ar = $definition['title_ar'];
                $page->save();
                Section::query()->where('page_id', $page->id)->delete();
            }

            foreach (array_values($definition['sections']) as $index => $sectionSpec) {
                $this->addSection($page, $sectionSpec['type'], $sectionSpec['settings'], $index);
            }

            $this->activity->log('builder.template_applied', $page, ['template' => $type]);

            return [
                'page' => $page->load('sections'),
                'theme' => $theme,
                'template' => $type,
            ];
        });
    }

    private function resolveSlug(?string $slug, string $fallbackTitle): string
    {
        $candidate = $slug !== null && trim($slug) !== '' ? $slug : $fallbackTitle;
        $ascii = Str::slug($candidate);
        if ($ascii !== '') {
            return $ascii;
        }

        return mb_strtolower((string) preg_replace('/\s+/u', '-', trim($candidate)), 'UTF-8');
    }

    private function uniqueSlug(string $baseSlug): string
    {
        $clean = $this->resolveSlug($baseSlug, 'page');
        $candidate = $clean;
        $suffix = 1;

        while (Page::query()->where('slug', $candidate)->exists()) {
            $candidate = "{$clean}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @return array<string, array{title: string, title_ar: string, theme: array<string, string>, sections: list<array{type: string, settings: array<string, mixed>}>}>
     */
    private function starterTemplateDefinitions(): array
    {
        return [
            'fashion' => [
                'title' => 'Fashion Store',
                'title_ar' => 'متجر الأزياء',
                'theme' => [
                    'primary_color' => '#111827',
                    'accent_color' => '#b45309',
                    'font_heading' => 'Playfair Display',
                    'font_body' => 'Cairo',
                    'corner_radius' => 'none',
                    'color_mode' => 'light',
                ],
                'sections' => [
                    ['type' => 'hero', 'settings' => ['headline' => 'New Season Collection', 'headline_ar' => 'تشكيلة الموسم الجديد']],
                    ['type' => 'category_grid', 'settings' => ['heading' => 'Shop by Style', 'heading_ar' => 'تسوق حسب الستايل']],
                    ['type' => 'featured_products', 'settings' => ['heading' => 'Trending Now', 'heading_ar' => 'الأكثر رواجاً']],
                    ['type' => 'image_carousel', 'settings' => []],
                    ['type' => 'social_carousel', 'settings' => ['heading' => 'Styled by You', 'heading_ar' => 'إطلالات عملائنا']],
                ],
            ],
            'food' => [
                'title' => 'Food & Bakery',
                'title_ar' => 'متجر الأطعمة والمخبوزات',
                'theme' => [
                    'primary_color' => '#7c2d12',
                    'accent_color' => '#ea580c',
                    'font_heading' => 'Cairo',
                    'font_body' => 'Cairo',
                    'corner_radius' => 'lg',
                    'color_mode' => 'light',
                ],
                'sections' => [
                    ['type' => 'hero', 'settings' => ['headline' => 'Freshly Made Daily', 'headline_ar' => 'طازج يومياً']],
                    ['type' => 'featured_products', 'settings' => ['heading' => 'Signature Menu', 'heading_ar' => 'أشهر الأصناف']],
                    ['type' => 'brand_story', 'settings' => ['heading' => 'Our Kitchen', 'heading_ar' => 'مطبخنا']],
                    ['type' => 'testimonials', 'settings' => ['heading' => 'Loved by Foodies', 'heading_ar' => 'آراء الذواقة']],
                ],
            ],
            'handmade' => [
                'title' => 'Handmade & Crafts',
                'title_ar' => 'متجر المشغولات اليدوية',
                'theme' => [
                    'primary_color' => '#3f3f46',
                    'accent_color' => '#0f766e',
                    'font_heading' => 'Amiri',
                    'font_body' => 'Cairo',
                    'corner_radius' => 'md',
                    'color_mode' => 'light',
                ],
                'sections' => [
                    ['type' => 'hero', 'settings' => ['headline' => 'Crafted by Hand', 'headline_ar' => 'صُنع بحب وبأيدٍ مصرية']],
                    ['type' => 'brand_story', 'settings' => ['heading' => 'The Artisan Story', 'heading_ar' => 'قصة الحرفة']],
                    ['type' => 'featured_products', 'settings' => ['heading' => 'Small-Batch Pieces', 'heading_ar' => 'قطع محدودة الإصدار']],
                    ['type' => 'video_reels', 'settings' => ['heading' => 'Behind the Scenes', 'heading_ar' => 'كواليس الصنع']],
                    ['type' => 'testimonial_carousel', 'settings' => []],
                ],
            ],
            'electronics' => [
                'title' => 'Electronics & Tech',
                'title_ar' => 'متجر الإلكترونيات',
                'theme' => [
                    'primary_color' => '#0f172a',
                    'accent_color' => '#2563eb',
                    'font_heading' => 'Inter',
                    'font_body' => 'Cairo',
                    'corner_radius' => 'sm',
                    'color_mode' => 'dark',
                ],
                'sections' => [
                    ['type' => 'video_hero', 'settings' => ['headline' => 'Next-Gen Tech', 'headline_ar' => 'أحدث التقنيات']],
                    ['type' => 'category_grid', 'settings' => ['heading' => 'Browse Devices', 'heading_ar' => 'تصفح الأجهزة']],
                    ['type' => 'featured_products', 'settings' => ['heading' => 'Top Picks', 'heading_ar' => 'أفضل الاختيارات']],
                    ['type' => 'logo_carousel', 'settings' => ['heading' => 'Top Brands', 'heading_ar' => 'أشهر العلامات']],
                    ['type' => 'testimonials', 'settings' => []],
                ],
            ],
        ];
    }
}
