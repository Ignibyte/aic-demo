import { expect, test } from '@playwright/test';
import { countWhere, PAGE, pageVins, shownVins, signIn, summary } from './support/inventory';
import { anchorPath, collectErrors, expectMarksKept, expectTableVisit, expectTesseraVary, markPage, ready, recordListRequests, RESULTS } from './support/tessera';

test('a filter, a sort and a page each swap the table alone, in one Tessera visit, the URL in step', async ({ page }) => {
    const errors = collectErrors(page);
    await signIn(page);
    await ready(page);

    const filters = page.locator('#inventory-filters');
    const head = (label: string) => page.locator('#vehicles-table thead th', { hasText: label });
    const pageLink = (label: string) => page.locator('#vehicles-pages a', { hasText: new RegExp(`^${label}$`) });
    const keptSort = page.locator('input[type="hidden"][name="sort"][form="inventory-filters"]');

    // Every control the table answers names it by its anchor; the filter form keeps no sort of its own.
    await expect(filters).toHaveAttribute('data-target', `@${RESULTS}`);
    await expect(head('Price').getByRole('link')).toHaveAttribute('data-target', `@${RESULTS}`);
    await expect(pageLink('2')).toHaveAttribute('data-target', `@${RESULTS}`);
    await expect(filters.locator('[name="sort"]')).toHaveCount(0);
    await expect(keptSort).toHaveValue('');

    const requests = await recordListRequests(page);
    await markPage(page);

    // A filter.
    await page.locator('#filter-status').selectOption('in_stock');
    await filters.getByRole('button', { name: 'Filter' }).click();
    const filterPath = '/app/inventory?q=&make=&status=in_stock&min_price=&max_price=&sort=';
    await expectTableVisit(page, requests, filterPath);
    await expect(page).toHaveURL(filterPath);
    const kept = "WHERE status = 'in_stock'";
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(kept)));
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'added_on DESC'));
    await expect(page.locator('#filter-status')).toHaveValue('in_stock');
    await expectMarksKept(page);

    // A sort: the table's own copy of the sort follows it.
    await head('Price').getByRole('link').click();
    const sortPath = '/app/inventory?status=in_stock&sort=price';
    await expectTableVisit(page, requests, sortPath);
    await expect(page).toHaveURL(sortPath);
    await expect(head('Price')).toHaveAttribute('aria-sort', 'ascending');
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'list_price ASC'));
    await expect(keptSort).toHaveValue('price');
    await expect(page.locator('#filter-status')).toHaveValue('in_stock');
    await expectMarksKept(page);

    // A page.
    await pageLink('2').click();
    const pagePath = '/app/inventory?status=in_stock&sort=price&page=2';
    await expectTableVisit(page, requests, pagePath);
    await expect(page).toHaveURL(pagePath);
    await expect(pageLink('2')).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(kept), 2));
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'list_price ASC', PAGE));
    await expectMarksKept(page);

    // A filter after the sort sends the sort the table shows, and starts again at page 1.
    await page.locator('#filter-min-price').fill('10000');
    await filters.getByRole('button', { name: 'Filter' }).click();
    const refinedPath = '/app/inventory?q=&make=&status=in_stock&min_price=10000&max_price=&sort=price';
    await expectTableVisit(page, requests, refinedPath);
    await expect(page).toHaveURL(refinedPath);
    const refined = "WHERE status = 'in_stock' AND list_price >= 10000";
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(refined)));
    await expect(shownVins(page)).toHaveText(pageVins(refined, 'list_price ASC'));
    await expectMarksKept(page);

    // A visit naming a component the results lack gets the whole document. The page sends it, with its session.
    const nope = `${await anchorPath(page, RESULTS)}/nope`;
    const missing = await page.evaluate(async (targets) => {
        const response = await fetch('/app/inventory', {
            headers: {
                'Tessera-Request': 'visit',
                'Tessera-Protocol': '2',
                'Tessera-Layout': 'lotline',
                'Tessera-Targets': targets,
            },
        });

        return {
            status: response.status,
            contentType: response.headers.get('content-type') ?? '',
            vary: response.headers.get('vary') ?? '',
            body: await response.text(),
        };
    }, nope);
    expect(missing.status).toBe(200);
    expect(missing.contentType).toMatch(/^text\/html/);
    expectTesseraVary(missing.vary);
    expect(missing.body).toMatch(/^<!doctype html>/i);

    expect(errors).toEqual([]);
});
