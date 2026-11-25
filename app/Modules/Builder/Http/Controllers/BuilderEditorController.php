<?php

namespace App\Modules\Builder\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Builder\Models\MediaFile;
use App\Modules\Builder\Models\Page;
use App\Modules\Builder\Models\PageVersion;
use App\Modules\Builder\Services\PageBuilderService;
use App\Modules\Builder\Services\SectionRegistry;
use App\Modules\Builder\Services\ThemeService;
use App\Modules\Builder\Storefront\StorefrontRenderer;
use App\Modules\Builder\Storefront\StorefrontView;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The visual store editor: pages, section/block editing with live preview, header/footer groups,
 * publish and version history. FR-BLD-01, FR-BLD-02, FR-BLD-03, FR-BLD-06, FR-BLD-07, FR-BLD-08
 */
class BuilderEditorController extends Controller
{
    public function __construct(
        private readonly CurrentStore $current,
        private readonly PageBuilderService $builder,
        private readonly SectionRegistry $registry,
        private readonly ThemeService $themes,
        private readonly StorefrontView $storefront,
    ) {}

    public function editor(Request $request): Response
    {
        $groups = $this->builder->ensureGroupPages();
        $store = $this->current->getOrFail();

        $pages = Page::query()->where('slug', 'not like', '\_\_%')->orderByDesc('is_home')->orderBy('id')->get();

        return Inertia::render('builder/editor', [
            'pages' => $pages->map(fn (Page $p) => $this->pageSummary($p))->values(),
            'groups' => ['header' => $this->pageSummary($groups['header']), 'footer' => $this->pageSummary($groups['footer'])],
            'initialPageId' => $request->integer('page') ?: $pages->firstWhere('is_home', true)?->id ?? $pages->first()?->id,
            'sectionTypes' => $this->registry->all(),
            'styleFields' => SectionRegistry::styleFields(),
            'templates' => PageBuilderService::STARTER_TEMPLATES,
            'theme' => $this->themes->current()->only(['primary_color', 'accent_color', 'font_heading', 'font_body', 'corner_radius', 'color_mode', 'custom_css']) + ['settings' => $this->themes->current()->resolvedSettings()],
            'storeUrl' => $store->url('/'.$store->default_locale),
            'catalog' => [
                'products' => Product::query()->latest('id')->limit(500)->get(['id', 'name', 'name_ar', 'price', 'images'])->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'image' => \App\Modules\Builder\Storefront\StorefrontCatalog::images($p)[0] ?? null])->values(),
                'categories' => Category::query()->orderBy('sort_order')->get(['id', 'name', 'name_ar'])->values(),
            ],
        ]);
    }

    public function show(int $page): JsonResponse
    {
        $record = Page::query()->with(['sections', 'versions' => fn ($q) => $q->limit(20)])->findOrFail($page);

        return response()->json(['page' => $this->pageSummary($record) + [
            'sections' => $record->sections->map(fn ($s) => ['id' => $s->id, 'type' => $s->type, 'is_visible' => $s->is_visible, 'settings' => $s->settings ?? []])->values(),
            'versions' => $record->versions->map(fn (PageVersion $v) => ['id' => $v->id, 'version_number' => $v->version_number, 'created_at' => $v->created_at?->toIso8601String(), 'sections' => count($v->snapshot['sections'] ?? [])])->values(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120', 'not_regex:/^__/'],
            'is_home' => ['sometimes', 'boolean'],
            'duplicate_of' => ['nullable', 'integer'],
        ]);

        $page = $this->builder->createPage($data);

        if (! empty($data['duplicate_of']) && ($source = Page::query()->find($data['duplicate_of'])) && ! str_starts_with($source->slug, '__')) {
            $this->builder->syncSections($page, $source->sections->map(fn ($s) => ['type' => $s->type, 'is_visible' => $s->is_visible, 'settings' => $s->settings ?? []])->all());
        } else {
            $this->builder->addSection($page, 'text_block', ['heading' => $page->title, 'heading_ar' => $page->title_ar ?? '']);
        }

        return response()->json(['page' => $this->pageSummary($page->fresh())], 201);
    }

    public function update(Request $request, int $page): JsonResponse
    {
        $record = Page::query()->findOrFail($page);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'title_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:120'],
            'is_home' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['page' => $this->pageSummary($this->builder->updatePageMeta($record, $data))]);
    }

    public function destroy(int $page): JsonResponse
    {
        $record = Page::query()->findOrFail($page);
        abort_if($record->is_home || str_starts_with($record->slug, '__'), 422, 'The home page and the header/footer cannot be deleted.');
        $record->delete();

        return response()->json(['deleted' => true]);
    }

    /** Save the whole section list of a page (debounced autosave from the editor). */
    public function saveSections(Request $request, int $page): JsonResponse
    {
        $record = Page::query()->findOrFail($page);
        $data = $request->validate([
            'sections' => ['present', 'array', 'max:80'],
            'sections.*.id' => ['nullable'],
            'sections.*.type' => ['required', 'string', Rule::in($this->registry->types())],
            'sections.*.is_visible' => ['sometimes', 'boolean'],
            'sections.*.settings' => ['sometimes', 'array'],
        ]);
        abort_if(strlen((string) json_encode($data['sections'])) > 1_500_000, 413, 'Page is too large.');

        $sections = $this->builder->syncSections($record, $data['sections']);

        return response()->json([
            'sections' => $sections->map(fn ($s) => ['id' => $s->id, 'type' => $s->type, 'is_visible' => $s->is_visible, 'settings' => $s->settings ?? []])->values(),
            'page' => $this->pageSummary($record->fresh()),
        ]);
    }

    /** Render one section from unsaved editor state for the live preview. */
    public function renderSection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'locale' => ['sometimes', Rule::in(config('minivora.locales'))],
            'template' => ['sometimes', 'string', 'max:20'],
            'section' => ['required', 'array'],
            'section.id' => ['required'],
            'section.type' => ['required', 'string', Rule::in($this->registry->types())],
            'section.is_visible' => ['sometimes', 'boolean'],
            'section.settings' => ['sometimes', 'array'],
        ]);

        $store = $this->current->getOrFail();
        $locale = $data['locale'] ?? $store->default_locale;
        app()->setLocale($locale);
        $sf = $this->storefront->context($store, $locale, true, $data['template'] ?? 'page');

        $html = $this->storefront->renderer()->renderOne([
            'id' => $data['section']['id'],
            'type' => $data['section']['type'],
            'is_visible' => (bool) ($data['section']['is_visible'] ?? true),
            'settings' => $data['section']['settings'] ?? [],
        ], $sf);

        return response()->json(['html' => $html]);
    }

    /** Full draft page for the editor's preview frame (same host as the dashboard). */
    public function preview(Request $request, int $page): HttpResponse
    {
        $record = Page::query()->findOrFail($page);
        $store = $this->current->getOrFail();
        $locale = in_array($request->query('locale'), config('minivora.locales'), true) ? (string) $request->query('locale') : $store->default_locale;
        app()->setLocale($locale);
        $sf = $this->storefront->context($store, $locale, true, $record->is_home ? 'home' : 'page');

        $response = $this->storefront->make($sf, $record->is_home ? 'storefront.pages.home' : 'storefront.pages.page', [
            'page' => $record,
            'sections' => $this->storefront->renderer()->sectionsFor($record, true),
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /** Publish the page together with the header and footer groups. FR-BLD-06, FR-BLD-07 */
    public function publish(Request $request, int $page): JsonResponse
    {
        $record = Page::query()->findOrFail($page);
        /** @var User $user */
        $user = $request->user();
        $groups = $this->builder->ensureGroupPages();

        $version = DB::transaction(function () use ($record, $user, $groups) {
            foreach ($groups as $group) {
                if ($group->id !== $record->id && $this->hasChanges($group)) {
                    $this->builder->publishPage($group, $user);
                }
            }

            return $this->builder->publishPage($record, $user);
        });

        return response()->json(['page' => $this->pageSummary($record->fresh()), 'version' => ['id' => $version->id, 'version_number' => $version->version_number]]);
    }

    public function restore(int $page, int $version): JsonResponse
    {
        $record = Page::query()->findOrFail($page);
        $versionRecord = PageVersion::query()->where('page_id', $record->id)->findOrFail($version);
        $this->builder->restoreVersion($record, $versionRecord);

        return $this->show($record->id);
    }

    public function media(): JsonResponse
    {
        return response()->json(['files' => MediaFile::query()->latest()->limit(300)->get()->map(fn (MediaFile $f) => [
            'id' => $f->id, 'url' => $f->url(), 'mime_type' => $f->mime_type, 'alt_en' => $f->alt_en,
        ])->values()]);
    }

    /** @return array<string, mixed> */
    private function pageSummary(Page $page): array
    {
        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'title_ar' => $page->title_ar,
            'is_home' => $page->is_home,
            'status' => $page->status,
            'published_at' => $page->published_at?->toIso8601String(),
            'updated_at' => $page->updated_at?->toIso8601String(),
            'has_changes' => $this->hasChanges($page),
            'url' => $page->is_home ? '/' : '/pages/'.$page->slug,
        ];
    }

    private function hasChanges(Page $page): bool
    {
        $latest = $page->versions()->first();

        return $latest === null || ($page->updated_at !== null && $page->updated_at->gt($latest->created_at));
    }
}
