import { expect, test } from '@playwright/test';
import { VINS, insertVehicle, removeVins, signInAt, storedVehicle, testVehicle } from './support/vehicles';

const doomed = testVehicle(VINS.deleted);

test.beforeEach(() => {
    removeVins(doomed.vin);
    insertVehicle(doomed);
});
test.afterEach(() => removeVins(doomed.vin));

test('deleting a vehicle asks first, and then the vehicle is gone', async ({ page }) => {
    await signInAt(page, `/app/inventory/${doomed.vin}`);
    await page.locator('#vehicle-delete').click();
    await expect(page).toHaveURL(new RegExp(`/app/inventory/${doomed.vin}/delete$`));
    await expect(page.getByRole('heading', { name: 'Delete the 2023 Probe Hatch SE?' })).toBeVisible();
    await expect(page.getByText(`VIN ${doomed.vin}`)).toBeVisible();

    await page.getByRole('link', { name: 'Keep it' }).click();
    await expect(page).toHaveURL(new RegExp(`/app/inventory/${doomed.vin}$`));
    expect(storedVehicle(doomed.vin)).toEqual(doomed);

    await page.locator('#vehicle-delete').click();
    await page.locator('#vehicle-delete-confirm').click();

    await expect(page).toHaveURL(/\/app\/inventory$/);
    await expect(page.locator('#inventory-notice')).toHaveText(`Deleted the 2023 Probe Hatch SE, VIN ${doomed.vin}.`);
    expect(storedVehicle(doomed.vin)).toBeNull();

    const answer = await page.goto(`/app/inventory/${doomed.vin}`);
    expect(answer?.status()).toBe(404);
    await expect(page.locator('#vehicle-missing')).toBeVisible();
});
