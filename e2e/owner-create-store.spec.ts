// Owner creates a store in one step and lands in its dashboard. FR-ONB-01, FR-ONB-02, FR-ONB-03, FR-ONB-06
import { expect, test } from '@playwright/test';

test('FR-ONB-03 owner creates a store and reaches its dashboard', async ({ page, browser }) => {
    const name = `e2e${Date.now().toString(36)}`;

    await page.goto('/locale/en');
    await page.goto('/');
    await page.getByLabel('Store name').fill(`E2E ${name}`);
    await expect(page.getByText('Available')).toBeVisible();

    // A taken address offers suggestions.
    await page.getByLabel('Store address').fill('demo');
    await expect(page.getByText(/Taken/)).toBeVisible();
    await page.getByLabel('Store address').fill(name);
    await expect(page.getByText('Available')).toBeVisible();

    await page.getByLabel('Email').fill(`${name}@example.com`);
    await page.getByLabel('Password').fill('secret-pass-1');

    const started = Date.now();
    await page.getByRole('button', { name: 'Create my store' }).click();
    await page.waitForURL('**/dashboard');
    expect(Date.now() - started).toBeLessThan(60_000);

    // The Today page greets the owner by first name; the store name shows in the header.
    await expect(page.getByRole('heading', { level: 1, name: /^Good (morning|afternoon|evening), E2E$/ })).toBeVisible();
    await expect(page.getByRole('banner').getByText(`E2E ${name}`)).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Store setup' })).toBeVisible();

    // Shoppers see "opening soon" until the store is published.
    const shopper = await browser.newPage();
    await shopper.goto(`http://${name}.localhost:8000/en`);
    await expect(shopper.getByText('Opening soon')).toBeVisible();
});
