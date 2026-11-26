import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { storeBaseUrl, pickStore, SEARCH_QUERIES, CONFIG } from './lib/config.js';

/**
 * Minivora Progressive Stress Test — Breaking Point & Recovery (TC-STRESS-001, TC-STRESS-003)
 *
 * WARNING: Run against a local or VPS staging instance, NOT Hostinger shared hosting.
 *
 * Purpose:
 *   Step virtual users well beyond the SRS Stage 1 target (50 VUs) up to 250 VUs, then
 *   step down to 20 VUs to verify:
 *     1. Where TTFB crosses the 800 ms NFR-PER-01 ceiling (latency knee).
 *     2. Maximum sustainable Requests Per Second (RPS).
 *     3. When rate-limiting (HTTP 429) or server errors (HTTP 5xx) begin.
 *     4. Whether the app and database recover cleanly once load subsides.
 *
 * Usage:
 *   k6 run tests/load/stress-storefront.js
 *   k6 run --summary-export=stress-summary.json tests/load/stress-storefront.js
 */
const ttfb = new Trend('stress_ttfb_ms', true);
const rateLimited = new Counter('stress_http_429_count');
const serverErrors = new Rate('stress_5xx_rate');

export const options = {
    stages: [
        { duration: '1m', target: 50 },   // Baseline (NFR-PER-01 target)
        { duration: '1m', target: 100 },  // 2x target
        { duration: '1m', target: 150 },  // 3x target
        { duration: '1m', target: 200 },  // 4x target
        { duration: '1m', target: 250 },  // 5x target (expected saturation / breaking point)
        { duration: '2m', target: 20 },   // Recovery stage
        { duration: '30s', target: 0 },   // Ramp-down
    ],
    thresholds: {
        // Stress test observes breaking point rather than strict 800 ms ceiling;
        // fail only if catastrophic 5xx rate (>10%) occurs across the entire run.
        stress_5xx_rate: ['rate<0.10'],
    },
};

export default function () {
    const storeSlug = pickStore(__VU);
    const store = storeBaseUrl(storeSlug);
    const locale = storeSlug === 'nile-crafts' ? 'ar' : CONFIG.defaultLocale;

    // 60% Home/Shop, 25% Search, 15% Cart JSON
    const roll = Math.random();
    let res;

    if (roll < 0.35) {
        res = http.get(`${store}/${locale}`, { tags: { endpoint: 'home', store: storeSlug } });
    } else if (roll < 0.60) {
        res = http.get(`${store}/${locale}/shop?sort=price_asc`, { tags: { endpoint: 'shop', store: storeSlug } });
    } else if (roll < 0.85) {
        const q = SEARCH_QUERIES[Math.floor(Math.random() * SEARCH_QUERIES.length)];
        res = http.get(`${store}/${q.locale}/search?q=${encodeURIComponent(q.q)}`, {
            tags: { endpoint: 'search', store: storeSlug },
        });
    } else {
        res = http.get(`${store}/cart`, {
            headers: { Accept: 'application/json' },
            tags: { endpoint: 'cart', store: storeSlug },
        });
    }

    ttfb.add(res.timings.waiting);
    if (res.status === 429) {
        rateLimited.add(1);
    }
    serverErrors.add(res.status >= 500);

    check(res, {
        'status is 200 or graceful 429': (r) => r.status === 200 || r.status === 429,
        'no 5xx server crash': (r) => r.status < 500,
    });

    sleep(Math.random() * 0.5 + 0.2);
}
