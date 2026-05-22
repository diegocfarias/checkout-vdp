import { expect, test } from '@playwright/test';

import { defaultSearchParams, futureDate } from './support/env';
import { openResults, waitForSearchToSettle } from './support/search';

test.describe('public search smoke', () => {
    test('home exposes the main flight search form', async ({ page }) => {
        await page.goto('/');

        await expect(page.locator('#search-form')).toBeVisible();
        await expect(page.locator('#departure-input')).toBeVisible();
        await expect(page.locator('#arrival-input')).toBeVisible();
        await expect(page.locator('#btn-search')).toBeVisible();

        await expect(page.locator('body')).not.toContainText('Desconto no Pix nas passagens');
    });

    test('calendar price API returns levels without exposing source', async ({ request }) => {
        const params = defaultSearchParams({
            outbound_date: futureDate(35),
            inbound_date: futureDate(45),
        });

        const response = await request.get('/api/date-prices', {
            params: {
                departure: params.departure,
                arrival: params.arrival,
                cabin: params.cabin,
                adults: params.adults,
                children: params.children,
                infants: params.infants,
                date_from: params.outbound_date,
                date_to: params.inbound_date ?? futureDate(45),
                trip_type: params.trip_type,
            },
        });

        expect(response.status()).toBe(200);
        const payload = await response.json();

        expect(payload).toHaveProperty('levels');
        expect(payload).toHaveProperty('currency');
        expect(payload).not.toHaveProperty('source');

        for (const level of Object.values(payload.levels ?? {})) {
            expect(['low', 'medium', 'high']).toContain(level);
        }
    });

    test('results page loads cards or an empty state without crashing', async ({ page }) => {
        await openResults(page);

        const totalCards = await waitForSearchToSettle(page);
        const bodyText = await page.locator('body').innerText();

        expect(totalCards > 0 || /Nenhum voo encontrado|Nenhum resultado|Tente alterar/i.test(bodyText)).toBeTruthy();
        await expect(page.locator('#results-count')).toBeVisible();
    });
});
