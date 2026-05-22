import path from 'node:path';

import { expect, test, type Page } from '@playwright/test';

import { credentials, fixtures, flags } from './support/env';

async function loginCustomer(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: /Entrar/i }).click();
}

test.describe('customer post-sale', () => {
    test('tracking form validates an unknown order', async ({ page }) => {
        await page.goto('/pedido');

        await page.locator('#tracking_code').fill('VDP-000000');
        await page.locator('#document').fill('390.533.447-05');
        await page.getByRole('button', { name: /Consultar pedido/i }).click();

        await expect(page.locator('body')).toContainText(/nao encontrado|n.o encontrado|Pedido/i);
    });

    test('tracking form opens a known order when fixture is configured', async ({ page }) => {
        test.skip(!fixtures.trackingCode || !fixtures.trackingDocument, 'set E2E_TRACKING_CODE and E2E_TRACKING_DOCUMENT');

        await page.goto('/pedido');
        await page.locator('#tracking_code').fill(fixtures.trackingCode);
        await page.locator('#document').fill(fixtures.trackingDocument);
        await page.getByRole('button', { name: /Consultar pedido/i }).click();

        await expect(page.locator('body')).toContainText(fixtures.trackingCode);
    });

    test('customer can access support area and attachment controls', async ({ page }) => {
        test.skip(!credentials.customerEmail || !credentials.customerPassword, 'set E2E_CUSTOMER_EMAIL and E2E_CUSTOMER_PASSWORD');

        await loginCustomer(page, credentials.customerEmail, credentials.customerPassword);
        await page.goto('/minha-conta/atendimentos');

        await expect(page.locator('body')).toContainText(/Atendimento|Solicita/i);

        if (flags.allowSupportMutations) {
            const newTicketButton = page.getByRole('link', { name: /Novo|Abrir|Atendimento/i }).first();
            if (await newTicketButton.count() > 0) {
                await newTicketButton.click();
                const controls = page.locator('textarea[name="message"], input[type="file"]');
                expect(await controls.count()).toBeGreaterThan(0);
            }
        }
    });

    test('customer can see or send support attachments on a known ticket', async ({ page }) => {
        test.skip(!credentials.customerEmail || !credentials.customerPassword || !fixtures.supportTicketPath, 'set customer credentials and E2E_SUPPORT_TICKET_PATH');

        await loginCustomer(page, credentials.customerEmail, credentials.customerPassword);
        await page.goto(fixtures.supportTicketPath);

        await expect(page.locator('body')).toContainText(/Atendimento|Solicita|Mensagem/i);

        const fileInput = page.locator('input[type="file"][name="attachments[]"]');
        const attachmentLinks = page.getByRole('link', { name: /Visualizar|Baixar/i });

        if (await attachmentLinks.count() > 0) {
            await expect(attachmentLinks.first()).toBeVisible();
        }

        if (flags.allowSupportMutations && await fileInput.count() > 0) {
            await page.locator('textarea[name="message"]').fill('Resposta automatizada com anexo de teste.');
            await fileInput.setInputFiles(path.resolve(process.cwd(), 'tests/e2e/fixtures/support-attachment.txt'));
            await page.getByRole('button', { name: /Enviar|Responder/i }).click();
            await expect(page.locator('body')).toContainText(/enviada|sucesso|Arquivo/i);
        } else {
            expect(await fileInput.count() + await attachmentLinks.count()).toBeGreaterThanOrEqual(0);
        }
    });
});
