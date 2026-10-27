import { expect, test, type Page } from '@playwright/test';
import { literal, sql } from './support/inventory';
import { insertVehicle, testVehicle } from './support/vehicles';
import {
    BUYER,
    VINS,
    fillSale,
    insertVehicleAndBuyer,
    login,
    recordSale,
    removeSaleLooks,
    saveSale,
    signIn,
    storedSales,
} from './support/sales';

const first = { customer: BUYER, salesperson: login, price: 17000, soldOn: '2026-10-02' };
const second = { customer: BUYER, salesperson: login, price: 16500, soldOn: '2026-10-03' };

// The vehicle is refused beside its field, with the alert above, and every value is as chosen or typed.
async function expectSoldRefused(page: Page, vin: string, typed: typeof second): Promise<void> {
    await expect(page.locator('#sale-error')).toHaveText('Fix the fields marked below.');
    await expect(page.locator('#sale-vehicle')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#sale-vehicle-error')).toHaveText('This vehicle is already sold.');
    await expect(page.locator('[aria-invalid="true"]')).toHaveCount(1);
    await expect(page.locator('#sale-vehicle')).toHaveValue(vin);
    await expect(page.locator('#sale-customer')).toHaveValue(typed.customer);
    await expect(page.locator('#sale-salesperson')).toHaveValue(typed.salesperson);
    await expect(page.locator('#sale-price')).toHaveValue(String(typed.price));
    await expect(page.locator('#sale-sold_on')).toHaveValue(typed.soldOn);
}

test.beforeEach(() => {
    removeSaleLooks();
    insertVehicleAndBuyer(VINS.again);
    insertVehicle(testVehicle(VINS.marked));
});
test.afterEach(() => removeSaleLooks());

test('a vehicle is sold once: a second sale of it is refused in place', async ({ page, context }) => {
    // Two forms for the one vehicle, filled side by side.
    await signIn(page);
    const other = await context.newPage();
    await page.goto(`/app/sales/new?vehicle=${VINS.again}`);
    await other.goto(`/app/sales/new?vehicle=${VINS.again}`);
    await fillSale(page, first);
    await fillSale(other, second);

    await recordSale(page);

    expect(await saveSale(other)).toBe(422);
    await expectSoldRefused(other, VINS.again, second);
    expect(storedSales(VINS.again)).toEqual([{ id: expect.any(Number), ...first }]);

    // A vehicle marked sold while its form was open is refused the same way, and nothing is recorded for it.
    await page.goto(`/app/sales/new?vehicle=${VINS.marked}`);
    await fillSale(page, second);
    sql(`UPDATE vehicles SET status = 'sold' WHERE vin = ${literal(VINS.marked)}`);
    expect(await saveSale(page)).toBe(422);
    await expectSoldRefused(page, VINS.marked, second);
    expect(storedSales(VINS.marked)).toEqual([]);
});
