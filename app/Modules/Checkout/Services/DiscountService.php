<?php

namespace App\Modules\Checkout\Services;

use App\Core\Money\Piasters;
use App\Modules\Checkout\Models\DiscountCode;
use Illuminate\Validation\ValidationException;

/**
 * Discount code validation and integer-piaster calculation. FR-PAY-11, FR-CRT-05
 */
class DiscountService
{
    /**
     * Validate a discount code against subtotal and shipping fee, returning the calculated discount.
     *
     * @return array{discount_code: DiscountCode, discount_amount: int, free_shipping: bool}
     *
     * @throws ValidationException
     */
    public function apply(string $code, int $subtotal, int $shippingFee): array
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'discount_code' => 'Discount code is required.',
            ]);
        }

        /** @var DiscountCode|null $discount */
        $discount = DiscountCode::query()
            ->whereRaw('UPPER(code) = ?', [$normalized])
            ->first();

        if ($discount === null) {
            throw ValidationException::withMessages([
                'discount_code' => 'Invalid discount code.',
            ]);
        }

        if (! $discount->is_active) {
            throw ValidationException::withMessages([
                'discount_code' => 'This discount code is no longer active.',
            ]);
        }

        if ($discount->starts_at !== null && $discount->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'discount_code' => 'This discount code is not active yet.',
            ]);
        }

        if ($discount->expires_at !== null && $discount->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'discount_code' => 'This discount code has expired.',
            ]);
        }

        if ($discount->min_order_amount > 0 && $subtotal < $discount->min_order_amount) {
            throw ValidationException::withMessages([
                'discount_code' => 'Order subtotal does not meet the minimum amount for this discount code.',
            ]);
        }

        if ($discount->max_uses !== null && $discount->uses_count >= $discount->max_uses) {
            throw ValidationException::withMessages([
                'discount_code' => 'This discount code has reached its maximum number of uses.',
            ]);
        }

        $discountAmount = 0;
        $freeShipping = false;

        match ($discount->type) {
            DiscountCode::TYPE_PERCENTAGE => $discountAmount = min(
                max(0, $subtotal),
                max(0, Piasters::of($subtotal)->percent($discount->value * 100)->amount),
            ),
            DiscountCode::TYPE_FIXED => $discountAmount = min(
                max(0, $subtotal),
                max(0, $discount->value),
            ),
            DiscountCode::TYPE_FREE_SHIPPING => $freeShipping = true,
            default => throw ValidationException::withMessages([
                'discount_code' => 'Unsupported discount code type.',
            ]),
        };

        return [
            'discount_code' => $discount,
            'discount_amount' => $discountAmount,
            'free_shipping' => $freeShipping,
        ];
    }
}
