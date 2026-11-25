<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\AuditLog\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Store category CRUD and drag-and-drop ordering. FR-CAT-03
 */
class CategoryController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'image' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $slug = $this->resolveSlug($data['slug'] ?? null, $data['name']);
        $this->ensureUniqueSlug($slug);

        $nextOrder = (int) (Category::query()->max('sort_order') ?? -1) + 1;

        $category = Category::query()->create([
            'name' => trim($data['name']),
            'name_ar' => isset($data['name_ar']) ? trim((string) $data['name_ar']) : null,
            'slug' => $slug,
            'image' => $data['image'] ?? null,
            'sort_order' => $data['sort_order'] ?? $nextOrder,
        ]);

        $this->activity->log('category.created', $category, ['name' => $category->name]);

        if ($request->expectsJson()) {
            return response()->json(['category' => $category], 201);
        }

        return back()->with('success', __('categories.created'));
    }

    public function update(Request $request, int $category): JsonResponse|RedirectResponse
    {
        $category = Category::query()->findOrFail($category);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'image' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $slug = $this->resolveSlug($data['slug'] ?? $category->slug, $data['name']);
        $this->ensureUniqueSlug($slug, $category->id);

        $category->fill([
            'name' => trim($data['name']),
            'name_ar' => array_key_exists('name_ar', $data) ? ($data['name_ar'] !== null ? trim((string) $data['name_ar']) : null) : $category->name_ar,
            'slug' => $slug,
            'image' => array_key_exists('image', $data) ? $data['image'] : $category->image,
            'sort_order' => $data['sort_order'] ?? $category->sort_order,
        ]);
        $category->save();

        $this->activity->log('category.updated', $category, ['name' => $category->name]);

        if ($request->expectsJson()) {
            return response()->json(['category' => $category]);
        }

        return back()->with('success', __('categories.updated'));
    }

    public function destroy(Request $request, int $category): JsonResponse|RedirectResponse
    {
        $category = Category::query()->findOrFail($category);

        DB::transaction(function () use ($category) {
            Product::query()->where('category_id', $category->id)->update(['category_id' => null]);
            $this->activity->log('category.deleted', $category, ['name' => $category->name]);
            $category->delete();
        });

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return back()->with('success', __('categories.deleted'));
    }

    /**
     * Persist a new display order for categories given an ordered list of category IDs. FR-CAT-03
     */
    public function reorder(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['order']) as $position => $id) {
                Category::query()->whereKey((int) $id)->update(['sort_order' => $position]);
            }
        });

        $categories = Category::query()->orderBy('sort_order')->orderBy('id')->get();

        if ($request->expectsJson()) {
            return response()->json(['categories' => $categories]);
        }

        return back()->with('success', __('categories.reordered'));
    }

    private function resolveSlug(?string $slug, string $fallbackName): string
    {
        $candidate = $slug !== null && trim($slug) !== '' ? $slug : $fallbackName;
        $ascii = Str::slug($candidate);
        if ($ascii !== '') {
            return $ascii;
        }

        return mb_strtolower((string) preg_replace('/\s+/u', '-', trim($candidate)), 'UTF-8');
    }

    private function ensureUniqueSlug(string $slug, ?int $ignoreId = null): void
    {
        $query = Category::query()->where('slug', $slug);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'slug' => __('validation.unique', ['attribute' => 'slug']),
            ]);
        }
    }
}
