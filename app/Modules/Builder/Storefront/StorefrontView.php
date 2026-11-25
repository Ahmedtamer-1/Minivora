<?php

namespace App\Modules\Builder\Storefront;

use App\Modules\Builder\Services\ThemeService;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
use App\Modules\Platform\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Renders a storefront template inside the store layout with the header and footer groups.
 */
final class StorefrontView
{
    public function __construct(
        private readonly StorefrontRenderer $renderer,
        private readonly ThemeService $themes,
        private readonly CartService $carts,
    ) {}

    public function context(Store $store, string $locale, bool $preview = false, string $template = 'page'): StorefrontContext
    {
        return new StorefrontContext($store, $locale, $this->themes->current(), $preview, $template);
    }

    /** @param  array<string, mixed>  $data */
    public function make(StorefrontContext $sf, string $view, array $data = [], int $status = 200): Response
    {
        $cartCount = 0;
        if (! $sf->preview) {
            try {
                // Read-only: never create a cart just because someone viewed a page.
                $request = request();
                $user = $request->user();
                $cart = Cart::query()
                    ->when($user !== null, fn ($q) => $q->where('user_id', $user?->id), fn ($q) => $q->where('session_id', $request->hasSession() ? $request->session()->getId() : '-'))
                    ->first();
                $cartCount = $cart === null ? 0 : (int) $cart->items()->sum('quantity');
            } catch (\Throwable) {
                $cartCount = 0;
            }
        }

        view()->share('cartCount', $cartCount);

        $header = $this->renderer->render($this->renderer->group(StorefrontRenderer::HEADER_SLUG, $sf->preview), $sf);
        $footer = $this->renderer->render($this->renderer->group(StorefrontRenderer::FOOTER_SLUG, $sf->preview), $sf);

        return response()->view($view, $data + [
            'sf' => $sf,
            'store' => $sf->store,
            'headerHtml' => $header,
            'footerHtml' => $footer,
            'renderer' => $this->renderer,
        ], $status);
    }

    public function renderer(): StorefrontRenderer
    {
        return $this->renderer;
    }

    public static function locale(Request $request, Store $store): string
    {
        $locale = (string) ($request->route('locale') ?? $request->query('locale', $store->default_locale));

        return in_array($locale, config('minivora.locales'), true) ? $locale : $store->default_locale;
    }
}
