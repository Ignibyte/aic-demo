// Search as you type on the customers (#27): the list's results are one component anchored `customer-results`, built
// from their source; the search and the page links name them, and each pause in typing submits the search form as one
// visit of them alone, the query in the URL, the box keeping focus and its caret. The form carries
// `data-push="replace"`, so typing replaces the list's history entry rather than adding one, while a page link still
// adds one. Back, Forward and a reload each show the list of their URL.

import { expect, test, type Page } from '@playwright/test';
import { countWhere, PAGE, pageNames, shownNames, signInAt } from './support/customers';
import { caret, customerSearch, CUSTOMER_COLUMNS, expectSearches, historyLength, PAUSE, QUICK, recordSearches, searched } from './support/search';
import { anchorPath, collectErrors, expectMarksKept, markPage, ready } from './support/tessera';

// The customers' results' anchor.
const RESULTS = 'customer-results';

// What the table's summary says for that many customers at that page.
function summary(count: number, page = 1): string {
    const pages = Math.ceil(count / PAGE);
    const customers = `${count} ${count === 1 ? 'customer' : 'customers'}`;

    return pages > 1 ? `${customers}, page ${page} of ${pages}` : customers;
}

// The list a search keeps, as Postgres gives it: its count line and the page's names.
async function expectListFor(page: Page, text: string, at = 1): Promise<void> {
    const where = searched(CUSTOMER_COLUMNS, text);
    await expect(page.locator('#customers-table-summary')).toHaveText(summary(countWhere(where), at));
    await expect(shownNames(page)).toHaveText(pageNames(where, (at - 1) * PAGE));
}

test('the customers search swaps the anchored results alone after each pause, the URL, focus and caret in step', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectErrors(page);
    await signInAt(page, '/app/customers');
    await ready(page);

    // One element carries the results' anchor: their root, around the table and the pages. The search form and every
    // page link name it, never a path.
    const results = page.locator(`[data-t-anchor="${RESULTS}"]`);
    const box = page.locator('#filter-q');
    const form = page.locator('#customers-filters');
    await expect(results).toHaveCount(1);
    await expect(results).toHaveAttribute('data-t-c', /.+/);
    await expect(results.locator('#customers-table')).toHaveCount(1);
    await expect(results.locator('#customers-pages')).toHaveCount(1);
    await expect(form.locator('#filter-q')).toHaveCount(1);
    await expect(results.locator('#filter-q')).toHaveCount(0);
    await expect(form).toHaveAttribute('data-target', `@${RESULTS}`);
    await expect(form).toHaveAttribute('data-push', 'replace');
    await expect(form.getByRole('button', { name: 'Filter' })).toHaveAttribute('data-push', 'push');
    const pageTargets = await page.locator('#customers-pages a').evaluateAll((links) => links.map((link) => link.getAttribute('data-target')));
    expect(pageTargets.length).toBeGreaterThan(1);
    expect(new Set(pageTargets)).toEqual(new Set([`@${RESULTS}`]));
    await expect(box).toHaveAttribute('type', 'search');
    await expect(box).toHaveAttribute('data-on:input__debounce.300ms', 'el.form.requestSubmit()');
    await expect(box).toHaveAttribute('data-focus-key', 'customers-search');
    const path = await anchorPath(page, RESULTS);
    expect(path).toMatch(/^content\/.+/);

    const searches = await recordSearches(page, '/app/customers');
    await markPage(page);
    const entries = await historyLength(page);

    // A pause after each of two keys: two visits of the results alone, no click.
    await box.click();
    await box.pressSequentially('ex', { delay: PAUSE });
    expect(await expectSearches(searches, 2)).toEqual(['e', 'ex']);
    for (const request of searches.requests) {
        expect(request.tesseraRequest).toBe('visit');
        expect(request.targets).toBe(`${path} @${RESULTS}`);
        expect(request.templates).toEqual([path]);
    }
    await expect(page).toHaveURL(customerSearch('ex'));
    await expectListFor(page, 'ex');
    await expect(box).toBeFocused();
    expect(await caret(box)).toBe(2);
    await expectMarksKept(page);

    // Several keys with no pause between them: one visit.
    searches.requests.length = 0;
    await box.pressSequentially('ample.test', { delay: QUICK });
    expect(await expectSearches(searches, 1)).toEqual(['example.test']);
    await expect(page).toHaveURL(customerSearch('example.test'));
    await expectListFor(page, 'example.test');
    await expect(box).toBeFocused();
    expect(await caret(box)).toBe('example.test'.length);
    expect(await historyLength(page)).toBe(entries);

    // The search keeps more than a page: its second page is a visit of the results alone, keeping the search.
    searches.requests.length = 0;
    const second = page.locator('#customers-pages a', { hasText: /^2$/ });
    await expect(second).toHaveAttribute('href', '/app/customers?q=example.test&page=2');
    await second.click();
    await expectSearches(searches, 1);
    expect(searches.requests[0].targets).toBe(`${path} @${RESULTS}`);
    expect(searches.requests[0].templates).toEqual([path]);
    await expect(page).toHaveURL('/app/customers?q=example.test&page=2');
    await expectListFor(page, 'example.test', 2);
    await expect(box).toHaveValue('example.test');
    expect(await historyLength(page)).toBe(entries + 1);

    // A search no one matches says so.
    await box.fill('no such customer');
    await expect(page).toHaveURL(customerSearch('no such customer'));
    await expect(page.locator('#customers-table-summary')).toHaveText('0 customers');
    await expect(page.locator('#customers-table-empty')).toHaveText('No customer matches. Try another search, or clear it.');
    expect(await historyLength(page)).toBe(entries + 1);

    // The search replaced the second page's entry, so Back goes to the search before the page link, and Forward to the
    // last search; a reload shows the URL's list with its query in the box.
    await page.goBack();
    await expect(page).toHaveURL(customerSearch('example.test'));
    await expectListFor(page, 'example.test');
    await page.goForward();
    await expect(page).toHaveURL(customerSearch('no such customer'));
    await expect(page.locator('#customers-table-summary')).toHaveText('0 customers');
    await page.goBack();
    await expect(page).toHaveURL(customerSearch('example.test'));
    await expectListFor(page, 'example.test');
    await expectMarksKept(page);
    await page.reload();
    await ready(page);
    await expect(box).toHaveValue('example.test');
    await expectListFor(page, 'example.test');

    expect(errors).toEqual([]);
});
