import { expect, test } from '@playwright/test';
import { signIn, sql } from './support/inventory';
import { VINS, expectFacts, fillVehicleForm, removeVins, storedVehicle, testVehicle } from './support/vehicles';

const vehicle = { ...testVehicle(VINS.added, 'on_hold'), year: 2024, make: 'Probe Motors', model: 'Wagon', trim: 'LX', mileage: 12345, listPrice: 23450, cost: 19999 };

test.beforeEach(() => removeVins(VINS.added));
test.afterEach(() => removeVins(VINS.added));

test('adding a vehicle lands on its detail page, which never shows the cost, and the list links to it', async ({ page }) => {
    await signIn(page);
    await page.getByRole('link', { name: 'Add a vehicle' }).click();
    await expect(page).toHaveURL(/\/app\/inventory\/new$/);
    await expect(page.locator('#vehicle-status')).toHaveValue('in_stock');
    await expect(page.locator('#vehicle-added_on')).toHaveValue(sql("SELECT to_char(current_date, 'YYYY-MM-DD')")[0]);

    await fillVehicleForm(page, vehicle);
    await page.locator('#vehicle-save').click();

    await expect(page).toHaveURL(new RegExp(`/app/inventory/${vehicle.vin}$`));
    await expect(page.locator('#vehicle-notice')).toHaveText('Added this vehicle.');
    await expect(page.locator('#vehicle-title')).toHaveText('2024 Probe Motors Wagon LX');
    await expect(page.locator('#vehicle-status')).toHaveText('On hold');
    await expectFacts(page, [vehicle.vin, '2024', 'Probe Motors', 'Wagon', 'LX', 'Hatchback', 'Slate', '12,345 mi', '$23,450', '2026-09-15']);
    await expect(page.locator('body')).not.toContainText('19,999');
    await expect(page.locator('body')).not.toContainText('19999');
    expect(storedVehicle(vehicle.vin)).toEqual(vehicle);

    await page.reload();
    await expect(page.locator('#vehicle-notice')).toHaveCount(0);

    await page.goto(`/app/inventory?q=${vehicle.vin}`);
    const link = page.locator('#vehicles-table').getByRole('link', { name: vehicle.vin });
    await expect(link).toHaveAttribute('href', `/app/inventory/${vehicle.vin}`);
    await link.click();
    await expect(page).toHaveURL(new RegExp(`/app/inventory/${vehicle.vin}$`));
    await expect(page.locator('#vehicle-title')).toHaveText('2024 Probe Motors Wagon LX');
});
