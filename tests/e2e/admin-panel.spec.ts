import { expect, test } from '@playwright/test';

import { credentials } from './support/env';

test.describe('admin panel', () => {
    test('admin can log in and see core operational areas', async ({ page }) => {
        test.skip(!credentials.adminEmail || !credentials.adminPassword, 'set E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD');

        await page.goto('/admin');
        if (await page.locator('input[type="email"]').count() > 0) {
            await page.locator('input[type="email"]').fill(credentials.adminEmail);
            await page.locator('input[type="password"]').fill(credentials.adminPassword);
            await page.locator('button[type="submit"]').click();
        }

        await expect(page.locator('body')).toContainText(/Dashboard|Pedidos|Painel/i, { timeout: 30_000 });

        const body = page.locator('body');
        await expect(body).toContainText(/Pedidos/i);
        await expect(body).toContainText(/Configur/i);
        await expect(body).toContainText(/Clientes|Usuarios|Usu.rio/i);
    });
});
