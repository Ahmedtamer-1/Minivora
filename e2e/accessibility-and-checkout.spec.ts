// Playwright E2E spec: keyboard-only navigation, RTL/LTR direction, <= 8 checkout fields,
// and explicit <label for="..."> pairing on /ar/checkout and /en/checkout (V-02, V-12, NFR-USE-04, NFR-USE-05).
import { expect, test } from '@playwright/test';

const STORE_BASE_URL = (process.env.E2E_STORE_URL ?? 'http://demo.localhost:8000').replace(/\/+$/, '');

const EXPECTED_CONTROL_ORDER = [
    'checkout-name',
    'checkout-phone',
    'checkout-email',
    'checkout-governorate',
    'checkout-address',
    'checkout-payment-method',
    'checkout-discount',
];

for (const { locale, dir } of [
    { locale: 'ar', dir: 'rtl' },
    { locale: 'en', dir: 'ltr' },
]) {
    test.describe(`Checkout accessibility and structure (${locale.toUpperCase()} / ${dir})`, () => {
        test(`V-02 V-12 NFR-USE-04 NFR-USE-05 verifies dir="${dir}", <= 8 fields, matching labels, and Tab order on /${locale}/checkout`, async ({ page }) => {
            await page.goto(`${STORE_BASE_URL}/${locale}/checkout`, { waitUntil: 'networkidle' });

            // 1. Verify document locale and reading direction.
            const html = page.locator('html');
            await expect(html).toHaveAttribute('lang', locale);
            await expect(html).toHaveAttribute('dir', dir);

            // 2. Verify checkout form has <= 8 visible form controls (NFR-USE-03 / NFR-USE-05).
            const form = page.locator('form[action="/checkout"]');
            await expect(form).toBeVisible();

            const visibleControls = form.locator('input:not([type="hidden"]), select, textarea');
            const controlCount = await visibleControls.count();
            expect(controlCount).toBeGreaterThan(0);
            expect(controlCount).toBeLessThanOrEqual(8);

            // 3. Verify every visible form control has an id and a matching <label for="..."> with text.
            for (let i = 0; i < controlCount; i += 1) {
                const control = visibleControls.nth(i);
                const id = await control.getAttribute('id');
                expect(id, `Form control #${i} must have an id`).toBeTruthy();

                const label = form.locator(`label[for="${id}"]`);
                await expect(label, `Missing <label for="${id}">`).toHaveCount(1);
                await expect(label).toBeVisible();
                const labelText = (await label.innerText()).trim();
                expect(labelText.length, `Label for #${id} must not be empty`).toBeGreaterThan(0);
            }

            // 4. Verify keyboard-only Tab navigation visits every checkout control in DOM order and reaches submit.
            await page.locator(`#${EXPECTED_CONTROL_ORDER[0]}`).focus();

            const visitedIds: string[] = [];
            const firstFocusedId = await page.evaluate(() => document.activeElement?.id ?? '');
            visitedIds.push(firstFocusedId);

            for (let step = 1; step < EXPECTED_CONTROL_ORDER.length; step += 1) {
                await page.keyboard.press('Tab');
                const activeId = await page.evaluate(() => document.activeElement?.id ?? '');
                visitedIds.push(activeId);
            }

            expect(visitedIds).toEqual(EXPECTED_CONTROL_ORDER);

            // Next Tab after the final input reaches the submit button.
            await page.keyboard.press('Tab');
            const submitFocused = await page.evaluate(() => {
                const el = document.activeElement;
                return el?.tagName === 'BUTTON' && el.getAttribute('type') === 'submit';
            });
            expect(submitFocused).toBe(true);
        });
    });
}
