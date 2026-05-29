import { expect, test } from '@playwright/test';

import { isFlagEnabled } from './support/env';

test.describe('QA stage 2 UI adjustments', () => {
    test('fake checkout shows direction icons and completed steps as the flow advances', async ({ page }) => {
        test.skip(!isFlagEnabled('E2E_ENABLE_DEV_FIXTURES'), 'set E2E_ENABLE_DEV_FIXTURES=true to use local debug fixtures');

        await page.goto('/dev/fake-checkout');

        await expect(page.locator('[data-step="1"][data-step-state="current"]')).toBeVisible();
        await expect(page.getByLabel('Voo de ida').first()).toBeVisible();

        await page.getByRole('link', { name: /Continuar/i }).click();

        await expect(page.locator('[data-step="1"][data-step-state="completed"]')).toBeVisible();
        await expect(page.locator('[data-step="2"][data-step-state="current"]')).toBeVisible();
        await page.locator('#btn-detalhes-compra').click();
        await expect(page.locator('#modal-detalhes')).toBeVisible();
        await expect(page.locator('[aria-label="Voo de ida"]:visible').first()).toBeVisible();
    });

    test('search results keep skeleton cards while provider response has not arrived', async ({ page }) => {
        await page.route('**/api/search/provider?**', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 600));
            await route.fulfill({
                contentType: 'application/json',
                body: JSON.stringify({ outbound: [], inbound: [] }),
            });
        });

        await page.goto('/voos?trip_type=oneway&departure=GIG&arrival=FOR&outbound_date=2026-07-16&adults=1&children=0&infants=0&cabin=EC');

        await expect(page.locator('.skeleton-card').first()).toBeVisible();
        await expect(page.locator('#results-count')).toContainText('Buscando');
    });

    test('going back to search results does not keep the travel loading overlay visible', async ({ page }) => {
        await page.route('**/api/search/provider?**', async (route) => {
            await route.fulfill({
                contentType: 'application/json',
                body: JSON.stringify({ outbound: [], inbound: [] }),
            });
        });

        await page.goto('/voos?trip_type=oneway&departure=GIG&arrival=FOR&outbound_date=2026-07-16&adults=1&children=0&infants=0&cabin=EC');
        await expect(page.locator('#results-count')).toBeVisible();

        await page.getByRole('link', { name: 'Passagens', exact: true }).click();
        await expect(page.locator('#search-form')).toBeVisible();

        await page.goBack();
        await expect(page.locator('#travel-loading')).toBeHidden();
        await expect(page.locator('#results-count')).toBeVisible();
    });
});
