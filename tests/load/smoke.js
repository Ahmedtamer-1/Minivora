import http from 'k6/http';
import { check, sleep } from 'k6';
import { appBaseUrl, storeBaseUrl, assertNoPublicCache, CONFIG } from './lib/config.js';

/**
 * Minivora Smoke Test (TC-PERF-001)
 * Purpose: Fast 1-VU sanity check verifying core storefront, SEO, cart, checkout, and API endpoints
 *          respond cleanly before executing heavier Load, Stress, Spike, or Soak tests.
 *
 * Usage:
 *   k6 run tests/load/smoke.js
 *   STOREFRONT_MODE=path k6 run tests/load/smoke.js
 */
export const options = {
    vus: 1,
    duration: __ENV.SMOKE_DURATION || '30s',
    thresholds: {
        http_req_failed: ['rate==0'],
        http_req_duration: ['p(95)<1000'],
        checks: ['rate==1.0'],
    },
};

export default function () {
    const store = storeBaseUrl(CONFIG.defaultStore);
    const app = appBaseUrl();
    const locale = CONFIG.defaultLocale;

    // 1. Platform landing & subdomain availability check (FR-ONB-02)
    const landing = http.get(`${app}/`, { tags: { endpoint: 'landing' } });
    check(landing, { 'landing returns 200': (r) => r.status === 200 });

    const subCheck = http.get(`${app}/api/v1/subdomains/check?subdomain=smoke-check-shop`, {
        headers: { Accept: 'application/json' },
        tags: { endpoint: 'api_subdomain_check' },
    });
    check(subCheck, { 'subdomain check returns 200': (r) => r.status === 200 });

    // 2. Storefront home, shop, search (EN & AR)
    const home = http.get(`${store}/${locale}`, { tags: { endpoint: 'storefront_home' } });
    check(home, { 'storefront home returns 200': (r) => r.status === 200 });

    const shop = http.get(`${store}/${locale}/shop?sort=newest`, { tags: { endpoint: 'storefront_shop' } });
    check(shop, { 'storefront shop returns 200': (r) => r.status === 200 });

    const search = http.get(`${store}/${locale}/search?q=bag`, { tags: { endpoint: 'storefront_search' } });
    check(search, { 'storefront search returns 200': (r) => r.status === 200 });

    // 3. SEO endpoints (FR-SEO-05, FR-SEO-06)
    const sitemap = http.get(`${store}/sitemap.xml`, { tags: { endpoint: 'seo_sitemap' } });
    check(sitemap, { 'sitemap.xml returns 200': (r) => r.status === 200 });

    const robots = http.get(`${store}/robots.txt`, { tags: { endpoint: 'seo_robots' } });
    check(robots, { 'robots.txt returns 200': (r) => r.status === 200 });

    // 4. Cart & Checkout pages (NFR-SEC-17 no-cache verification)
    const cart = http.get(`${store}/cart`, {
        headers: { Accept: 'application/json' },
        tags: { endpoint: 'cart_show' },
    });
    check(cart, { 'cart JSON returns 200': (r) => r.status === 200 });
    assertNoPublicCache(cart, 'cart');

    const checkout = http.get(`${store}/${locale}/checkout`, { tags: { endpoint: 'checkout_show' } });
    check(checkout, { 'checkout page returns 200': (r) => r.status === 200 });
    assertNoPublicCache(checkout, 'checkout');

    sleep(1);
}
