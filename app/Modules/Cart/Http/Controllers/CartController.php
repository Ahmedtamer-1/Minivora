<?php

namespace App\Modules\Cart\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Data\AddToCartData;
use App\Modules\Cart\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Storefront cart endpoints: view summary, add item, update quantity, remove item.
 * FR-CRT-01..04
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->carts->resolve($request->user(), $request->session()->getId());
        $governorate = $request->string('governorate')->toString() ?: null;
        $threshold = $request->has('free_shipping_threshold')
            ? $request->integer('free_shipping_threshold')
            : null;

        return response()->json($this->carts->summary($cart, $governorate, $threshold));
    }

    public function store(Request $request): JsonResponse
    {
        $data = AddToCartData::validateAndCreate($request->all());
        $cart = $this->carts->resolve($request->user(), $request->session()->getId());

        $item = $this->carts->addItem($cart, $data->product_id, $data->quantity, $data->variant_id);

        return response()->json([
            'item' => $item,
            'cart' => $this->carts->summary($cart),
        ], 201);
    }

    public function update(Request $request, int $item): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $cart = $this->carts->resolve($request->user(), $request->session()->getId());
        $updated = $this->carts->updateQuantity($cart, $item, (int) $validated['quantity']);

        return response()->json([
            'item' => $updated,
            'cart' => $this->carts->summary($cart),
        ]);
    }

    public function destroy(Request $request, int $item): JsonResponse
    {
        $cart = $this->carts->resolve($request->user(), $request->session()->getId());
        $this->carts->removeItem($cart, $item);

        return response()->json([
            'cart' => $this->carts->summary($cart),
        ]);
    }
}
