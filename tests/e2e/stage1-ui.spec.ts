import { expect, test } from '@playwright/test';

import { isFlagEnabled } from './support/env';

test.describe('QA stage 1 UI adjustments', () => {
    test('search calendar uses neutral ida and volta labels', async ({ page }) => {
        const dateKey = (date: Date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        };
        const outbound = new Date();
        outbound.setDate(outbound.getDate() + 1);
        const inbound = new Date();
        inbound.setDate(inbound.getDate() + 4);

        await page.goto('/');
        await page.locator('#datepicker-toggle').click();
        await page.locator(`[data-date="${dateKey(outbound)}"] button`).click();
        await page.locator(`[data-date="${dateKey(inbound)}"] button`).click();

        const firstChip = page.locator('#dp-chips > span').first();
        const secondChip = page.locator('#dp-chips > span').nth(1);
        await expect(firstChip).toContainText('IDA');
        await expect(secondChip).toContainText('VOLTA');
        await expect(firstChip).toHaveClass(/bg-gray-100/);
        await expect(secondChip).toHaveClass(/bg-gray-100/);
        await expect(page.locator('#dp-calendars span.absolute').first()).toHaveClass(/text-gray-600/);
    });

    test('fake checkout shows required marks and streamlined cancellation policy', async ({ page }) => {
        test.skip(!isFlagEnabled('E2E_ENABLE_DEV_FIXTURES'), 'set E2E_ENABLE_DEV_FIXTURES=true to use local debug fixtures');

        await page.goto('/dev/fake-checkout');

        await expect(page.getByRole('heading', { name: /Sua viagem/i })).toBeVisible();
        await expect(page.locator('body')).toContainText('Cancelamento da viagem');
        await expect(page.locator('body')).not.toContainText('Ver política completa');

        const directionLabel = page.locator('text=IDA').first();
        await expect(directionLabel).toHaveClass(/text-gray-600/);
        await expect(directionLabel).not.toHaveClass(/bg-blue-100|text-blue-700|bg-emerald-100|text-emerald-700/);

        await page.getByRole('link', { name: /Continuar/i }).click();

        await expect(page.locator('#checkout-form')).toBeVisible();
        await expect(page.locator('label.required-label[for="passengers_0_full_name"]')).toBeVisible();
        await expect(page.locator('label.required-label[for="payer_name"]')).toBeVisible();

        const requiredMarker = await page.locator('label.required-label[for="passengers_0_full_name"]').evaluate((label) => {
            return window.getComputedStyle(label, '::after').content;
        });

        expect(requiredMarker).toContain('*');
    });
});
