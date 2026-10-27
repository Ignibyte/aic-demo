import { expect, test } from '@playwright/test';
import { VINS, insertVehicle, removeVins, signInAt, storedVehicle, testVehicle } from './support/vehicles';

const sold = testVehicle(VINS.sold, 'sold');

// Plain links and posts: the delete posted anyway must go as the browser sends a form.
test.use({ javaScriptEnabled: false });

test.beforeEach(() => {
    removeVins(sold.vin);
    insertVehicle(sold);
});
test.afterEach(() => removeVins(sold.vin));

test('a sold vehicle cannot be deleted, even by a delete posted for it anyway', async ({ page }) => {
    await signInAt(page, `/app/inventory/${sold.vin}`);
    await expect(page.locator('#vehicle-status')).toHaveText('Sold');
    await page.locator('#vehicle-delete').click();

    await expect(page.locator('#vehicle-sold')).toHaveText('This vehicle is sold, so it stays on the record and cannot be deleted.');
    await expect(page.locator('#vehicle-delete-confirm')).toHaveCount(0);
    await expect(page.locator('#vehicle-delete-form')).toHaveCount(0);

    // Post a delete anyway, from the browser so the session goes with it: the edit form already carries a token and an
    // envelope sealing this vehicle's path, which a delete accepts, so it is pointed at the delete route.
    await page.goto(`/app/inventory/${sold.vin}/edit`);
    await page.locator('#vehicle-form').evaluate((form, action) => form.setAttribute('action', action), `/app/inventory/${sold.vin}/delete`);
    const answer = page.waitForResponse((response) => response.request().method() === 'POST');
    await page.locator('#vehicle-save').click();

    expect((await answer).status()).toBe(422);
    await expect(page.locator('#vehicle-sold')).toBeVisible();
    expect(storedVehicle(sold.vin)).toEqual(sold);
});
