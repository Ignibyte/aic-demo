// The payment calculator with JavaScript off (#26): on the car's page and on the vehicle's page the calculator stays
// hidden, and the server's table of example payments shows instead, over 36, 48 and 60 months at the default down
// payment and the dealership's APR.

import { expect, test } from '@playwright/test';
import { VINS, calculator, expectExamples, insertCar, profileApr } from './support/finance';
import { fillSignIn } from './support/inventory';
import { slugOf } from './support/storefront';
import { removeVins } from './support/vehicles';

test.use({ javaScriptEnabled: false });

test.beforeEach(() => {
    removeVins(VINS.noScript);
    insertCar(VINS.noScript);
});
test.afterEach(() => removeVins(VINS.noScript));

test('with JavaScript off both pages show the example payments in place of the calculator', async ({ page }) => {
    const apr = profileApr();

    await page.goto(`/cars/${slugOf(VINS.noScript)}`);
    await expect(calculator(page).root).toBeVisible();
    await expectExamples(page, apr);

    await page.goto('/sign-in?next=%2Fapp%2Finventory');
    await fillSignIn(page);
    await page.goto(`/app/inventory/${VINS.noScript}`);
    await expect(calculator(page).root).toBeVisible();
    await expectExamples(page, apr);
});
