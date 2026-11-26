import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { storeBaseUrl, pickStore, SEARCH_QUERIES, CONFIG } from './lib/config.js';

/**
 * Minivora Soak / Endurance Test (TC-SOAK-001)
 *
 * Purpose:
 *   Hold a sustained moderate load (30 VUs across multiple stores) for 30 minutes (or longer)
 *   to detect memory leaks, session table bloat, DB connection leaks, or gradual latency drift.
 *
 * Usage:
 *   k6 run tests/load/soak-endurance.js
 *   SOAK_DURATION=10m k6 run tests/load/soak-endurance.js
 */
const soakTtfb = new Trend('soak_ttfb_ms', true);
const soakErrors = new Rate('soak_error_rate');

export const options = {
    stages: [
        { duration: '2m', target: Number(__ENV.SOAK_VUS || 30) },
        { duration: __ENV.SOAK_DURATION || '30m', target: Number(__ENV.SOAK_VUS || 30) },
        { duration: '2m', target: 0 },
    ],
    thresholds: {
        soak_ttfb_ms: ['p(95)<800'],
        soak_error_rate: ['rate<0.01'],
        checks: ['rate>0.99'],
    },
};

export default function () {
    const storeSlug = pickStore(__VU);
    const store = storeBaseUrl(storeSlug);
    const locale = storeSlug === 'nile-crafts' ? 'ar' : CONFIG.defaultLocale;

    const home = http.get(`${store}/${locale}`, { tags: { page: 'soak_home', store: storeSlug } });
    soakTtfb.add(home.timings.waiting);
    soakErrors.add(home.status >= 400);
    check(home, { 'soak home 200': (r) => r.status === 200 });

    sleep(1);

    const shop = http.get(`${store}/${locale}/shop?page=${(__ITER % 3) + 1}`, {
        tags: { page: 'soak_shop', store: storeSlug },
    });
    soakTtfb.add(shop.timings.waiting);
    soakErrors.add(shop.status >= 400);
    check(shop, { 'soak shop 200': (r) => r.status === 200 });

    sleep(1);

    const q = SEARCH_QUERIES[__ITER % SEARCH_QUERIES.length];
    const search = http.get(`${store}/${q.locale}/search?q=${encodeURIComponent(q.q)}`, {
        tags: { page: 'soak_search', store: storeSlug },
    });
    soakTtfb.add(search.timings.waiting);
    soakErrors.add(search.status >= 400);
    check(search, { 'soak search 200': (r) => r.status === 200 });

    sleep(Math.random() * 2 + 1);
}
