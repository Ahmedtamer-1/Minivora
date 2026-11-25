<?php

namespace App\Modules\Checkout\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Validated single-page checkout submission DTO. FR-PAY-01
 */
#[TypeScript]
class CheckoutData extends Data
{
    public const PAYMENT_METHODS = ['cod', 'kashier', 'instapay', 'paymob', 'fawry'];

    public function __construct(
        #[Max(255)]
        public string $name,
        #[Max(32)]
        public string $phone,
        #[Email, Max(255)]
        public ?string $email = null,
        #[Max(40)]
        public string $governorate = 'cairo',
        #[Max(500)]
        public string $address = '',
        #[In(self::PAYMENT_METHODS)]
        public string $payment_method = 'cod',
        #[Max(64)]
        public ?string $discount_code = null,
        #[Max(500)]
        public ?string $receipt_path = null,
        #[Min(0)]
        public int $cod_fee = 0,
        #[Min(0)]
        public ?int $free_shipping_threshold = null,
    ) {}
}
