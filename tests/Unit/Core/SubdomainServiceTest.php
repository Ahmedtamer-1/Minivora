<?php

use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Services\SubdomainService;

test('FR-ONB-02 names become valid addresses', function (string $input, string $expected) {
    expect(app(SubdomainService::class)->normalize($input))->toBe($expected);
})->with([
    ['Brown Tote', 'brown-tote'],
    ['  Adorna EG!! ', 'adorna-eg'],
    ['Café & Co', 'cafe-co'],
    ['UPPER--case', 'upper-case'],
]);

test('FR-ONB-02 reserved names are never available', function (string $name) {
    expect(app(SubdomainService::class)->status($name))->toBe(SubdomainService::RESERVED);
})->with(['app', 'api', 'www', 'mail', 'admin', 'status']);

test('FR-ONB-02 suggestions are available addresses', function () {
    Store::factory()->create(['subdomain' => 'nile']);
    Store::factory()->create(['subdomain' => 'nile-eg']);
    $service = app(SubdomainService::class);

    $suggestions = $service->suggest('nile');

    expect($suggestions)->toHaveCount(3)->not->toContain('nile', 'nile-eg');
    foreach ($suggestions as $s) {
        expect($service->isAvailable($s))->toBeTrue();
    }
});

test('FR-ONB-02 format rules', function (string $name, bool $valid) {
    expect(app(SubdomainService::class)->isValidFormat($name))->toBe($valid);
})->with([
    ['ab', false],
    ['abc', true],
    ['-abc', false],
    ['abc-', false],
    ['a--b', false],
    ['shop-2026', true],
    [str_repeat('a', 41), false],
]);
