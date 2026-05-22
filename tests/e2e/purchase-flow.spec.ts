import { expect, test } from '@playwright/test';

import { flags } from './support/env';
import { openResults, waitForVisibleCards } from './support/search';

test.describe('guarded purchase flow', () => {
    test('searches, selects a flight and validates checkout form', async ({ page }) => {
        test.skip(!flags.enablePurchaseFlow, 'set E2E_ENABLE_PURCHASE_FLOW=true to create a checkout from search');

        await openResults(page);
        const cardCount = await waitForVisibleCards(page);
        test.skip(cardCount === 0, 'configured route returned no purchasable results');

        await page.locator('.combination-card:visible').first().getByRole('button', { name: /Comprar/i }).first().click();

        await expect(page.getByRole('heading', { name: /Sua viagem/i })).toBeVisible({ timeout: 60_000 });
        await expect(page.locator('body')).toContainText('Passagens');
        await expect(page.locator('body')).toContainText('Taxas');

        await page.getByRole('link', { name: /Continuar/i }).click();
        await expect(page.locator('#checkout-form')).toBeVisible();

        await page.locator('#passengers_0_full_name').fill('Cliente QA Automacao');
        await page.locator('#passengers_0_nationality').selectOption('BR');
        await page.locator('#passengers_0_document').fill('390.533.447-05');
        await page.locator('#passengers_0_birth_date').fill('10/10/1990');
        await page.locator('#passengers_0_email').fill('qa+cliente@voedeprimeira.test');
        await page.locator('#passengers_0_phone').fill('(11) 99999-9999');

        await page.locator('#payer_name').fill('Cliente QA Automacao');
        await page.locator('#payer_email').fill('qa+pagador@voedeprimeira.test');
        await page.locator('#payer_document').fill('390.533.447-05');

        await page.locator('input[name="payment_method"][value="pix"]').check({ force: true });
        await expect(page.locator('#footer-total')).toBeVisible();

        if (flags.submitPayment) {
            await page.getByRole('button', { name: /Finalizar compra/i }).click();
            await expect(page.locator('body')).toContainText(/pedido|pagamento|pix/i, { timeout: 60_000 });
        }
    });
});
