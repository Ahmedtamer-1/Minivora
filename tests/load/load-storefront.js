import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';
import {
    storeBaseUrl,
    pickStore,
    extractCsrfToken,
    jsonHeaders,
    assertNoPublicCache,
    SEARCH_QUERIES,
    CONFIG,
} from './lib/config.js';

/**
 * Minivora Official Load Test — SRS v1.8 V-10 / NFR-PER-01 (TC-PERF-002)
 *
 * Requirement (NFR-PER-01):
 *   "Storefront pages shall return their first byte within 800 ms at the 95th percentile
 *    with 50 concurrent shoppers, using page caching at the server and the CDN."
 *
 * Profile:
 *   - 1m ramp-up  : 0 -> 50 VUs
 *   - 3m steady   : 50 concurrent virtual shoppers
 *   - 1m ramp-down: 50 -> 0 VUs
 *   Total duration: 5 minutes (matches SRS V-10)
 *
 * Usage:
 *   k6 run tests/load/load-storefront.js
 *   k6 run --out json=load-results.json tests/load/load-storefront.js
 */
const storefrontTtfb = new Trend('minivora_storefront_ttfb_ms', true);
const searchLatency = new Trend('minivora_search_duration_ms', true);
const serverErrors = new Rate('minivora_5xx_rate');

export const options = {
    stages: [
        { duration: __ENV.RAMP_UP || '1m', target: Number(__ENV.TARGET_VUS || 50) },
        { duration: __ENV.STEADY || '3m', target: Number(__ENV.TARGET_VUS || 50) },
        { duration: __ENV.RAMP_DOWN || '1m', target: 0 },
    ],
    thresholds: {
        // SRS NFR-PER-01: Storefront TTFB p95 <= 800 ms
        'http_req_waiting{group:::01_Storefront_Browse}': ['p(95)<800'],
        minivora_storefront_ttfb_ms: ['p(95)<800'],
        // SRS NFR-PER-05: Search suggestions <= 500 ms (p95 budget)
        minivora_search_duration_ms: ['p(95)<500'],
        // Overall reliability under 50 VUs
        minivora_5xx_rate: ['rate<0.01'],
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
    },
};

export default function () {
    const storeSlug = pickStore(__VU);
    const store = storeBaseUrl(storeSlug);
    const locale = storeSlug === 'nile-crafts' ? 'ar' : CONFIG.defaultLocale;
    let csrf = '';

    // 1. Browse Storefront Home & Shop (weighted toward read traffic like real shoppers)
    group('01_Storefront_Browse', () => {
        const homeRes = http.get(`${store}/${locale}`, { tags: { page: 'home', store: storeSlug } });
        storefrontTtfb.add(homeRes.timings.waiting);
        serverErrors.add(homeRes.status >= 500);
        check(homeRes, { 'home status 200': (r) => r.status === 200 });
        csrf = extractCsrfToken(homeRes);

        sleep(Math.random() * 1.5 + 0.5);

        const shopRes = http.get(`${store}/${locale}/shop?sort=newest&page=1`, {
            tags: { page: 'shop', store: storeSlug },
        });
        storefrontTtfb.add(shopRes.timings.waiting);
        serverErrors.add(shopRes.status >= 500);
        check(shopRes, { 'shop status 200': (r) => r.status === 200 });

        sleep(Math.random() * 1.5 + 0.5);
    });

    // 2. Bilingual Search (FR-CAT-05 / NFR-PER-05)
    group('02_Storefront_Search', () => {
        const sample = SEARCH_QUERIES[Math.floor(Math.random() * SEARCH_QUERIES.length)];
        const searchRes = http.get(`${store}/${sample.locale}/search?q=${encodeURIComponent(sample.q)}`, {
            tags: { page: 'search', locale: sample.locale },
        });
        searchLatency.add(searchRes.timings.duration);
        serverErrors.add(searchRes.status >= 500);
        check(searchRes, { 'search status 200': (r) => r.status === 200 });

        sleep(Math.random() * 1.0 + 0.5);
    });

    // 3. Cart & Checkout Page Check (30% of shoppers inspect cart/checkout, verifying NFR-SEC-17)
    if (Math.random() < 0.3) {
        group('03_Cart_And_Checkout_View', () => {
            const cartRes = http.get(`${store}/cart`, {
                headers: { Accept: 'application/json' },
                tags: { page: 'cart_json' },
            });
            serverErrors.add(cartRes.status >= 500);
            check(cartRes, { 'cart JSON status 200': (r) => r.status === 200 });
            assertNoPublicCache(cartRes, 'load_cart');

            const checkoutRes = http.get(`${store}/${locale}/checkout`, {
                tags: { page: 'checkout' },
            });
            serverErrors.add(checkoutRes.status >= 500);
            check(checkoutRes, { 'checkout page status 200': (r) => r.status === 200 });
            assertNoPublicCache(checkoutRes, 'load_checkout');

            // Optional lead capture (10% of checkout viewers; respects throttle:30,1)
            if (Math.random() < 0.1 && csrf) {
                const phone = `010${String(10000000 + ((__VU * 1000 + __ITER) % 89999999))}`;
                const leadRes = http.post(
                    `${store}/checkout/lead`,
                    JSON.stringify({ phone, name: `Shopper ${__VU}`, governorate: 'cairo' }),
                    { headers: jsonHeaders(csrf), tags: { page: 'checkout_lead' } }
                );
                serverErrors.add(leadRes.status >= 500);
                check(leadRes, { 'lead capture 201 or 429': (r) => r.status === 201 || r.status === 429 });
            }
        });
    }

    // 4. Periodic SEO crawler traffic (5% of iterations)
    if (Math.random() < 0.05) {
        group('04_SEO_Feeds', () => {
            const sitemapRes = http.get(`${store}/sitemap.xml`, { tags: { page: 'sitemap' } });
            serverErrors.add(sitemapRes.status >= 500);
            check(sitemapRes, { 'sitemap status 200': (r) => r.status === 200 });
        });
    }

    sleep(Math.random() * 2 + 1);
}
