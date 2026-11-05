<?php

use App\Core\Money\MoneyFormatter;
use App\Core\Money\Piasters;

test('money is integer piasters, parsed without floats', function () {
    expect(Piasters::fromPounds('8,059.97')->amount)->toBe(805997)
        ->and(Piasters::fromPounds('99')->amount)->toBe(9900)
        ->and(Piasters::fromPounds('0.5')->amount)->toBe(50)
        ->and(Piasters::fromPounds('-12.05')->amount)->toBe(-1205)
        ->and(Piasters::of(10)->plus(Piasters::of(5))->minus(Piasters::of(3))->times(2)->amount)->toBe(24)
        ->and(Piasters::of(1999)->percent(1000)->amount)->toBe(200); // 10% of 19.99 rounds half up
});

test('invalid amounts are rejected', function () {
    Piasters::fromPounds('12.345');
})->throws(InvalidArgumentException::class);

test('NFR-USE-05 amounts show as EGP with Western digits in both languages', function () {
    expect(MoneyFormatter::format(805997))->toBe('8,059.97 EGP')
        ->and(MoneyFormatter::format(Piasters::of(-5), 'ar'))->toBe('-0.05 ج.م');
});
