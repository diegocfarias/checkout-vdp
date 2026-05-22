import { expect, test } from '@playwright/test';

import { fixtures } from './support/env';

test.describe('checkout readonly', () => {
    test('existing checkout token exposes summary and passenger step', async ({ page }) => {
        test.skip(!fixtures.checkoutToken, 'set E2E_CHECKOUT_TOKEN to run checkout readonly tests');

        await page.goto(`/r/${fixtures.checkoutToken}`);

        await expect(page.getByRole('heading', { name: /Sua viagem/i })).toBeVisible();
        await expect(page.locator('body')).toContainText('Passagens');
        await expect(page.locator('body')).toContainText('Taxas');
        await expect(page.locator('body')).toContainText('Valor total');

        await page.getByRole('link', { name: /Continuar/i }).click();
        await expect(page.locator('#checkout-form')).toBeVisible();
        await expect(page.locator('#payer_name')).toBeVisible();
        await expect(page.locator('#footer-total')).toBeVisible();
    });
});
