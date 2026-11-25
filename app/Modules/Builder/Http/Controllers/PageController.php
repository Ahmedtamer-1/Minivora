<?php

namespace App\Modules\Builder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Builder\Models\Page;
use App\Modules\Builder\Models\PageVersion;
use App\Modules\Builder\Models\Section;
use App\Modules\Builder\Services\PageBuilderService;
use App\Modules\Builder\Services\SectionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dashboard Store Builder endpoints: pages, sections, AI draft, publish/restore, starter templates.
 * FR-BLD-01, FR-BLD-02, FR-BLD-05, FR-BLD-06, FR-BLD-07, FR-BLD-08
 */
class PageController extends Controller
{
    public function __construct(
        private readonly PageBuilderService $builder,
        private readonly SectionRegistry $registry,
    ) {}

    public function index(): JsonResponse
    {
        $pages = Page::query()
            ->withCount('sections')
            ->orderByDesc('is_home')
            ->orderBy('id')
            ->get();

        return response()->json([
            'pages' => $pages,
            'section_types' => $this->registry->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120'],
            'is_home' => ['sometimes', 'boolean'],
        ]);

        $page = $this->builder->createPage($data);

        return response()->json(['page' => $page], 201);
    }

    public function show(int $page): JsonResponse
    {
        $record = Page::query()
            ->with(['sections', 'versions'])
            ->findOrFail($page);

        return response()->json(['page' => $record]);
    }

    public function addSection(Request $request, int $page): JsonResponse
    {
        $pageRecord = Page::query()->findOrFail($page);

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in($this->registry->types())],
            'settings' => ['sometimes', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_visible' => ['sometimes', 'boolean'],
        ]);

        $section = $this->builder->addSection(
            $pageRecord,
            $data['type'],
            $data['settings'] ?? [],
            $data['sort_order'] ?? null,
            (bool) ($data['is_visible'] ?? true),
        );

        return response()->json(['section' => $section], 201);
    }

    public function updateSection(Request $request, int $page, int $section): JsonResponse
    {
        $sectionRecord = Section::query()
            ->where('page_id', $page)
            ->findOrFail($section);

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'is_visible' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->builder->updateSection(
            $sectionRecord,
            $data['settings'],
            array_key_exists('is_visible', $data) ? (bool) $data['is_visible'] : null,
        );

        return response()->json(['section' => $updated]);
    }

    public function toggleSection(Request $request, int $page, int $section): JsonResponse
    {
        $sectionRecord = Section::query()
            ->where('page_id', $page)
            ->findOrFail($section);

        $data = $request->validate([
            'is_visible' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->builder->toggleSectionVisibility(
            $sectionRecord,
            array_key_exists('is_visible', $data) ? (bool) $data['is_visible'] : null,
        );

        return response()->json(['section' => $updated]);
    }

    public function reorderSections(Request $request, int $page): JsonResponse
    {
        $pageRecord = Page::query()->findOrFail($page);

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer'],
        ]);

        $sections = $this->builder->reorderSections($pageRecord, $data['order']);

        return response()->json(['sections' => $sections]);
    }

    public function deleteSection(int $page, int $section): JsonResponse
    {
        $sectionRecord = Section::query()
            ->where('page_id', $page)
            ->findOrFail($section);

        $this->builder->deleteSection($sectionRecord);

        return response()->json(['deleted' => true]);
    }

    public function publish(Request $request, int $page): JsonResponse
    {
        $pageRecord = Page::query()->findOrFail($page);
        /** @var User $owner */
        $owner = $request->user();

        $version = $this->builder->publishPage($pageRecord, $owner);

        return response()->json([
            'page' => $pageRecord->fresh('sections'),
            'version' => $version,
        ]);
    }

    public function restore(int $page, int $version): JsonResponse
    {
        $pageRecord = Page::query()->findOrFail($page);
        $versionRecord = PageVersion::query()
            ->where('page_id', $pageRecord->id)
            ->findOrFail($version);

        $restored = $this->builder->restoreVersion($pageRecord, $versionRecord);

        return response()->json(['page' => $restored]);
    }

    public function applyTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_type' => ['required', 'string', Rule::in(PageBuilderService::STARTER_TEMPLATES)],
        ]);

        $result = $this->builder->applyStarterTemplate($data['business_type']);

        return response()->json($result);
    }

    public function createAiDraft(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'spec' => ['sometimes', 'array'],
            'target_margin' => ['sometimes', 'numeric', 'min:0', 'lt:1'],
        ]);

        $result = $this->builder->createAiDraft(
            $data['description'],
            $data['spec'] ?? [],
            isset($data['target_margin']) ? (float) $data['target_margin'] : 0.40,
        );

        return response()->json($result, 201);
    }
}
