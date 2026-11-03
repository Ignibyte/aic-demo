// A buyer's payment calculator on a car's page (#26): the server's defaults (the price, 10% down, 60 months and the
// dealership's APR), then the figures following every keystroke in each field with no request made, the range and the
// number field in step, the range's max at the price, the cover note and the warning above 3% of the price a month.

import { expect, test } from '@playwright/test';
import { VINS, calculator, insertCar, profileApr, recordRequests, walkCalculator } from './support/finance';
import { slugOf } from './support/storefront';
import { collectErrors, ready } from './support/tessera';
import { removeVins } from './support/vehicles';

test.beforeEach(() => {
    removeVins(VINS.store);
    insertCar(VINS.store);
});
test.afterEach(() => removeVins(VINS.store));

test("a buyer works out a payment on the car's page, all in the browser", async ({ page }) => {
    const errors = collectErrors(page);
    await page.goto(`/cars/${slugOf(VINS.store)}`);
    await ready(page);

    // The calculator sits in the car's card, under its price.
    await expect(page.locator('article #finance')).toBeVisible();
    await expect(calculator(page).root).toHaveAttribute('data-signals', `{price: 17900, down: 1700, trade: 0, term: 60, apr: ${Math.round(profileApr() * 100)} / 100, warn: 3}`);

    const requests = recordRequests(page);
    await walkCalculator(page, profileApr());
    expect(requests).toEqual([]);
    expect(errors).toEqual([]);
});
