<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Marketing\Models\AbandonedCheckout;
use Illuminate\Database\Eloquent\Collection;

/**
 * Open abandoned checkout listing and localized WhatsApp recovery links. FR-MKT-01
 */
class AbandonedCartService
{
    /** @return Collection<int, AbandonedCheckout> */
    public function listOpen(): Collection
    {
        return AbandonedCheckout::query()
            ->open()
            ->latest('id')
            ->get();
    }

    public function whatsappRecoveryUrl(AbandonedCheckout $checkout, string $locale = 'ar'): string
    {
        $phone = $this->normalizePhone($checkout->phone);
        $name = trim((string) ($checkout->customer_name ?? ''));
        $amountEgp = number_format($checkout->subtotal / 100, 2, '.', '');

        if ($locale === 'en') {
            $greeting = $name !== '' ? "Hi {$name}," : 'Hi,';
            $message = "{$greeting} you left items worth {$amountEgp} EGP in your cart. Reply here if you'd like help completing your order!";
        } else {
            $greeting = $name !== '' ? "أهلاً {$name}،" : 'أهلاً بك،';
            $message = "{$greeting} تركت منتجات بقيمة {$amountEgp} جنيه في سلة التسوق. يسعدنا مساعدتك في إتمام طلبك الآن!";
        }

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '01')) {
            return '2'.$digits;
        }

        return $digits;
    }
}
