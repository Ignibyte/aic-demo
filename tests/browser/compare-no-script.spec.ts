// The comparison with JavaScript off (#33): the ticks are plain checkboxes in the bar's GET form, whose Compare button
// loads `/compare?car=…&car=…` as a plain page with the cars side by side; four ticked answer the 422 page.

import { expect, test } from '@playwright/test';
import { MAKE, comparedTitles, compareHref, dock, insertCompareCars, removeCompareCars, tick } from './support/compare';
import { pathOf } from './support/tessera';

let slugs: string[] = [];

test.use({ javaScriptEnabled: false });

test.beforeEach(() => {
    removeCompareCars();
    slugs = insertCompareCars(4);
});
test.afterEach(() => removeCompareCars());

test('with JavaScript off the ticks are a GET form to a plain compare page', async ({ page }) => {
    const parts = dock(page);
    await page.goto(`/cars?make=${MAKE}`);

    // The form's own button shows; what needs JavaScript does not.
    await expect(parts.bar).toBeVisible();
    await expect(parts.submit).toBeVisible();
    await expect(parts.go).toBeHidden();
    await expect(parts.clear).toBeHidden();
    await expect(parts.full).toBeHidden();
    await expect(parts.drawer).toBeHidden();
    await expect(tick(page, slugs[0])).toHaveAttribute('form', 'compare-form');

    await tick(page, slugs[1]).check();
    await tick(page, slugs[0]).check();
    await Promise.all([page.waitForURL(/\/compare\?/), parts.submit.click()]);
    expect(pathOf(page.url())).toBe(compareHref([slugs[0], slugs[1]]));
    await expect(page.locator('#compare-title')).toHaveText('Compare cars');
    await expect.poll(() => comparedTitles(page.locator('main #compare-table'))).toEqual(['2023 Comparo C1', '2023 Comparo C2']);

    // Four ticked on one page answer the 422 page.
    await page.goto(`/cars?make=${MAKE}`);

    for (const slug of slugs) {
        await tick(page, slug).check();
    }

    const [response] = await Promise.all([page.waitForResponse((answer) => new URL(answer.url()).pathname === '/compare'), parts.submit.click()]);
    expect(response.status()).toBe(422);
    await expect(page.locator('#compare-title')).toHaveText('Compare up to three cars at a time.');
    await expect(page.locator('#compare-table')).toHaveCount(0);
});
