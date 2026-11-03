// The payment calculator under AIC's profiler (#26): its toolbar lists every exchange this browser makes, and while the
// calculator is used on a car's page it lists none; the next Tessera visit is then the one line it adds. The staff
// vehicle page, which shows the calculator at the APR settings' plugin gives and names the dealership in its footer,
// reads the dealership's profile once. gate:23 runs it in its profiled pass only; it skips on a site without the
// profiler.

import { expect, test } from '@playwright/test';
import { VINS, insertCar, walkCalculator, profileApr } from './support/finance';
import { signIn } from './support/inventory';
import { collectProfilerErrors, exchangeLine, exchanges, expectExchange, openTab, skipUnlessProfiled, toolbar } from './support/profiler';
import { slugOf } from './support/storefront';
import { ready } from './support/tessera';
import { removeVins } from './support/vehicles';

test.beforeEach(async ({ request }) => {
    await skipUnlessProfiled(request);
    removeVins(VINS.profiled);
    insertCar(VINS.profiled);
});
test.afterEach(() => removeVins(VINS.profiled));

test('the toolbar lists no exchange while the calculator is used, then the next visit alone', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectProfilerErrors(page);
    const carPage = `/cars/${slugOf(VINS.profiled)}`;
    await page.goto(carPage);
    await ready(page);

    // The toolbar's stream has the page's own profile once its summary shows the load.
    await toolbar(page).locator('summary').click();
    await expect(toolbar(page).locator('summary')).toHaveText(exchangeLine('AIC · load', 'GET', carPage, '', '200'));

    // The whole walk, then time for the stream, which reads the profiles once a second, to show anything it would.
    await walkCalculator(page, profileApr());
    await page.waitForTimeout(3_000);
    await expect(exchanges(page, /./)).toHaveCount(0);

    // A visit to the list is the next exchange, and the only one.
    await page.getByRole('link', { name: 'All cars for sale' }).click();
    await expect(page).toHaveURL(/\/cars$/);
    await expectExchange(page, exchangeLine('visit', 'GET', '/cars', '\\S', '200'), 1);
    await expect(exchanges(page, /./)).toHaveCount(1);

    expect(errors).toEqual([]);
});

test("the vehicle's page reads the dealership's profile once for its calculator and its footer", async ({ page }) => {
    await signIn(page);
    await page.goto(`/app/inventory/${VINS.profiled}`);
    await ready(page);
    await toolbar(page).locator('summary').click();
    await toolbar(page).getByRole('link', { name: "This page's profile" }).click();
    await expect(page).toHaveURL(/\/_profiler\/[0-9a-f]{32}$/);
    await expect(page.locator('h2').first()).toHaveText(`GET /app/inventory/${VINS.profiled}`);
    await openTab(page, 'Database');
    await expect(page.locator('table.aic-table tbody td', { hasText: /^SELECT .* FROM dealership_profile / })).toHaveCount(1);
});
