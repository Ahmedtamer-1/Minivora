import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';
import { appBaseUrl, extractCsrfToken, csrfHeaders, CONFIG } from './lib/config.js';

/**
 * Minivora Authenticated Owner Dashboard & API Load Test — SRS v1.8 NFR-PER-04 (TC-PERF-003)
 *
 * Requirement (NFR-PER-04):
 *   "Dashboard pages (excluding AI answers) shall load their data within 1 s at the 95th
 *    percentile for a seeded store."
 *
 * Profile:
 *   Authenticates as seeded store owners (owner0..2@minivora.test) and loads core Inertia
 *   dashboard pages: /dashboard, /products, /orders, /analytics, /finance, /finance/reports.
 *
 * Usage:
 *   k6 run tests/load/load-dashboard-and-api.js
 */
const dashboardDuration = new Trend('minivora_dashboard_duration_ms', true);
const dashboardErrors = new Rate('minivora_dashboard_error_rate');

export const options = {
    // k6 empties each VU's cookie jar at the start of every iteration by default, which would
    // drop the owner session after the first pass through the dashboard pages.
    noCookiesReset: true,
    stages: [
        { duration: '30s', target: Number(__ENV.DASHBOARD_VUS || 15) },
        { duration: '2m', target: Number(__ENV.DASHBOARD_VUS || 15) },
        { duration: '30s', target: 0 },
    ],
    thresholds: {
        // SRS NFR-PER-04: Dashboard pages <= 1000 ms at p95
        minivora_dashboard_duration_ms: ['p(95)<1000'],
        minivora_dashboard_error_rate: ['rate<0.01'],
        checks: ['rate>0.99'],
    },
};

// Each seeded owner logs in once, in setup(), and the virtual users reuse that session.
// All k6 traffic comes from one IP, and Laravel's throttle:N,1 counts every throttled route
// against the same per-IP key for guests. Logging in per VU while other VUs call the throttled
// subdomain API pushed POST /login past its 20/min limit and produced 429s that real owners,
// each on their own IP, would never see. Login time is not part of NFR-PER-04 either.
let isLoggedIn = false;

function loginOwner(email) {
    const app = appBaseUrl();
    const jar = http.cookieJar();
    jar.clear(app);

    const loginPage = http.get(`${app}/login`, { tags: { endpoint: 'login_form' } });
    const csrf = extractCsrfToken(loginPage);

    const loginRes = http.post(
        `${app}/login`,
        {
            email,
            password: CONFIG.ownerPassword,
        },
        {
            headers: csrfHeaders(csrf),
            tags: { endpoint: 'login_submit' },
            redirects: 5,
        }
    );

    const ok = check(loginRes, {
        'owner login succeeded': (r) => r.status === 200 && !r.url.endsWith('/login'),
    });
    if (!ok) return null;

    const cookies = {};
    const all = jar.cookiesForURL(app);
    for (const name of Object.keys(all)) cookies[name] = all[name][0];
    return cookies;
}

export function setup() {
    const sessions = {};
    for (const email of CONFIG.ownerEmails) {
        const cookies = loginOwner(email);
        if (cookies) sessions[email] = cookies;
    }
    return { sessions };
}

export default function (data) {
    const app = appBaseUrl();

    if (!isLoggedIn) {
        const email = CONFIG.ownerEmails[(__VU - 1) % CONFIG.ownerEmails.length];
        const cookies = data.sessions[email];
        const ok = check(cookies, { 'owner session available': (c) => !!c });
        if (!ok) {
            dashboardErrors.add(1);
            sleep(2);
            return;
        }
        const jar = http.cookieJar();
        for (const name of Object.keys(cookies)) jar.set(app, name, cookies[name]);
        isLoggedIn = true;
    }

    const pages = [
        { path: '/dashboard', name: 'dashboard_home' },
        { path: '/products', name: 'dashboard_products' },
        { path: '/orders', name: 'dashboard_orders' },
        { path: '/analytics', name: 'dashboard_analytics' },
        { path: '/finance', name: 'dashboard_finance' },
        { path: '/finance/reports', name: 'dashboard_finance_reports' },
    ];

    group('Authenticated_Dashboard_Pages', () => {
        for (const page of pages) {
            const res = http.get(`${app}${page.path}`, {
                tags: { endpoint: page.name },
            });
            dashboardDuration.add(res.timings.duration);
            const signedOut = res.url.endsWith('/login');
            dashboardErrors.add(res.status >= 400 || signedOut);
            check(res, {
                [`${page.name} returns 200`]: (r) => r.status === 200 && !signedOut,
            });
            sleep(Math.random() * 1.0 + 0.5);
        }
    });

    // Also test public subdomain check API under concurrent dashboard load
    const sub = `test-shop-${__VU}-${__ITER}`;
    const apiRes = http.get(`${app}/api/v1/subdomains/check?name=${sub}`, {
        headers: { Accept: 'application/json' },
        tags: { endpoint: 'api_subdomain_check' },
    });
    check(apiRes, { 'subdomain API 200 or 429': (r) => r.status === 200 || r.status === 429 });

    sleep(1);
}
