import { expect, test } from '@playwright/test';
import { countWhere, PAGE, pageVins, shownVins, signIn, summary } from './support/inventory';
import { collectErrors, expectMarksKept, expectTableVisit, markPage, ready, pathOf, recordListRequests } from './support/tessera';

// What the list shows at each step: its path, the condition and order Postgres reads it with, and its offset.
interface Step {
    path: string;
    where: string;
    order: string;
    offset: number;
}

test('back and forward through a filter, a sort and a page restore each URL and its rows in place', async ({ page }) => {
    const errors = collectErrors(page);
    await signIn(page);
    await ready(page);

    const kept = "WHERE status = 'in_stock'";
    const start: Step = { path: '/app/inventory', where: '', order: 'added_on DESC', offset: 0 };
    const steps: Step[] = [
        { path: '/app/inventory?q=&make=&status=in_stock&min_price=&max_price=&sort=', where: kept, order: 'added_on DESC', offset: 0 },
        { path: '/app/inventory?status=in_stock&sort=price', where: kept, order: 'list_price ASC', offset: 0 },
        { path: '/app/inventory?status=in_stock&sort=price&page=2', where: kept, order: 'list_price ASC', offset: PAGE },
    ];
    const expectStep = async (step: Step): Promise<void> => {
        await expect(page).toHaveURL(step.path);
        await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(step.where), step.offset / PAGE + 1));
        await expect(shownVins(page)).toHaveText(pageVins(step.where, step.order, step.offset));
        await expectMarksKept(page);
    };

    const requests = await recordListRequests(page);
    await markPage(page);

    await page.locator('#filter-status').selectOption('in_stock');
    await page.locator('#inventory-filters').getByRole('button', { name: 'Filter' }).click();
    await expectTableVisit(page, requests, steps[0].path);
    await expectStep(steps[0]);
    await page.locator('#vehicles-table thead th', { hasText: 'Price' }).getByRole('link').click();
    await expectTableVisit(page, requests, steps[1].path);
    await expectStep(steps[1]);
    await page.locator('#vehicles-pages a', { hasText: /^2$/ }).click();
    await expectTableVisit(page, requests, steps[2].path);
    await expectStep(steps[2]);

    // Back to the sort and the filter: each entry a change made asks for the table again.
    await page.goBack();
    await expectTableVisit(page, requests, steps[1].path);
    await expectStep(steps[1]);
    await page.goBack();
    await expectTableVisit(page, requests, steps[0].path);
    await expectStep(steps[0]);

    // Back to the list as it opened: its entry asks for the whole document, still fetched and committed in place.
    await page.goBack();
    await expect.poll(() => requests.length).toBe(1);
    expect(requests[0].resourceType).toBe('fetch');
    expect(pathOf(requests[0].url)).toBe(start.path);
    expect(requests[0].tesseraRequest).toBe('visit');
    requests.length = 0;
    await expectStep(start);

    // Forward again through the filter, the sort and the page.
    for (const step of steps) {
        await page.goForward();
        await expectTableVisit(page, requests, step.path);
        await expectStep(step);
    }

    expect(errors).toEqual([]);
});
