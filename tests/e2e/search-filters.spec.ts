import { expect, test } from '@playwright/test';

import {
    checkFilter,
    clearFilters,
    openResults,
    visibleFlightOptionTexts,
    waitForVisibleCards,
} from './support/search';

test.describe('search filters', () => {
    test('outbound and inbound direct filters do not leave visible connection options', async ({ page }) => {
        await openResults(page);
        const cardCount = await waitForVisibleCards(page);
        test.skip(cardCount === 0, 'configured route returned no results');

        await checkFilter(page, '.filter-ob-stops[value="direct"]');
        const outboundTexts = await visibleFlightOptionTexts(page, 'ob');
        test.skip(outboundTexts.length === 0, 'no outbound options visible after direct filter');
        expect(outboundTexts.every((text) => !/conex/i.test(text))).toBeTruthy();

        await clearFilters(page);
        await checkFilter(page, '.filter-ib-stops[value="direct"]');
        const inboundTexts = await visibleFlightOptionTexts(page, 'ib');
        if (inboundTexts.length > 0) {
            expect(inboundTexts.every((text) => !/conex/i.test(text))).toBeTruthy();
        }
    });

    test('airline filters are split by outbound and inbound direction', async ({ page }) => {
        await openResults(page);
        const cardCount = await waitForVisibleCards(page);
        test.skip(cardCount === 0, 'configured route returned no results');

        const outboundAirline = page.locator('.filter-ob-cia').first();
        const inboundAirline = page.locator('.filter-ib-cia').first();

        test.skip(await outboundAirline.count() === 0, 'no outbound airline filter was rendered');
        await checkFilter(page, '.filter-ob-cia');
        await expect(page.locator('.combination-card:visible').first()).toBeVisible();

        await clearFilters(page);

        if (await inboundAirline.count() > 0) {
            await checkFilter(page, '.filter-ib-cia');
            await expect(page.locator('.combination-card:visible').first()).toBeVisible();
        }
    });
});
