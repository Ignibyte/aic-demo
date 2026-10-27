import { expect, test } from '@playwright/test';
import { countWhere, fillSignIn, PAGE, pageVins, shownVins, summary } from './support/inventory';
import { collectErrors, pathOf, recordListRequests } from './support/tessera';

test.use({ javaScriptEnabled: false });

test('with JavaScript off a filter, a sort and a page are plain GETs that reach the rows Postgres gives', async ({ page }) => {
    const errors = collectErrors(page);
    await page.goto('/sign-in?next=%2Fapp%2Finventory');
    await fillSignIn(page);

    const requests = await recordListRequests(page);
    // The one request a change makes: a document load, with no Tessera header.
    const expectDocument = async (path: string): Promise<void> => {
        await expect(page).toHaveURL(path);
        await expect.poll(() => requests.length).toBe(1);
        expect(requests[0].resourceType).toBe('document');
        expect(pathOf(requests[0].url)).toBe(path);
        expect(requests[0].tesseraRequest).toBe('');
        requests.length = 0;
    };
    const kept = "WHERE status = 'in_stock'";

    // A filter: the sort the table holds goes with the form, last, as before.
    await page.locator('#filter-status').selectOption('in_stock');
    await page.locator('#inventory-filters').getByRole('button', { name: 'Filter' }).click();
    await expectDocument('/app/inventory?q=&make=&status=in_stock&min_price=&max_price=&sort=');
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(kept)));
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'added_on DESC'));

    // A sort.
    await page.locator('#vehicles-table thead th', { hasText: 'Price' }).getByRole('link').click();
    await expectDocument('/app/inventory?status=in_stock&sort=price');
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'list_price ASC'));

    // A page.
    await page.locator('#vehicles-pages a', { hasText: /^2$/ }).click();
    await expectDocument('/app/inventory?status=in_stock&sort=price&page=2');
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(kept), 2));
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'list_price ASC', PAGE));

    // A filter after the sort keeps it.
    await page.locator('#filter-min-price').fill('10000');
    await page.locator('#inventory-filters').getByRole('button', { name: 'Filter' }).click();
    await expectDocument('/app/inventory?q=&make=&status=in_stock&min_price=10000&max_price=&sort=price');
    const refined = "WHERE status = 'in_stock' AND list_price >= 10000";
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(refined)));
    await expect(shownVins(page)).toHaveText(pageVins(refined, 'list_price ASC'));

    expect(errors).toEqual([]);
});
