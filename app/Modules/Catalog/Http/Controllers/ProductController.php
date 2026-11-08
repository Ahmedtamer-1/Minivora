<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Money\Piasters;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Products list, the two-step add flow (start from photos / a note / blank, then review) and edit.
 * FR-CAT-01. Products are always looked up through the store scope, so another store's id is a 404.
 */
class ProductController extends Controller
{
    private const FILTERS = ['all', 'live', 'draft', 'low', 'sold_out'];

    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): Response
    {
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'all';
        $search = trim((string) $request->query('q', ''));

        $query = Product::query()->latest('id');
        match ($filter) {
            'live' => $query->where('status', ProductStatus::Live),
            'draft' => $query->where('status', ProductStatus::Draft),
            'low' => $query->runningLow(),
            'sold_out' => $query->soldOut(),
            default => null,
        };
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('name_ar', 'like', $like));
        }

        $page = $query->paginate(23)->withQueryString()->through(fn (Product $p) => $p->toCard());

        return Inertia::render('products/index', [
            'products' => $page,
            'filter' => $filter,
            'search' => $search,
            'counts' => [
                'all' => Product::query()->count(),
                'live' => Product::query()->where('status', ProductStatus::Live)->count(),
                'draft' => Product::query()->where('status', ProductStatus::Draft)->count(),
                'low' => Product::query()->runningLow()->count(),
                'sold_out' => Product::query()->soldOut()->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('products/create', ['maxPhotos' => ProductService::MAX_IMAGES]);
    }

    /** Step 1: start a draft from photos, a note, or nothing, then go to the review step. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'photos' => ['array', 'max:'.ProductService::MAX_IMAGES],
            'photos.*' => ['image', 'max:8192'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $product = $this->products->startDraft($request->file('photos', []), $data['note'] ?? null);

        return redirect()->route('products.edit', $product->id);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $count = $this->products->importCsv($request->file('csv'));

        return redirect()->route('products.index', ['filter' => 'draft'])
            ->with('success', trans_choice('products.imported', $count, ['count' => $count]));
    }

    public function edit(int $product): Response
    {
        $product = Product::query()->findOrFail($product);

        return Inertia::render('products/edit', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'name_ar' => $product->name_ar,
                'description' => $product->description,
                'category' => $product->category,
                'price' => $product->price,
                'compare_at_price' => $product->compare_at_price,
                'cost' => $product->cost,
                'stock' => $product->stock,
                'status' => $product->status->value,
                'has_options' => $product->has_options,
                'colors' => $product->colors ?? [],
                'images' => array_map(
                    fn ($path, $url) => ['path' => $path, 'url' => $url],
                    $product->images ?? [],
                    $product->imageUrls(),
                ),
                'seo_title' => $product->seo_title,
                'seo_description' => $product->seo_description,
                'source' => $product->source,
            ],
            'maxPhotos' => ProductService::MAX_IMAGES,
            'categories' => Product::query()->whereNotNull('category')->distinct()->orderBy('category')->limit(30)->pluck('category'),
        ]);
    }

    /** Save the review form. `intent=publish` also publishes in the same request. */
    public function update(Request $request, int $product): RedirectResponse
    {
        $product = Product::query()->findOrFail($product);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:80'],
            'price' => ['required', 'string', 'max:20'],
            'compare_at_price' => ['nullable', 'string', 'max:20'],
            'cost' => ['nullable', 'string', 'max:20'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'has_options' => ['boolean'],
            'colors' => ['array', 'max:20'],
            'colors.*.name' => ['required', 'string', 'max:40'],
            'colors.*.hex' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'keep_images' => ['array'],
            'keep_images.*' => ['string'],
            'photos' => ['array', 'max:'.ProductService::MAX_IMAGES],
            'photos.*' => ['image', 'max:8192'],
            'intent' => ['nullable', Rule::in(['save', 'publish', 'unpublish'])],
        ]);

        $fields = [
            'name' => trim($data['name']),
            'name_ar' => $data['name_ar'] ?? null,
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'price' => $this->piasters($data['price'], 'price'),
            'compare_at_price' => $this->piastersOrNull($data['compare_at_price'] ?? null, 'compare_at_price'),
            'cost' => $this->piastersOrNull($data['cost'] ?? null, 'cost'),
            'stock' => (int) $data['stock'],
            'has_options' => (bool) ($data['has_options'] ?? false),
            'colors' => ($data['has_options'] ?? false) ? array_values($data['colors'] ?? []) : [],
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ];

        $this->products->update($product, $fields, $data['keep_images'] ?? [], $request->file('photos', []));

        $intent = $data['intent'] ?? 'save';
        if ($intent === 'publish') {
            $this->products->publish($product);

            return redirect()->route('products.index')->with('success', __('products.published', ['name' => $product->name]));
        }
        if ($intent === 'unpublish') {
            $this->products->unpublish($product);

            return back()->with('success', __('products.unpublished'));
        }

        return back()->with('success', __('products.saved'));
    }

    public function destroy(int $product): RedirectResponse
    {
        $product = Product::query()->findOrFail($product);
        $this->products->delete($product);

        return redirect()->route('products.index')->with('success', __('products.deleted'));
    }

    private function piasters(string $pounds, string $field): int
    {
        try {
            $amount = Piasters::fromPounds($pounds)->amount;
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([$field => __('products.invalid_amount')]);
        }
        if ($amount < 0) {
            throw ValidationException::withMessages([$field => __('products.invalid_amount')]);
        }

        return $amount;
    }

    private function piastersOrNull(?string $pounds, string $field): ?int
    {
        return $pounds === null || trim($pounds) === '' ? null : $this->piasters($pounds, $field);
    }
}
