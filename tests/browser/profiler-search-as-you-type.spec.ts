// Search as you type under AIC's profiler (#27): the toolbar lists this browser's exchanges as the server recorded them,
// so each pause in typing shows as one visit of the inventory's results, its query in its profile, and typing with no
// pause adds none of its own. A search whose answer the look holds back is listed too, since the server answered it,
// but its answer never lands: the page shows the newest search's results. gate:23 runs it in its profiled pass only;
// it skips on a site without the profiler.

import { expect, test, type Page } from '@playwright/test';
import { countWhere, signIn, summary } from './support/inventory';
import { collectProfilerErrors, exchangeLine, exchanges, expectExchange, expectProfile, skipUnlessProfiled, toolbar } from './support/profiler';
import { expectSearches, inventorySearch, PAUSE, QUICK, recordSearches, searched, VEHICLE_COLUMNS } from './support/search';
import { ready } from './support/tessera';

// The toolbar's line for a visit of the inventory's results.
const SEARCH = exchangeLine('visit', 'GET', '/app/inventory', '@inventory-results', '200');

// The profile query the filter form sends for a search.
function profiled(text: string): string {
    return inventorySearch(text).slice('/app/inventory?'.length);
}

// Signs in, opens the toolbar on the inventory's own load and gives the search box.
async function openInventory(page: Page) {
    await signIn(page);
    await ready(page);
    await toolbar(page).locator('summary').click();
    await expect(toolbar(page).locator('summary')).toHaveText(exchangeLine('AIC · load', 'GET', '/app/inventory', '', '200'));

    return page.locator('#filter-q');
}

test.beforeEach(async ({ request }) => {
    await skipUnlessProfiled(request);
});

test('the toolbar lists one visit of the results for each pause in typing, each with its query', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectProfilerErrors(page);
    const box = await openInventory(page);

    // Two keys with a pause after each: two lines, the first search's profile naming `c`, the second's `cr`.
    await box.click();
    await box.pressSequentially('cr', { delay: PAUSE });
    await expectProfile(page, await expectExchange(page, SEARCH, 1), 'GET /app/inventory', '200', { query: profiled('c') });
    await expectProfile(page, await expectExchange(page, SEARCH, 2), 'GET /app/inventory', '200', { query: profiled('cr') });

    // Three keys with no pause between them: one more line, for the three together; the stream reads the profiles once
    // a second, so the look gives it time to show any other search.
    await box.pressSequentially('ysl', { delay: QUICK });
    await expectProfile(page, await expectExchange(page, SEARCH, 3), 'GET /app/inventory', '200', { query: profiled('crysl') });
    await page.waitForTimeout(2_000);
    await expect(exchanges(page, SEARCH)).toHaveCount(3);
    await expect(page).toHaveURL(inventorySearch('crysl'));

    expect(errors).toEqual([]);
});

test('a search whose answer is held back is listed, and its answer never lands over the newer one', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectProfilerErrors(page);
    const box = await openInventory(page);
    const searches = await recordSearches(page, '/app/inventory');

    // `c` is held back after the server answered it; `cr` is typed after the pause and answered at once.
    searches.hold('c');
    await box.click();
    await box.pressSequentially('cr', { delay: PAUSE });
    expect(await expectSearches(searches, 2)).toEqual(['c', 'cr']);
    await expectProfile(page, await expectExchange(page, SEARCH, 1), 'GET /app/inventory', '200', { query: profiled('c') });
    await expectProfile(page, await expectExchange(page, SEARCH, 2), 'GET /app/inventory', '200', { query: profiled('cr') });

    // The held answer goes, and the page still shows `cr`'s results.
    searches.release();
    await page.waitForTimeout(PAUSE);
    const newer = searched(VEHICLE_COLUMNS, 'cr');
    expect(countWhere(newer)).not.toBe(countWhere(searched(VEHICLE_COLUMNS, 'c')));
    await expect(page).toHaveURL(inventorySearch('cr'));
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(newer)));
    await expect(exchanges(page, SEARCH)).toHaveCount(2);

    expect(errors).toEqual([]);
});
