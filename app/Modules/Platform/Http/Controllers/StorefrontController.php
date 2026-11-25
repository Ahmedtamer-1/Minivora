<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\StoreUrl;
use App\Http\Controllers\Controller;
use App\Modules\Builder\Models\Page;
use App\Modules\Builder\Storefront\StorefrontCatalog;
use App\Modules\Builder\Storefront\StorefrontView;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\StaticPage;
use App\Modules\Platform\Http\Middleware\StorefrontGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Shopper pages: builder home and custom pages, shop, category, product, search and cart, plus the
 * opening-soon unlock and owner preview. FR-BLD-01, FR-CAT-04, FR-CAT-07, FR-CAT-11, FR-CAT-15, FR-ONB-06
 */
class StorefrontController extends Controller
{
    public function __construct(
        private readonly CurrentStore $current,
        private readonly StorefrontView $view,
        private readonly StorefrontCatalog $catalog,
    ) {}

    public function root(): RedirectResponse
    {
        $store = $this->current->getOrFail();

        return redirect(StoreUrl::prefix($store->subdomain).'/'.$store->default_locale);
    }

    public function home(Request $request): Response
    {
        $store = $this->current->getOrFail();
        $sf = $this->view->context($store, StorefrontView::locale($request, $store), false, 'home');
        $page = Page::query()->where('is_home', true)->first();
        $sections = $this->view->renderer()->sectionsFor($page, false);

        return $this->view->make($sf, 'storefront.pages.home', ['page' => $page, 'sections' => $sections]);
    }

    public function page(Request $request, string $locale, string $slug): Response
    {
        $store = $this->current->getOrFail();
        $sf = $this->view->context($store, StorefrontView::locale($request, $store), false, 'page');

        $page = str_starts_with($slug, '__') ? null : Page::query()->where('slug', $slug)->first();
        if ($page !== null && $this->view->renderer()->isLive($page)) {
            return $this->view->make($sf, 'storefront.pages.page', [
                'page' => $page,
                'sections' => $this->view->renderer()->sectionsFor($page, false),
            ]);
        }

        $static = StaticPage::query()->where('slug', $slug)->first();
        abort_if($static === null, 404);

        return $this->view->make($sf, 'storefront.pages.static', ['static' => $static]);
    }

    public function shop(Request $request): Response
    {
        return $this->listing($request, null);
    }

    public function category(Request $request, string $locale, string $slug): Response
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();

        return $this->listing($request, $category);
    }

    public function search(Request $request): Response
    {
        return $this->listing($request, null, true);
    }

    private function listing(Request $request, ?Category $category, bool $search = false): Response
    {
        $store = $this->current->getOrFail();
        $sf = $this->view->context($store, StorefrontView::locale($request, $store), false, $search ? 'search' : 'collection');

        $filters = $request->only(['min', 'max', 'availability', 'sort', 'q']);
        if ($category !== null) {
            $filters['category_id'] = $category->id;
        }

        return $this->view->make($sf, 'storefront.pages.shop', [
            'category' => $category,
            'products' => $this->catalog->browse($filters),
            'categories' => $this->catalog->categories(),
            'filters' => $filters,
            'search' => $search,
        ]);
    }

    public function product(Request $request, string $locale, string $slug): Response
    {
        $store = $this->current->getOrFail();
        $sf = $this->view->context($store, StorefrontView::locale($request, $store), false, 'product');
        $product = $this->catalog->findProduct($slug);
        abort_if($product === null, 404);
        $product->load(['variants', 'categoryRecord', 'approvedReviews']);

        return $this->view->make($sf, 'storefront.pages.product', [
            'product' => $product,
            'related' => $this->catalog->related($product),
        ]);
    }

    public function cart(Request $request, CartService $carts): Response
    {
        $store = $this->current->getOrFail();
        $sf = $this->view->context($store, StorefrontView::locale($request, $store), false, 'cart');
        $cart = $carts->resolve($request->user(), $request->session()->getId());

        return $this->view->make($sf, 'storefront.pages.cart', ['summary' => $carts->summary($cart)]);
    }

    /** Signed link from the dashboard: unlock this browser and go home. */
    public function preview(Request $request): RedirectResponse
    {
        $store = $this->current->getOrFail();
        $request->session()->put(StorefrontGate::sessionKey($store->id), true);

        return redirect(StoreUrl::prefix($store->subdomain).'/'.$store->default_locale);
    }

    public function unlock(Request $request): RedirectResponse
    {
        $store = $this->current->getOrFail();
        $request->validate(['password' => ['required', 'string']]);

        $key = 'storefront-unlock:'.$store->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors(['password' => __('storefront.opening_soon.too_many')]);
        }

        if ($store->storefront_password === null || ! Hash::check((string) $request->input('password'), $store->storefront_password)) {
            RateLimiter::hit($key, 15 * 60);

            return back()->withErrors(['password' => __('storefront.opening_soon.wrong_password')]);
        }

        RateLimiter::clear($key);
        $request->session()->put(StorefrontGate::sessionKey($store->id), true);

        return redirect(StoreUrl::prefix($store->subdomain).'/'.$store->default_locale);
    }
}
