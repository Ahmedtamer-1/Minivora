<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\AuditLog\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Review;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Verified customer reviews and merchant moderation. FR-CAT-10, FR-CAT-18
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Submit a product review. Requires a delivered order in the current store containing the product.
     * Reviews start unapproved until moderated by the merchant. FR-CAT-10
     */
    public function store(Request $request, int $product): JsonResponse|RedirectResponse
    {
        $product = Product::query()->findOrFail($product);

        $data = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'customer_name' => ['required', 'string', 'max:120'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = $this->findVerifiedDeliveredOrder($product, $data);
        if ($order === null) {
            throw ValidationException::withMessages([
                'order_id' => 'A delivered order containing this product is required to submit a review.',
            ]);
        }

        $review = Review::query()->create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'customer_name' => trim($data['customer_name']),
            'rating' => (int) $data['rating'],
            'body' => isset($data['body']) ? trim((string) $data['body']) : null,
            'approved' => false,
            'approved_at' => null,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['review' => $review], 201);
        }

        return back()->with('success', __('reviews.submitted'));
    }

    /**
     * Approve a pending review so it appears on the storefront. FR-CAT-18
     */
    public function approve(Request $request, int $review): JsonResponse|RedirectResponse
    {
        $review = Review::query()->findOrFail($review);

        $review->approved = true;
        $review->approved_at = Carbon::now();
        $review->save();

        $this->activity->log('review.approved', $review, ['product_id' => $review->product_id]);

        if ($request->expectsJson()) {
            return response()->json(['review' => $review]);
        }

        return back()->with('success', __('reviews.approved'));
    }

    /**
     * Delete a review. FR-CAT-18
     */
    public function destroy(Request $request, int $review): JsonResponse|RedirectResponse
    {
        $review = Review::query()->findOrFail($review);

        $this->activity->log('review.deleted', $review, ['product_id' => $review->product_id]);
        $review->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return back()->with('success', __('reviews.deleted'));
    }

    /**
     * Verify that the shopper has a delivered order in this store containing the product.
     *
     * @param  array<string, mixed>  $data
     */
    private function findVerifiedDeliveredOrder(Product $product, array $data): ?Order
    {
        $query = Order::query()
            ->where('status', OrderStatus::Delivered)
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id));

        if (! empty($data['order_id'])) {
            return $query->whereKey((int) $data['order_id'])->first();
        }

        if (! empty($data['email']) || ! empty($data['phone'])) {
            $query->where(function ($q) use ($data) {
                if (! empty($data['email'])) {
                    $q->orWhere('email', $data['email']);
                }
                if (! empty($data['phone'])) {
                    $q->orWhere('phone', $data['phone']);
                }
            });

            return $query->latest('id')->first();
        }

        return null;
    }
}
