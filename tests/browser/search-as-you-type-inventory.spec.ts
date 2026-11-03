// Search as you type on the inventory (#27): each pause in typing submits the filter form, which Tessera sends as one
// visit of the results by their anchor, the query in the URL. The box sits outside the results, so it keeps focus and
// its caret; while the search is out the form carries `data-loading` (the box's spinner) and the results `aria-busy`.
// The form carries `data-push="replace"`, so typing replaces the list's history entry rather than adding one, while a
// pressed Filter (`data-push="push"`) still adds one. Back, Forward and a reload each show the list of their URL.

import { expect, test } from '@playwright/test';
import { countWhere, pageVins, shownVins, signIn, sql, summary } from './support/inventory';
import { caret, expectSearches, historyLength, inventorySearch, PAUSE, QUICK, recordSearches, searched, VEHICLE_COLUMNS } from './support/search';
import { anchorPath, collectErrors, expectMarksKept, markPage, ready, RESULTS } from './support/tessera';

// The list a search keeps, as Postgres gives it: its count line and its first page. A further condition joins the
// search's with AND, as the list's other filters do.
async function expectListFor(page: import('@playwright/test').Page, text: string, also = ''): Promise<void> {
    const search = searched(VEHICLE_COLUMNS, text);
    const where = also === '' ? search : `WHERE (${search.slice('WHERE '.length)}) AND ${also}`;
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(where)));
    await expect(shownVins(page)).toHaveText(pageVins(where, 'added_on DESC'));
}

test('typing in the inventory search swaps the results alone after each pause, the URL, focus and caret in step', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectErrors(page);
    await signIn(page);
    await ready(page);

    // The box: a search field in the filter form, which targets the results by their anchor.
    const box = page.locator('#filter-q');
    const form = page.locator('#inventory-filters');
    const results = page.locator(`[data-t-anchor="${RESULTS}"]`);
    const spinner = form.locator('.loading-spinner');
    await expect(box).toHaveAttribute('type', 'search');
    await expect(box).toHaveAttribute('data-on:input__debounce.300ms', 'el.form.requestSubmit()');
    await expect(box).toHaveAttribute('data-focus-key', 'inventory-search');
    await expect(form).toHaveAttribute('data-target', `@${RESULTS}`);
    await expect(form).toHaveAttribute('data-push', 'replace');
    await expect(form.getByRole('button', { name: 'Filter' })).toBeVisible();
    await expect(form.getByRole('button', { name: 'Filter' })).toHaveAttribute('data-push', 'push');
    await expect(spinner).toBeHidden();
    const path = await anchorPath(page, RESULTS);

    // The commonest three-letter start of a make, so every search keeps some vehicles.
    const word = sql("SELECT lower(left(make, 3)) FROM vehicles GROUP BY 1 ORDER BY count(*) DESC, 1 LIMIT 1")[0];
    const searches = await recordSearches(page, '/app/inventory');
    await markPage(page);
    const entries = await historyLength(page);

    // Two keys with a pause after each: two visits, each of the results alone, and no click.
    await box.click();
    await box.pressSequentially(word.slice(0, 2), { delay: PAUSE });
    expect(await expectSearches(searches, 2)).toEqual([word.slice(0, 1), word.slice(0, 2)]);
    for (const request of searches.requests) {
        expect(request.tesseraRequest).toBe('visit');
        expect(request.targets).toBe(`${path} @${RESULTS}`);
        expect(request.templates).toEqual([path]);
    }
    await expect(page).toHaveURL(inventorySearch(word.slice(0, 2)));
    await expectListFor(page, word.slice(0, 2));
    await expect(box).toBeFocused();
    await expect(box).toHaveValue(word.slice(0, 2));
    expect(await caret(box)).toBe(2);
    expect(await historyLength(page)).toBe(entries);
    await expectMarksKept(page);

    // The third key typed with no pause before the next: one visit for the two together.
    searches.requests.length = 0;
    await box.pressSequentially(`${word.slice(2)} `, { delay: QUICK });
    await box.press('Backspace');
    expect(await expectSearches(searches, 1)).toEqual([word]);
    await expect(page).toHaveURL(inventorySearch(word));
    await expectListFor(page, word);
    await expect(box).toBeFocused();
    expect(await caret(box)).toBe(3);

    // While a search is out the form is loading, its spinner shows and the results are busy; all go when it lands.
    searches.requests.length = 0;
    searches.hold(word.slice(0, 2));
    await box.press('Backspace');
    await expect.poll(() => searches.requests.length).toBe(1);
    await expect(form).toHaveAttribute('data-loading', '');
    await expect(results).toHaveAttribute('aria-busy', 'true');
    await expect(spinner).toBeVisible();
    searches.release();
    await expect(page).toHaveURL(inventorySearch(word.slice(0, 2)));
    await expect(form).not.toHaveAttribute('data-loading');
    await expect(results).not.toHaveAttribute('aria-busy');
    await expect(spinner).toBeHidden();
    await expectListFor(page, word.slice(0, 2));

    // Every search replaced the list's entry: typing added none. A pressed Filter adds one.
    expect(await historyLength(page)).toBe(entries);
    const inStock = "status = 'in_stock'";
    const filtered = inventorySearch(word.slice(0, 2)).replace('status=', 'status=in_stock');
    await page.locator('#filter-status').selectOption('in_stock');
    await form.getByRole('button', { name: 'Filter' }).click();
    await expect(page).toHaveURL(filtered);
    await expectListFor(page, word.slice(0, 2), inStock);
    expect(await historyLength(page)).toBe(entries + 1);

    // Back and Forward show each entry's list, and a reload shows the URL's list with its query in the box.
    await page.goBack();
    await expect(page).toHaveURL(inventorySearch(word.slice(0, 2)));
    await expectListFor(page, word.slice(0, 2));
    await page.goForward();
    await expect(page).toHaveURL(filtered);
    await expectListFor(page, word.slice(0, 2), inStock);
    await expectMarksKept(page);
    await page.reload();
    await ready(page);
    await expect(box).toHaveValue(word.slice(0, 2));
    await expectListFor(page, word.slice(0, 2), inStock);

    expect(errors).toEqual([]);
});
