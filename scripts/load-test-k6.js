// k6 load test for Minivora storefront (V-10, NFR-PER-01..07).
// Usage: k6 run -e BASE_URL=http://demo.localhost:8000 scripts/load-test-k6.js

import http from 'k6/http';
import { check, group, sleep } from 'k6';

const BASE_URL = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/+$/, '');

export const options = {
    vus: 50,
    duration: '5m',
    thresholds: {
        // SRS §8.1 performance & reliability targets (V-10, NFR-PER-01..07)
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<800'],
    },
};

export default function () {
    group('Storefront Arabic home (/ar)', () => {
        const res = http.get(`${BASE_URL}/ar`, {
            headers: { Accept: 'text/html' },
            tags: { endpoint: 'storefront_home_ar' },
        });

        check(res, {
            'GET /ar returns 200': (r) => r.status === 200,
            'GET /ar renders RTL html': (r) => r.body && r.body.includes('dir="rtl"'),
        });
    });

    sleep(0.5);

    group('Single-page guest checkout (/ar/checkout)', () => {
        const res = http.get(`${BASE_URL}/ar/checkout`, {
            headers: { Accept: 'text/html' },
            tags: { endpoint: 'storefront_checkout_ar' },
        });

        check(res, {
            'GET /ar/checkout returns 200': (r) => r.status === 200,
            'GET /ar/checkout contains form': (r) => r.body && r.body.includes('checkout-name'),
        });
    });

    sleep(0.5);

    group('SEO endpoints (/sitemap.xml and /robots.txt)', () => {
        const sitemap = http.get(`${BASE_URL}/sitemap.xml`, {
            headers: { Accept: 'application/xml' },
            tags: { endpoint: 'seo_sitemap' },
        });

        check(sitemap, {
            'GET /sitemap.xml returns 200': (r) => r.status === 200,
            'GET /sitemap.xml is XML': (r) => r.body && r.body.includes('<urlset'),
        });

        const robots = http.get(`${BASE_URL}/robots.txt`, {
            headers: { Accept: 'text/plain' },
            tags: { endpoint: 'seo_robots' },
        });

        check(robots, {
            'GET /robots.txt returns 200': (r) => r.status === 200,
            'GET /robots.txt includes User-agent': (r) => r.body && r.body.includes('User-agent'),
        });
    });

    sleep(1);
}
