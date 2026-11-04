// The comparison's drawer (#33): Compare sends one Tessera visit naming the drawer alone (`data-target` on its anchor),
// which leaves the URL and history as they were (`data-push="off"`) and shows the ticked cars side by side; a click
// outside the dock closes it (`data-on:click__outside`), as Close does.

import { expect, test } from '@playwright/test';
import { DRAWER, MAKE, compareCar, comparedTitles, dock, expectChosen, insertCompareCars, recordCompareRequests, removeCompareCars, tick } from './support/compare';
import { figures, profileApr } from './support/finance';
import { dollars, documentKept, markDocument } from './support/storefront';
import { anchorPath, collectErrors, ready } from './support/tessera';

let slugs: string[] = [];

test.beforeEach(() => {
    removeCompareCars();
    slugs = insertCompareCars(4);
});
test.afterEach(() => removeCompareCars());

test('Compare opens the drawer from one visit naming it alone, and a click outside closes it', async ({ page }) => {
    const errors = collectErrors(page);
    const parts = dock(page);
    await page.goto(`/cars?make=${MAKE}`);
    await ready(page);
    await markDocument(page);
    const url = page.url();
    const history = await page.evaluate(() => window.history.length);
    await page.locator('#cars').evaluate((grid) => {
        Object.assign(grid, { compareMark: 'kept' });
    });

    // Ticked in this order: the third car, then the first.
    await tick(page, slugs[2]).click();
    await tick(page, slugs[0]).click();
    await expectChosen(page, [slugs[2], slugs[0]]);
    await expect(parts.drawer).toBeHidden();

    const requests = await recordCompareRequests(page);
    const drawerPath = await anchorPath(page, DRAWER);
    expect(drawerPath).not.toBe('');
    await parts.go.click();
    await expect(parts.drawer).toBeVisible();
    await expect.poll(() => requests.length).toBe(1);
    const [request] = requests;
    expect(new URL(request.url).pathname + new URL(request.url).search).toBe(`/compare?car=${slugs[2]}&car=${slugs[0]}`);
    expect(request.tesseraRequest).toBe('visit');
    expect(request.targets).toBe(`${drawerPath} @${DRAWER}`);
    expect(request.status).toBe(200);
    expect(request.contentType).toMatch(/^text\/vnd\.tessera\+html/);
    expect(request.templates).toEqual([drawerPath]);

    // The address and history are as they were; the list is the same node in the same document.
    expect(page.url()).toBe(url);
    expect(await page.evaluate(() => window.history.length)).toBe(history);
    expect(await documentKept(page)).toBe(true);
    expect(await page.locator('#cars').evaluate((grid) => (grid as Element & { compareMark?: string }).compareMark)).toBe('kept');

    // The cars side by side, in the order ticked, each with what the store shows of it and its first payment.
    await expect.poll(() => comparedTitles(parts.table)).toEqual(['2023 Comparo C3', '2023 Comparo C1']);
    await expect(parts.table.locator('thead th a').first()).toHaveAttribute('href', `/cars/${slugs[2]}`);
    const apr = profileApr();
    const rows: Record<string, string[]> = {
        Photo: ['No photo yet', 'No photo yet'],
        Year: ['2023', '2023'],
        Make: [MAKE, MAKE],
        Model: ['C3', 'C1'],
        Trim: ['Trim 3', 'Trim 1'],
        Mileage: ['3,000 miles', '1,000 miles'],
        Price: [dollars(compareCar(3).listPrice), dollars(compareCar(1).listPrice)],
        'Monthly payment': [3, 1].map((n) => {
            const price = compareCar(n).listPrice;

            return figures({ down: Math.floor(price / 1000) * 100, trade: 0, apr, months: 60 }, price).payment;
        }),
    };

    for (const [fact, cells] of Object.entries(rows)) {
        await expect(parts.table.locator('tbody tr').filter({ has: page.locator('th', { hasText: fact }) }).locator('td')).toHaveText(cells);
    }

    // A click inside the drawer leaves it open; a click outside the dock closes it.
    await parts.table.locator('caption').click();
    await expect(parts.drawer).toBeVisible();
    await page.locator('#store-header').click({ position: { x: 5, y: 5 } });
    await expect(parts.drawer).toBeHidden();

    // After another tick, Compare sends a new visit and the drawer shows the new cars; Close closes it.
    await tick(page, slugs[3]).click();
    await expectChosen(page, [slugs[2], slugs[0], slugs[3]]);
    await parts.go.click();
    await expect(parts.drawer).toBeVisible();
    await expect.poll(() => requests.length).toBe(2);
    await expect.poll(() => comparedTitles(parts.table)).toEqual(['2023 Comparo C3', '2023 Comparo C1', '2023 Comparo C4']);
    await parts.close.click();
    await expect(parts.drawer).toBeHidden();
    expect(page.url()).toBe(url);
    expect(errors).toEqual([]);
});
