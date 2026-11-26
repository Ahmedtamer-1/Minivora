import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { storeBaseUrl, CONFIG } from './lib/config.js';

/**
 * Minivora Spike Test — Flash-Sale / Influencer Drop Scenario (TC-SPIKE-001)
 *
 * Purpose:
 *   Simulate an abrupt 15x traffic burst (10 VUs -> 150 VUs in 10 seconds) targeting a single
 *   storefront (e.g., an Instagram/TikTok story drop for Adorna EG or demo store) and verify
 *   cache resilience, zero crashes, and fast post-spike latency recovery.
 *
 * Usage:
 *   k6 run tests/load/spike-flash-sale.js
 */
const spikeTtfb = new Trend('spike_ttfb_ms', true);
const spike5xx = new Rate('spike_5xx_rate');

export const options = {
    stages: [
        { duration: '30s', target: 10 },                                // Normal baseline
        { duration: '10s', target: Number(__ENV.SPIKE_VUS || 150) },    // Sudden 15x burst
        { duration: '1m', target: Number(__ENV.SPIKE_VUS || 150) },     // Hold peak flash sale
        { duration: '10s', target: 10 },                                // Abrupt drop-off
        { duration: '1m', target: 10 },                                 // Verify post-spike recovery
        { duration: '15s', target: 0 },                                 // End
    ],
    thresholds: {
        spike_5xx_rate: ['rate<0.05'],
        http_req_duration: ['p(95)<2000'],
        checks: ['rate>0.95'],
    },
};

export default function () {
    const store = storeBaseUrl(CONFIG.defaultStore);
    const locale = CONFIG.defaultLocale;

    const home = http.get(`${store}/${locale}`, { tags: { page: 'flash_sale_home' } });
    spikeTtfb.add(home.timings.waiting);
    spike5xx.add(home.status >= 500);
    check(home, { 'flash sale home 200': (r) => r.status === 200 });

    sleep(0.3);

    const shop = http.get(`${store}/${locale}/shop?in_stock=1&sort=best_selling`, {
        tags: { page: 'flash_sale_shop' },
    });
    spikeTtfb.add(shop.timings.waiting);
    spike5xx.add(shop.status >= 500);
    check(shop, { 'flash sale shop 200': (r) => r.status === 200 });

    sleep(Math.random() * 0.8 + 0.2);
}
