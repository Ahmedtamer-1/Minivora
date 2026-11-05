// Playwright config. Start the app first (`composer run dev` or `php artisan serve`), seeded.
// *.localhost subdomains resolve to this machine in Chromium, so store URLs work with no setup.
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    timeout: 30_000,
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
        launchOptions: { args: ['--host-resolver-rules=MAP *.localhost 127.0.0.1'] },
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
        { name: 'mobile', use: { ...devices['Pixel 7'] } },
    ],
});
