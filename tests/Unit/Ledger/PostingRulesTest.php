<?php

use App\Modules\Ledger\Models\FinanceSetting;
use App\Modules\Ledger\Services\ChartOfAccounts as C;
use App\Modules\Ledger\Services\PostingRules;

// FR-LED posting rules (Documentation §5.12). Pure functions: no database.

function settings(bool $vat = false): FinanceSetting
{
    return new FinanceSetting(['vat_registered' => $vat, 'vat_rate_bps' => 1400, 'gateway_fee_bps' => 275, 'gateway_fee_fixed' => 300]);
}

/** @param  list<array<string, mixed>>  $lines */
function sides(array $lines): array
{
    return [array_sum(array_map(fn ($l) => $l['debit'] ?? 0, $lines)), array_sum(array_map(fn ($l) => $l['credit'] ?? 0, $lines))];
}

function line(array $lines, string $code): array
{
    return array_values(array_filter($lines, fn ($l) => ($l['code'] ?? null) === $code))[0] ?? [];
}

test('online payment debits gateway clearing, COD debits cash, InstaPay debits bank', function () {
    expect(PostingRules::receivingAccount('card'))->toBe(C::CLEARING)
        ->and(PostingRules::receivingAccount('wallet'))->toBe(C::CLEARING)
        ->and(PostingRules::receivingAccount('instapay'))->toBe(C::BANK)
        ->and(PostingRules::receivingAccount('cod'))->toBe(C::CASH);
});

test('a sale balances and splits items, shipping and discount', function () {
    $lines = PostingRules::sale(170000, 6000, 10000, 166000, 'cod', settings());
    [$d, $c] = sides($lines);

    expect($d)->toBe($c)->toBe(176000)
        ->and(line($lines, C::CASH)['debit'])->toBe(166000)
        ->and(line($lines, C::DISCOUNTS)['debit'])->toBe(10000)
        ->and(line($lines, C::SALES)['credit'])->toBe(170000)
        ->and(line($lines, C::SHIPPING_INCOME)['credit'])->toBe(6000)
        ->and(line($lines, C::VAT_OUT)['credit'])->toBe(0);
});

test('a VAT-registered sale carves 14% VAT out of the inclusive total', function () {
    $lines = PostingRules::sale(114000, 0, 0, 114000, 'card', settings(true));
    [$d, $c] = sides($lines);

    expect(PostingRules::vatInside(114000, 1400))->toBe(14000)
        ->and($d)->toBe($c)
        ->and(line($lines, C::VAT_OUT)['credit'])->toBe(14000)
        ->and(line($lines, C::SALES)['credit'])->toBe(100000);
});

test('VAT larger than the item subtotal comes out of shipping, never below zero', function () {
    $lines = PostingRules::sale(1000, 50000, 0, 51000, 'cod', settings(true));
    [$d, $c] = sides($lines);

    expect($d)->toBe($c)
        ->and(line($lines, C::SALES)['credit'])->toBe(0)
        ->and(line($lines, C::SHIPPING_INCOME)['credit'])->toBeGreaterThanOrEqual(0);
});

test('VAT rounding is half up and zero for zero amounts', function () {
    expect(PostingRules::vatInside(0, 1400))->toBe(0)
        ->and(PostingRules::vatInside(100, 0))->toBe(0)
        ->and(PostingRules::vatInside(1, 1400))->toBe(0)
        ->and(PostingRules::vatInside(57, 1400))->toBe(7);
});

test('gateway fee is percent plus fixed, never more than the payment', function () {
    expect(PostingRules::gatewayFee(100000, settings()))->toBe(2750 + 300)
        ->and(PostingRules::gatewayFee(100, settings()))->toBe(100)
        ->and(PostingRules::gatewayFee(0, settings()))->toBe(0);
    expect(sides(PostingRules::fee(3050)))->toBe([3050, 3050]);
});

test('cost of goods, restock, refund, expense, bill payment and transfer all balance', function () {
    foreach ([
        PostingRules::costOfGoods(60000),
        PostingRules::restock(60000),
        PostingRules::refund(114000, 'card', settings(true)),
        PostingRules::refund(50000, 'cod', settings()),
        PostingRules::expense(9, 11400, 1400, 'bank'),
        PostingRules::expense(9, 5000, 0, 'payable'),
        PostingRules::billPayment(5000, 'cash'),
        PostingRules::transfer(C::CLEARING, C::BANK, 90000),
    ] as $lines) {
        [$d, $c] = sides($lines);
        expect($d)->toBe($c)->toBeGreaterThan(0);
    }

    expect(line(PostingRules::refund(114000, 'card', settings(true)), C::VAT_OUT)['debit'])->toBe(14000)
        ->and(line(PostingRules::expense(9, 5000, 0, 'payable'), C::PAYABLE)['credit'])->toBe(5000)
        ->and(line(PostingRules::expense(9, 5000, 0, 'card'), C::CARD_PAYABLE)['credit'])->toBe(5000);
});

test('an unknown payment method is refused', function () {
    PostingRules::billPayment(100, 'cheque');
})->throws(InvalidArgumentException::class);
