<?php

use App\Modules\Platform\Models\Store;

test('NFR-USE-03 FR-PAY-01 storefront checkout renders at most 8 visible shopper fields in AR and EN', function (string $locale, string $expectedDir) {
    $store = Store::factory()->create([
        'subdomain' => 'checkoutui-'.$locale,
        'published_at' => now(),
    ]);

    $host = $store->subdomain.'.'.config('minivora.domain');
    $response = $this->get("http://{$host}/{$locale}/checkout");

    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toContain('dir="'.$expectedDir.'"')
        ->toContain('lang="'.$locale.'"');

    // Count visible shopper controls inside the checkout form (exclude hidden _token).
    preg_match('/<form[^>]*action="\/checkout"[^>]*>(.*?)<\/form>/s', $html, $formMatch);
    expect($formMatch)->not->toBeEmpty();

    preg_match_all('/<(?:input(?![^>]*type="hidden")|select|textarea)\b[^>]*>/i', $formMatch[1], $controls);
    expect(count($controls[0]))->toBeLessThanOrEqual(8)
        ->and(count($controls[0]))->toBeGreaterThanOrEqual(5);
})->with([
    'Arabic RTL' => ['ar', 'rtl'],
    'English LTR' => ['en', 'ltr'],
]);
