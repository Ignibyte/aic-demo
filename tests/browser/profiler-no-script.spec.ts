// AIC's profiler with JavaScript off (#25): every whole page still carries the toolbar, whose link opens the page's
// profile, and its tabs are plain links. The profiler showed Lotline repeating statements on these pages; each now
// runs every statement once. gate:23 runs it in its profiled pass only; it skips on a site without the profiler.

import { expect, test, type Page } from '@playwright/test';
import { fillSignIn } from './support/inventory';
import { UNREPEATED, openTab, skipUnlessProfiled, toolbar } from './support/profiler';

test.use({ javaScriptEnabled: false });

test.beforeEach(async ({ request }) => {
    await skipUnlessProfiled(request);
});

// Opens the toolbar on the page shown, follows its link to the page's profile, and checks the profile names the
// page and repeats no statement.
async function expectOwnProfile(page: Page, path: string): Promise<void> {
    await expect(toolbar(page).locator('summary')).toHaveText('AIC · profiling');
    await toolbar(page).locator('summary').click();
    await toolbar(page).getByRole('link', { name: "This page's profile" }).click();
    await expect(page).toHaveURL(/\/_profiler\/[0-9a-f]{32}$/);
    await expect(page.locator('h2').first()).toHaveText(`GET ${path}`);
    await expect(page.locator('main p').first()).toHaveText(/^200 · Page · /);
    await openTab(page, 'Database');
    await expect(page.locator('table.aic-table caption')).toHaveText(UNREPEATED);
}

test('with JavaScript off each page carries the toolbar, its profile opens, and no statement repeats', async ({ page }) => {
    await page.goto('/sign-in?next=%2Fapp%2Finventory');
    await expectOwnProfile(page, '/sign-in');

    await page.goto('/sign-in?next=%2Fapp%2Finventory');
    await fillSignIn(page);

    for (const path of ['/app/inventory', '/app', '/app/customers', '/app/sales', '/', '/cars']) {
        await page.goto(path);
        await expectOwnProfile(page, path);
    }
});
