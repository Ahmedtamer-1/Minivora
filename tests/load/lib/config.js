import http from 'k6/http';
import { check } from 'k6';

/**
 * Shared configuration and helpers for Minivora k6 performance test suite.
 * Supports both STOREFRONT_MODE=subdomain (http://demo.localhost:8000)
 * and STOREFRONT_MODE=path (http://localhost:8000/s/demo).
 */
export const CONFIG = {
    scheme: __ENV.APP_SCHEME || 'http',
    domain: __ENV.APP_DOMAIN || 'localhost',
    port: __ENV.APP_PORT || '8000',
    storefrontMode: __ENV.STOREFRONT_MODE || 'subdomain', // 'subdomain' | 'path'
    defaultStore: __ENV.STORE_SLUG || 'demo',
    stores: (__ENV.STORE_SLUGS || 'demo,nile-crafts,cairo-leather').split(',').map((s) => s.trim()),
    defaultLocale: __ENV.LOCALE || 'en',
    productId: Number(__ENV.PRODUCT_ID || 1),
    ownerEmails: (__ENV.OWNER_EMAILS || 'owner0@minivora.test,owner1@minivora.test,owner2@minivora.test')
        .split(',')
        .map((s) => s.trim()),
    ownerPassword: __ENV.OWNER_PASSWORD || 'password',
};

function portSuffix() {
    return CONFIG.port ? `:${CONFIG.port}` : '';
}

/** Root platform / dashboard URL (e.g., http://localhost:8000). */
export function appBaseUrl() {
    return `${CONFIG.scheme}://${CONFIG.domain}${portSuffix()}`;
}

/** Storefront base URL for a given store subdomain. */
export function storeBaseUrl(storeSlug = CONFIG.defaultStore) {
    if (CONFIG.storefrontMode === 'path') {
        return `${appBaseUrl()}/s/${storeSlug}`;
    }
    return `${CONFIG.scheme}://${storeSlug}.${CONFIG.domain}${portSuffix()}`;
}

/** Pick a store deterministically per VU or randomly across seeded live stores. */
export function pickStore(vuId) {
    return CONFIG.stores[(vuId - 1) % CONFIG.stores.length];
}

/**
 * Extract Laravel CSRF token from either the XSRF-TOKEN cookie or an HTML meta/input tag.
 */
export function extractCsrfToken(res) {
    const xsrfCookie = res.cookies['XSRF-TOKEN'];
    if (xsrfCookie && xsrfCookie.length > 0 && xsrfCookie[0].value) {
        try {
            return decodeURIComponent(xsrfCookie[0].value);
        } catch (_) {
            return xsrfCookie[0].value;
        }
    }

    if (res.body) {
        const metaMatch = res.body.match(/<meta\s+name="csrf-token"\s+content="([^"]+)"/i);
        if (metaMatch) return metaMatch[1];

        const inputMatch = res.body.match(/name="_token"\s+value="([^"]+)"/i);
        if (inputMatch) return inputMatch[1];
    }

    return '';
}

/** Standard headers for JSON AJAX requests against Laravel storefront/dashboard. */
export function jsonHeaders(csrfToken, extra = {}) {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
        headers['X-XSRF-TOKEN'] = csrfToken;
    }
    return Object.assign(headers, extra);
}

/**
 * Verify NFR-SEC-17: Cart and checkout responses must never be publicly cacheable.
 */
export function assertNoPublicCache(res, label) {
    const cc = (res.headers['Cache-Control'] || res.headers['cache-control'] || '').toLowerCase();
    return check(res, {
        [`${label}: NFR-SEC-17 not publicly cached`]: () =>
            !cc.includes('public') && (cc.includes('no-store') || cc.includes('no-cache') || cc.includes('private')),
    });
}

/** Sample Arabic & English search queries (FR-CAT-05 Arabic normalization coverage). */
export const SEARCH_QUERIES = [
    { locale: 'en', q: 'bag' },
    { locale: 'en', q: 'leather' },
    { locale: 'en', q: 'mug' },
    { locale: 'ar', q: 'فخار' },
    { locale: 'ar', q: 'احذيه' }, // Tests alef/ta-marbuta normalization (FR-CAT-05)
    { locale: 'ar', q: 'جلد' },
];
