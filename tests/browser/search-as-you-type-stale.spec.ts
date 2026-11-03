// A superseded search never paints (#27): the look holds the first search's answer back (the server has answered; the
// look passes the answer on only later), types on, and the newer search's results show. Tessera aborted the held
// request when the newer one superseded it, so when the look lets the held answer go it lands nowhere: the newer
// results still show, and Tessera reports the older exchange `superseded`.

import { expect, test } from '@playwright/test';
import { countWhere, pageVins, shownVins, signIn, summary } from './support/inventory';
import { exchangesSeen, expectSearches, inventorySearch, PAUSE, queryOf, recordSearches, searched, VEHICLE_COLUMNS, watchExchanges } from './support/search';
import { collectErrors, ready } from './support/tessera';

test('a search whose answer is late is superseded by the next, and its answer never paints over it', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectErrors(page);
    await signIn(page);
    await ready(page);
    await watchExchanges(page);

    const box = page.locator('#filter-q');
    const searches = await recordSearches(page, '/app/inventory');

    // `c` is held back; `cr`, typed after the pause, is answered at once.
    searches.hold('c');
    await box.click();
    await box.pressSequentially('cr', { delay: PAUSE });
    expect(await expectSearches(searches, 2)).toEqual(['c', 'cr']);
    const newer = searched(VEHICLE_COLUMNS, 'cr');
    const older = searched(VEHICLE_COLUMNS, 'c');
    expect(countWhere(newer)).not.toBe(countWhere(older));
    await expect(page).toHaveURL(inventorySearch('cr'));
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(newer)));
    await expect(shownVins(page)).toHaveText(pageVins(newer, 'added_on DESC'));

    // The held answer goes now, and lands nowhere. Only the visits count: with the profiler on, its stream's events
    // settle as exchanges too.
    searches.release();
    await expect.poll(async () => (await exchangesSeen(page))
        .filter((exchange) => exchange.kind === 'visit')
        .map((exchange) => `${exchange.kind} ${queryOf(exchange.url)} ${exchange.outcome}`))
        .toEqual(['visit c superseded', 'visit cr committed']);
    await page.waitForTimeout(PAUSE);
    await expect(page).toHaveURL(inventorySearch('cr'));
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(newer)));
    await expect(shownVins(page)).toHaveText(pageVins(newer, 'added_on DESC'));
    await expect(box).toHaveValue('cr');

    expect(errors).toEqual([]);
});
