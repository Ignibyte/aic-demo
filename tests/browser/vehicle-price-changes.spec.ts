// A change of a vehicle's list price is recorded with its save (#31): the full form and the row's save each insert one
// `vehicle_prices` row, the old and new price on the database's day; a save that keeps the price, a new vehicle and a
// bulk status change insert none.

import { expect, test } from '@playwright/test';
import { sql } from './support/inventory';
import { vehicleRow } from './support/inline-edit';
import { insertPanelVehicle, MAKE, priceChanges, removePanelLooks, vin } from './support/panels';
import { ready } from './support/tessera';
import { fillVehicleForm, signInAt, testVehicle } from './support/vehicles';

const VIN = vin('0LTPNPRC2SR31501');
const ADDED = vin('0LTPNPRC2SR31502');

test.beforeEach(() => {
    removePanelLooks();
    insertPanelVehicle({ vin: VIN, listPrice: 30000 });
});
test.afterEach(() => removePanelLooks());

test('the form and the row each record a price change; other saves record none', async ({ page }) => {
    const [today] = sql("SELECT to_char(current_date, 'YYYY-MM-DD')");

    // The full form, its price unchanged: no row.
    await signInAt(page, `/app/inventory/${VIN}/edit`);
    await ready(page);
    await page.locator('#vehicle-mileage').fill('12500');
    await page.locator('#vehicle-save').click();
    await expect(page).toHaveURL(new RegExp(`/app/inventory/${VIN}$`));
    expect(priceChanges(VIN)).toEqual([]);

    // The full form with a new price: one row.
    await page.goto(`/app/inventory/${VIN}/edit`);
    await page.locator('#vehicle-list_price').fill('28750');
    await page.locator('#vehicle-save').click();
    await expect(page).toHaveURL(new RegExp(`/app/inventory/${VIN}$`));
    expect(priceChanges(VIN)).toEqual([`30000>28750@${today}`]);

    // The row's save with a new price: one more.
    await page.goto(`/app/inventory?q=${VIN}`);
    await ready(page);
    const row = vehicleRow(page, VIN);
    await row.locator('a', { hasText: '$28,750' }).click();
    const price = page.locator(`#list_price-vehicle-${VIN}`);
    await expect(price).toBeFocused();
    await page.keyboard.type('27900');
    await price.press('Enter');
    await expect(row).toContainText('$27,900');
    expect(priceChanges(VIN)).toEqual([`30000>28750@${today}`, `28750>27900@${today}`]);

    // A bulk status change: none.
    await row.locator('input[name="vins[]"]').check();
    await page.locator('#vehicles-bulk-choice').selectOption('status:on_hold');
    await page.locator('#vehicles-bulk-apply').click();
    await expect(row).toContainText('On hold');
    expect(priceChanges(VIN)).toHaveLength(2);

    // A new vehicle: none.
    await page.goto('/app/inventory/new');
    await fillVehicleForm(page, { ...testVehicle(ADDED), make: MAKE });
    await page.locator('#vehicle-save').click();
    await expect(page).toHaveURL(new RegExp(`/app/inventory/${ADDED}$`));
    expect(priceChanges(ADDED)).toEqual([]);
});
