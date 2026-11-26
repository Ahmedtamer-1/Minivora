<?php

namespace App\Modules\Cart\Services;

use App\Modules\Accounts\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartEvent;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Orders\Models\ShippingZone;
use App\Modules\Orders\Services\ShippingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cart persistence, guest-to-user merge, stock/price reconciliation, event logging, and summary.
 * FR-CRT-01, FR-CRT-02, FR-CRT-03, FR-CRT-04
 */
class CartService
{
    public function __construct(
        private readonly ?ShippingService $shipping = null,
    ) {}

    /**
     * Resolve or create the active cart for a registered user or guest session. FR-CRT-01
     */
    public function resolve(?User $user, string $sessionId): Cart
    {
        if ($user !== null) {
            return Cart::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['session_id' => $sessionId !== '' ? $sessionId : null],
            );
        }

        return Cart::query()->firstOrCreate(
            ['user_id' => null, 'session_id' => $sessionId],
        );
    }

    /**
     * Merge any guest cart bound to $sessionId into $user's cart, summing quantities capped at
     * current stock and deleting the guest cart. FR-CRT-01
     */
    public function mergeGuestIntoUser(string $sessionId, User $user): Cart
    {
        $guestCart = Cart::query()
            ->whereNull('user_id')
            ->where('session_id', $sessionId)
            ->with('items')
            ->first();

        $userCart = $this->resolve($user, $sessionId);

        if ($guestCart === null || $guestCart->id === $userCart->id) {
            return $userCart->load('items');
        }

        DB::transaction(function () use ($guestCart, $userCart): void {
            foreach ($guestCart->items as $guestItem) {
                $product = Product::query()->find($guestItem->product_id);
                if ($product === null || ! $product->isLive() || $product->stock <= 0) {
                    continue;
                }

                $existing = $this->findCartItem($userCart, $guestItem->product_id, $guestItem->variant_id);
                $desired = ($existing?->quantity ?? 0) + $guestItem->quantity;
                $capped = min($desired, $product->stock);

                if ($existing !== null) {
                    $oldQuantity = $existing->quantity;
                    $existing->update([
                        'quantity' => $capped,
                        'unit_price_snapshot' => $product->price,
                    ]);

                    if ($capped !== $oldQuantity) {
                        $this->recordEvent(
                            cart: $userCart,
                            productId: $product->id,
                            variantId: $guestItem->variant_id,
                            eventType: CartEvent::TYPE_QUANTITY_CHANGE,
                            delta: $capped - $oldQuantity,
                            quantityAfter: $capped,
                            unitPrice: $product->price,
                        );
                    }
                } else {
                    $userCart->items()->create([
                        'product_id' => $product->id,
                        'variant_id' => $guestItem->variant_id,
                        'quantity' => $capped,
                        'unit_price_snapshot' => $product->price,
                    ]);

                    $this->recordEvent(
                        cart: $userCart,
                        productId: $product->id,
                        variantId: $guestItem->variant_id,
                        eventType: CartEvent::TYPE_ADD,
                        delta: $capped,
                        quantityAfter: $capped,
                        unitPrice: $product->price,
                    );
                }
            }

            if ($userCart->discount_code === null && $guestCart->discount_code !== null) {
                $userCart->update(['discount_code' => $guestCart->discount_code]);
            }

            $guestCart->delete();
        });

        return $userCart->fresh(['items']) ?? $userCart;
    }

    /**
     * Add a product (or increment its quantity) in the cart after checking status and stock.
     * FR-CRT-02, FR-CRT-03
     *
     * @throws ValidationException
     */
    public function addItem(Cart $cart, int $productId, int $quantity = 1, ?int $variantId = null): CartItem
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        $product = Product::query()->find($productId);
        if ($product === null || ! $product->isLive()) {
            throw ValidationException::withMessages([
                'product_id' => 'This product is not available.',
            ]);
        }

        ['stock' => $stock, 'price' => $price] = $this->availability($product, $variantId);

        $existing = $this->findCartItem($cart, $productId, $variantId);
        $newQuantity = ($existing?->quantity ?? 0) + $quantity;

        if ($stock <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'This product is out of stock.',
            ]);
        }

        if ($newQuantity > $stock) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$stock} left in stock.",
            ]);
        }

        if ($existing !== null) {
            $existing->update([
                'quantity' => $newQuantity,
                'unit_price_snapshot' => $price,
            ]);

            $this->recordEvent(
                cart: $cart,
                productId: $product->id,
                variantId: $variantId,
                eventType: CartEvent::TYPE_QUANTITY_CHANGE,
                delta: $quantity,
                quantityAfter: $newQuantity,
                unitPrice: $price,
            );

            return $existing->refresh();
        }

        $item = $cart->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'unit_price_snapshot' => $price,
        ]);

        $this->recordEvent(
            cart: $cart,
            productId: $product->id,
            variantId: $variantId,
            eventType: CartEvent::TYPE_ADD,
            delta: $quantity,
            quantityAfter: $quantity,
            unitPrice: $price,
        );

        return $item;
    }

    /**
     * Update an existing cart item's quantity, or remove it when quantity <= 0.
     * FR-CRT-02, FR-CRT-03
     *
     * @throws ValidationException
     */
    public function updateQuantity(Cart $cart, int $itemId, int $quantity): ?CartItem
    {
        $item = $cart->items()->whereKey($itemId)->firstOrFail();

        if ($quantity <= 0) {
            $this->removeItem($cart, $itemId);

            return null;
        }

        $product = Product::query()->find($item->product_id);
        if ($product === null || ! $product->isLive()) {
            throw ValidationException::withMessages([
                'product_id' => 'This product is not available.',
            ]);
        }

        ['stock' => $stock, 'price' => $price] = $this->availability($product, $item->variant_id);

        if ($stock <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'This product is out of stock.',
            ]);
        }

        if ($quantity > $stock) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$stock} left in stock.",
            ]);
        }

        $delta = $quantity - $item->quantity;

        $item->update([
            'quantity' => $quantity,
            'unit_price_snapshot' => $price,
        ]);

        $this->recordEvent(
            cart: $cart,
            productId: $item->product_id,
            variantId: $item->variant_id,
            eventType: CartEvent::TYPE_QUANTITY_CHANGE,
            delta: $delta,
            quantityAfter: $quantity,
            unitPrice: $price,
        );

        return $item->refresh();
    }

    /**
     * Remove an item from the cart and record a remove event. FR-CRT-02
     */
    public function removeItem(Cart $cart, int $itemId): void
    {
        $item = $cart->items()->whereKey($itemId)->firstOrFail();
        $oldQuantity = $item->quantity;
        $productId = $item->product_id;
        $variantId = $item->variant_id;
        $unitPrice = $item->unit_price_snapshot;

        $item->delete();

        $this->recordEvent(
            cart: $cart,
            productId: $productId,
            variantId: $variantId,
            eventType: CartEvent::TYPE_REMOVE,
            delta: -$oldQuantity,
            quantityAfter: 0,
            unitPrice: $unitPrice,
        );
    }

    /**
     * Re-validate every cart item against live product status, stock, and price, adjusting the cart
     * and returning human-readable adjustment notices. FR-CRT-03
     *
     * @return list<array{
     *     item_name: string,
     *     product_id: int,
     *     reason: string,
     *     old_value: int,
     *     new_value: int,
     *     message: string
     * }>
     */
    public function reconcile(Cart $cart): array
    {
        $items = $cart->items()->get();
        if ($items->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', $items->pluck('product_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $notices = [];

        foreach ($items as $item) {
            /** @var Product|null $product */
            $product = $products->get($item->product_id);

            if ($product === null || ! $product->isLive()) {
                $itemName = $product?->name ?? "Product #{$item->product_id}";
                $oldQuantity = $item->quantity;

                $item->delete();

                $this->recordEvent(
                    cart: $cart,
                    productId: $item->product_id,
                    variantId: $item->variant_id,
                    eventType: CartEvent::TYPE_REMOVE,
                    delta: -$oldQuantity,
                    quantityAfter: 0,
                    unitPrice: $item->unit_price_snapshot,
                );

                $notices[] = [
                    'item_name' => $itemName,
                    'product_id' => $item->product_id,
                    'reason' => 'unavailable',
                    'old_value' => $oldQuantity,
                    'new_value' => 0,
                    'message' => "{$itemName} is no longer available and was removed from your cart.",
                ];

                continue;
            }

            // The chosen size/colour's stock and price; a removed option counts as out of stock.
            try {
                $avail = $this->availability($product, $item->variant_id);
            } catch (ValidationException) {
                $avail = ['stock' => 0, 'price' => (int) $item->unit_price_snapshot];
            }

            if ($avail['stock'] <= 0) {
                $oldQuantity = $item->quantity;

                $item->delete();

                $this->recordEvent(
                    cart: $cart,
                    productId: $product->id,
                    variantId: $item->variant_id,
                    eventType: CartEvent::TYPE_REMOVE,
                    delta: -$oldQuantity,
                    quantityAfter: 0,
                    unitPrice: $avail['price'],
                );

                $notices[] = [
                    'item_name' => $product->name,
                    'product_id' => $product->id,
                    'reason' => 'out_of_stock',
                    'old_value' => $oldQuantity,
                    'new_value' => 0,
                    'message' => "{$product->name} is now out of stock and was removed from your cart.",
                ];

                continue;
            }

            if ($item->quantity > $avail['stock']) {
                $oldQuantity = $item->quantity;
                $newQuantity = $avail['stock'];

                $item->update(['quantity' => $newQuantity]);

                $this->recordEvent(
                    cart: $cart,
                    productId: $product->id,
                    variantId: $item->variant_id,
                    eventType: CartEvent::TYPE_QUANTITY_CHANGE,
                    delta: $newQuantity - $oldQuantity,
                    quantityAfter: $newQuantity,
                    unitPrice: $avail['price'],
                );

                $notices[] = [
                    'item_name' => $product->name,
                    'product_id' => $product->id,
                    'reason' => 'stock_reduced',
                    'old_value' => $oldQuantity,
                    'new_value' => $newQuantity,
                    'message' => "{$product->name} quantity was reduced from {$oldQuantity} to {$newQuantity} due to limited stock.",
                ];
            }

            if ($item->unit_price_snapshot !== $avail['price']) {
                $oldPrice = $item->unit_price_snapshot;
                $newPrice = $avail['price'];

                $item->update(['unit_price_snapshot' => $newPrice]);

                $notices[] = [
                    'item_name' => $product->name,
                    'product_id' => $product->id,
                    'reason' => 'price_changed',
                    'old_value' => $oldPrice,
                    'new_value' => $newPrice,
                    'message' => "{$product->name} price changed from {$oldPrice} to {$newPrice}.",
                ];
            }
        }

        $cart->unsetRelation('items');

        return $notices;
    }

    /**
     * Reconcile the cart and return totals, shipping estimate, free-shipping progress, and notices.
     * FR-CRT-04
     *
     * @return array{
     *     cart_id: int,
     *     items: list<array{
     *         id: int,
     *         product_id: int,
     *         variant_id: int|null,
     *         name: string,
     *         name_ar: string|null,
     *         quantity: int,
     *         unit_price: int,
     *         line_total: int,
     *         stock: int,
     *         cover: string|null
     *     }>,
     *     subtotal: int,
     *     shipping_estimate: int,
     *     free_shipping_remaining: int|null,
     *     total: int,
     *     notices: list<array{
     *         item_name: string,
     *         product_id: int,
     *         reason: string,
     *         old_value: int,
     *         new_value: int,
     *         message: string
     *     }>
     * }
     */
    public function summary(Cart $cart, ?string $governorate = null, ?int $freeShippingThreshold = null): array
    {
        $notices = $this->reconcile($cart);

        $cartItems = $cart->items()->get();
        $products = Product::query()
            ->whereIn('id', $cartItems->pluck('product_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $items = [];
        $subtotal = 0;

        $variants = ProductVariant::query()->whereIn('id', $cartItems->pluck('variant_id')->filter()->unique()->all())->get()->keyBy('id');

        foreach ($cartItems as $item) {
            /** @var Product|null $product */
            $product = $products->get($item->product_id);
            $unitPrice = $item->unit_price_snapshot;
            $lineTotal = $unitPrice * $item->quantity;
            $subtotal += $lineTotal;

            $items[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'name' => $product?->name ?? "Product #{$item->product_id}",
                'name_ar' => $product?->name_ar,
                'variant' => $item->variant_id !== null ? $variants->get($item->variant_id)?->label() : null,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'stock' => $item->variant_id !== null ? ($variants->get($item->variant_id)->stock ?? 0) : ($product?->stock ?? 0),
                'cover' => $product?->imageUrls()[0] ?? null,
            ];
        }

        $freeShippingRemaining = ($freeShippingThreshold !== null && $freeShippingThreshold > 0)
            ? max(0, $freeShippingThreshold - $subtotal)
            : null;

        $shippingEstimate = $this->resolveShippingEstimate($governorate, $subtotal, $freeShippingThreshold);
        $total = $subtotal + $shippingEstimate;

        return [
            'cart_id' => $cart->id,
            'items' => $items,
            'subtotal' => $subtotal,
            'shipping_estimate' => $shippingEstimate,
            'free_shipping_remaining' => $freeShippingRemaining,
            'total' => $total,
            'notices' => $notices,
        ];
    }

    private function findCartItem(Cart $cart, int $productId, ?int $variantId): ?CartItem
    {
        $query = $cart->items()->where('product_id', $productId);

        if ($variantId === null) {
            $query->whereNull('variant_id');
        } else {
            $query->where('variant_id', $variantId);
        }

        return $query->first();
    }

    private function recordEvent(
        Cart $cart,
        int $productId,
        ?int $variantId,
        string $eventType,
        int $delta,
        int $quantityAfter,
        int $unitPrice,
    ): void {
        $cart->events()->create([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'event_type' => $eventType,
            'delta' => $delta,
            'quantity_after' => $quantityAfter,
            'unit_price' => $unitPrice,
        ]);
    }

    private function resolveShippingEstimate(?string $governorate, int $subtotal, ?int $freeShippingThreshold): int
    {
        if ($governorate === null || trim($governorate) === '') {
            return 0;
        }

        if ($freeShippingThreshold !== null && $freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold) {
            return 0;
        }

        $zoneRate = ShippingZone::query()
            ->where('governorate', $governorate)
            ->value('rate');

        if ($zoneRate === null) {
            $zoneRate = ShippingZone::query()
                ->whereRaw('LOWER(governorate) = ?', [strtolower($governorate)])
                ->value('rate');
        }

        if ($zoneRate !== null) {
            return (int) $zoneRate;
        }

        $shipping = $this->shipping ?? app(ShippingService::class);

        return $shipping->feeFor(strtolower($governorate));
    }

    /**
     * Stock and price for a product, or for its chosen size/colour. A product with variants must
     * have one chosen, and it must belong to that product. FR-CAT-02
     *
     * @return array{stock: int, price: int}
     */
    private function availability(Product $product, ?int $variantId): array
    {
        $hasVariants = $product->variants()->exists();

        if ($variantId === null) {
            if ($hasVariants) {
                throw ValidationException::withMessages(['variant_id' => 'Please choose an option first.']);
            }

            return ['stock' => (int) $product->stock, 'price' => (int) $product->price];
        }

        $variant = ProductVariant::query()->where('product_id', $product->id)->find($variantId);
        if ($variant === null) {
            throw ValidationException::withMessages(['variant_id' => 'This option is not available.']);
        }

        return ['stock' => (int) $variant->stock, 'price' => (int) ($variant->price ?: $product->price)];
    }
}
