<?php

namespace App\Modules\Ledger\Services;

use App\Core\Money\Piasters;
use App\Modules\Ledger\Models\FinanceSetting;
use App\Modules\Ledger\Services\ChartOfAccounts as C;

/**
 * Business event → journal lines, exactly as in Documentation §5.12. Pure functions: no database,
 * so every rule is unit tested on its own (NFR-MNT: 100% coverage of ledger code).
 *
 * Revenue is recognised when the money is received: online and InstaPay payments when captured,
 * cash on delivery when the courier collects it. Prices in Egypt are shown VAT-inclusive, so a
 * VAT-registered store carves VAT out of the order total: VAT = total × r / (1 + r).
 */
final class PostingRules
{
    /** Where the money for an order lands, by payment method. */
    public static function receivingAccount(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'card', 'wallet' => C::CLEARING,  // Kashier / Paymob hold it until the payout
            'instapay' => C::BANK,
            default => C::CASH,               // cash on delivery
        };
    }

    /** VAT inside a VAT-inclusive amount, rounded to the piaster. */
    public static function vatInside(int $gross, int $rateBps): int
    {
        if ($gross <= 0 || $rateBps <= 0) {
            return 0;
        }

        return intdiv($gross * $rateBps * 2 + (10000 + $rateBps), 2 * (10000 + $rateBps));
    }

    /**
     * Order paid: Dr receiving account (total) and Dr Sales discounts; Cr Sales revenue (net of
     * VAT), Cr Shipping income, Cr VAT payable.
     *
     * @return list<array{code: string, debit?: int, credit?: int}>
     */
    public static function sale(int $subtotal, int $shipping, int $discount, int $total, string $paymentMethod, FinanceSetting $settings): array
    {
        $vat = $settings->vat_registered ? self::vatInside($total, $settings->vat_rate_bps) : 0;
        // VAT comes out of the item sales first, then out of shipping if the items alone are too small.
        $fromSales = min($vat, $subtotal);

        return [
            ['code' => self::receivingAccount($paymentMethod), 'debit' => $total],
            ['code' => C::DISCOUNTS, 'debit' => $discount],
            ['code' => C::SALES, 'credit' => $subtotal - $fromSales],
            ['code' => C::SHIPPING_INCOME, 'credit' => $shipping - ($vat - $fromSales)],
            ['code' => C::VAT_OUT, 'credit' => $vat],
        ];
    }

    /** Gateway fee on an online payment: percent of the amount plus a fixed fee per payment. */
    public static function gatewayFee(int $total, FinanceSetting $settings): int
    {
        if ($total <= 0) {
            return 0;
        }

        return min($total, Piasters::of($total)->percent($settings->gateway_fee_bps)->amount + $settings->gateway_fee_fixed);
    }

    /** @return list<array{code: string, debit?: int, credit?: int}> Dr Payment fees, Cr Gateway clearing */
    public static function fee(int $fee): array
    {
        return [
            ['code' => C::FEES, 'debit' => $fee],
            ['code' => C::CLEARING, 'credit' => $fee],
        ];
    }

    /** @return list<array{code: string, debit?: int, credit?: int}> Order shipped: Dr COGS, Cr Inventory */
    public static function costOfGoods(int $cost): array
    {
        return [
            ['code' => C::COGS, 'debit' => $cost],
            ['code' => C::INVENTORY, 'credit' => $cost],
        ];
    }

    /** @return list<array{code: string, debit?: int, credit?: int}> Returned items back on the shelf: Dr Inventory, Cr COGS */
    public static function restock(int $cost): array
    {
        return [
            ['code' => C::INVENTORY, 'debit' => $cost],
            ['code' => C::COGS, 'credit' => $cost],
        ];
    }

    /**
     * Refund issued: Dr Sales returns (net of VAT), Dr VAT payable (the VAT is no longer owed);
     * Cr the account the money originally came into.
     *
     * @return list<array{code: string, debit?: int, credit?: int}>
     */
    public static function refund(int $total, string $paymentMethod, FinanceSetting $settings): array
    {
        $vat = $settings->vat_registered ? self::vatInside($total, $settings->vat_rate_bps) : 0;

        return [
            ['code' => C::RETURNS, 'debit' => $total - $vat],
            ['code' => C::VAT_OUT, 'debit' => $vat],
            ['code' => self::receivingAccount($paymentMethod), 'credit' => $total],
        ];
    }

    /**
     * Manual expense: Dr expense account (net) and Dr VAT recoverable; Cr cash, bank, card or payable.
     *
     * @return list<array{code?: string, account_id?: int, debit?: int, credit?: int}>
     */
    public static function expense(int $expenseAccountId, int $amount, int $vat, string $paidFrom): array
    {
        return [
            ['account_id' => $expenseAccountId, 'debit' => $amount - $vat],
            ['code' => C::VAT_IN, 'debit' => $vat],
            ['code' => C::codeForMethod($paidFrom), 'credit' => $amount],
        ];
    }

    /** @return list<array{code: string, debit?: int, credit?: int}> Paying a bill later: Dr Accounts payable, Cr cash or bank */
    public static function billPayment(int $amount, string $paidWith): array
    {
        return [
            ['code' => C::PAYABLE, 'debit' => $amount],
            ['code' => C::codeForMethod($paidWith), 'credit' => $amount],
        ];
    }

    /** @return list<array{code: string, debit?: int, credit?: int}> Money moved between own accounts (e.g. gateway payout to bank) */
    public static function transfer(string $fromCode, string $toCode, int $amount): array
    {
        return [
            ['code' => $toCode, 'debit' => $amount],
            ['code' => $fromCode, 'credit' => $amount],
        ];
    }
}
