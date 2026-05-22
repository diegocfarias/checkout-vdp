import { expect, Page } from '@playwright/test';

import { defaultSearchParams, env, SearchParams } from './env';

export function searchResultsPath(overrides: Partial<SearchParams> = {}): string {
    const params = defaultSearchParams(overrides);
    const qs = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== '') {
            qs.set(key, value);
        }
    });

    return `/voos?${qs.toString()}`;
}

export async function openResults(page: Page, overrides: Partial<SearchParams> = {}): Promise<void> {
    const path = searchResultsPath(overrides);

    for (let attempt = 0; attempt < 2; attempt++) {
        await page.goto(path, { waitUntil: 'domcontentloaded' });
        if (await page.locator('#combinations-list').count() > 0) {
            await expect(page.locator('#combinations-list')).toBeVisible();
            return;
        }

        if (attempt === 0) {
            await page.waitForTimeout(1_000);
        }
    }

    await expect(page.locator('#combinations-list')).toBeVisible();
}

export async function waitForSearchToSettle(page: Page): Promise<number> {
    const timeout = Number(env('E2E_RESULTS_TIMEOUT_MS', '90000'));

    await page.waitForFunction(() => {
        const cardCount = document.querySelectorAll('.combination-card').length;
        const text = document.body.innerText || '';
        const progress = document.querySelector<HTMLElement>('#search-progress');
        const progressHidden = !!progress && (progress.style.display === 'none' || progress.offsetParent === null);

        return cardCount > 0
            || /Nenhum voo encontrado|Nenhum resultado|Tente alterar/i.test(text)
            || progressHidden;
    }, null, { timeout }).catch(() => undefined);

    return page.locator('.combination-card').count();
}

export async function waitForVisibleCards(page: Page): Promise<number> {
    await waitForSearchToSettle(page);
    return page.locator('.combination-card:visible').count();
}

export async function checkFilter(page: Page, selector: string): Promise<void> {
    await page.evaluate((inputSelector) => {
        const input = document.querySelector<HTMLInputElement>(inputSelector);
        if (!input) return;

        input.checked = true;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }, selector);
    await page.waitForTimeout(300);
}

export async function clearFilters(page: Page): Promise<void> {
    await page.evaluate(() => {
        const clear = (window as unknown as { clearFilters?: () => void }).clearFilters;
        if (clear) clear();
    });
    await page.waitForTimeout(300);
}

export async function visibleFlightOptionTexts(page: Page, direction: 'ob' | 'ib'): Promise<string[]> {
    return page.locator(`.combination-card:visible .flight-option[data-dir="${direction}"]:visible`).allTextContents();
}
