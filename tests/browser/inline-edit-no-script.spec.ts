// With JavaScript off (#28), the cells a click edits in the row are plain links to the record's edit page, and
// following one loads that page as a whole document.

import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { insertCustomer, testCustomer } from './support/customers';
import { CUSTOMERS, customerRow, EMAILS, removeInlineLooks, VEHICLES, vehicleRow, VINS } from './support/inline-edit';
import { insertVehicle, testVehicle } from './support/vehicles';

const VIN = VINS.edited;

test.use({ javaScriptEnabled: false });
test.beforeEach(() => removeInlineLooks());
test.afterEach(() => removeInlineLooks());

test('the price and status cells link to the vehicle edit page', async ({ page }) => {
    insertVehicle(testVehicle(VIN));
    await signIn(page);

    for (const cell of ['$17,900', 'In stock']) {
        await page.goto(VEHICLES);
        await vehicleRow(page, VIN).locator('a', { hasText: cell }).click();
        await expect(page).toHaveURL(`/app/inventory/${VIN}/edit`);
        await expect(page.locator('#vehicle-list_price')).toHaveValue('17900');
        await expect(page.locator('#vehicle-status')).toHaveValue('in_stock');
    }
});

test('the phone cell links to the customer edit page', async ({ page }) => {
    const customer = testCustomer('Inline Plain', EMAILS.edited);
    const id = insertCustomer(customer);
    await signIn(page);
    await page.goto(CUSTOMERS);
    await customerRow(page, id).locator('a', { hasText: customer.phone }).click();
    await expect(page).toHaveURL(`/app/customers/${id}/edit`);
    await expect(page.locator('#customer-phone')).toHaveValue(customer.phone);
});
