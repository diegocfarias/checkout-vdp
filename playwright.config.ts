import { defineConfig, devices } from '@playwright/test';

import { getE2eBaseUrl, isFlagEnabled, loadE2eEnv } from './tests/e2e/support/env';

loadE2eEnv();

const port = process.env.E2E_PORT ?? '8000';
const baseURL = getE2eBaseUrl();
const startServer = isFlagEnabled('E2E_START_SERVER');
const workers = Number(process.env.E2E_WORKERS ?? (process.env.CI ? '2' : '1'));
const retries = Number(process.env.E2E_RETRIES ?? (process.env.CI ? '2' : '1'));
const projects = [
    {
        name: 'chromium',
        use: { ...devices['Desktop Chrome'] },
    },
];

if (isFlagEnabled('E2E_INCLUDE_MOBILE')) {
    projects.push({
        name: 'mobile-chrome',
        use: { ...devices['Pixel 7'] },
    });
}

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries,
    workers,
    timeout: 90_000,
    expect: {
        timeout: 10_000,
    },
    reporter: [
        ['list'],
        ['html', { open: 'never', outputFolder: 'playwright-report' }],
        ['json', { outputFile: 'test-results/e2e-results.json' }],
    ],
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        actionTimeout: 15_000,
        navigationTimeout: 45_000,
    },
    webServer: startServer
        ? {
            command: `php artisan serve --host=127.0.0.1 --port=${port}`,
            url: baseURL,
            reuseExistingServer: true,
            timeout: 120_000,
        }
        : undefined,
    projects,
});
