// A refused value in a row (#28) answers 422 with that row alone, still a form: the field marked invalid, its message
// beside it, and focus on it. The row's save runs the record's full form's checks, so the full vehicle form refuses a
// list price below the cost with the same message.

import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { collectFormErrors, recordRequests } from './support/forms';
import { insertCustomer, storedCustomer, testCustomer } from './support/customers';
import {
    CUSTOMERS, customerRow, customerWritten, EMAILS, expectRowAction, removeInlineLooks, VEHICLES, vehicleRow,
    vehicleWritten, VINS,
} from './support/inline-edit';
import { ready } from './support/tessera';
import { insertVehicle, storedVehicle, testVehicle } from './support/vehicles';

const VIN = VINS.edited;
const PATH = `/app/inventory/${VIN}`;
const ANCHOR = `vehicle-${VIN}`;
const BELOW_COST = 'The list price is below the cost, $15,100.';
const NOT_WHOLE = 'The list price is whole dollars, 0 or more.';
const BAD_PHONE = 'A phone is 7 to 30 digits, spaces and + ( ) - . characters, with at least 7 digits.';

test.beforeEach(() => removeInlineLooks());
test.afterEach(() => removeInlineLooks());

test('a price below the cost, or not whole dollars, is refused in the row and nothing is saved', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectFormErrors(page);
    insertVehicle(testVehicle(VIN));
    const written = vehicleWritten(VIN);
    await signIn(page);
    await page.goto(VEHICLES);
    await ready(page);
    const row = vehicleRow(page, VIN);
    const requests = await recordRequests(page);
    await row.locator('a', { hasText: '$17,900' }).click();
    await expectRowAction(requests, 'GET', `${PATH}/row/edit`, ANCHOR, 200);
    const price = page.locator(`#list_price-${ANCHOR}`);

    // Below the cost, sent with Save: the row as a form again, the price marked, its message beside it, and focus back
    // on the price though Save was clicked.
    await page.keyboard.type('15000');
    await row.getByRole('button', { name: 'Save' }).click();
    await expectRowAction(requests, 'PUT', `${PATH}/row`, ANCHOR, 422);
    await expect(price).toHaveAttribute('aria-invalid', 'true');
    await expect(price).toHaveAttribute('aria-describedby', `error-list_price-${ANCHOR}`);
    await expect(page.locator(`#error-list_price-${ANCHOR}`)).toHaveText(BELOW_COST);
    await expect(price).toHaveValue('15000');
    await expect(price).toBeFocused();

    // Not whole dollars, sent with Enter: refused the same way, with its own message.
    await price.fill('17,9x');
    await price.press('Enter');
    await expectRowAction(requests, 'PUT', `${PATH}/row`, ANCHOR, 422);
    await expect(page.locator(`#error-list_price-${ANCHOR}`)).toHaveText(NOT_WHOLE);
    await expect(price).toHaveValue('17,9x');
    await expect(price).toBeFocused();

    // Nothing was saved: the stored vehicle, and the time Postgres last wrote it, are as they were.
    expect(storedVehicle(VIN)).toEqual(testVehicle(VIN));
    expect(vehicleWritten(VIN)).toBe(written);
    expect(errors).toEqual([]);
});

test('a phone the customer form refuses is refused in the row and nothing is saved', async ({ page }) => {
    const errors = collectFormErrors(page);
    const customer = testCustomer('Inline Refused', EMAILS.edited);
    const id = insertCustomer(customer);
    const written = customerWritten(id);
    const anchor = `customer-${id}`;
    await signIn(page);
    await page.goto(CUSTOMERS);
    await ready(page);
    const row = customerRow(page, id);
    const requests = await recordRequests(page);
    await row.locator('a', { hasText: customer.phone }).click();
    await expectRowAction(requests, 'GET', `/app/customers/${id}/row/edit`, anchor, 200);
    const phone = page.locator(`#phone-${anchor}`);

    await page.keyboard.type('12');
    await phone.press('Enter');
    await expectRowAction(requests, 'PUT', `/app/customers/${id}/row`, anchor, 422);
    await expect(phone).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator(`#error-phone-${anchor}`)).toHaveText(BAD_PHONE);
    await expect(phone).toHaveValue('12');
    await expect(phone).toBeFocused();

    expect(storedCustomer(EMAILS.edited)).toEqual({ id, ...customer });
    expect(customerWritten(id)).toBe(written);
    expect(errors).toEqual([]);
});

test('the full vehicle form refuses a price below the cost with the same message', async ({ page }) => {
    const errors = collectFormErrors(page);
    insertVehicle(testVehicle(VIN));
    await signIn(page);
    await page.goto(`${PATH}/edit`);
    await ready(page);

    await page.locator('#vehicle-list_price').fill('15000');
    await page.locator('#vehicle-save').click();
    await expect(page.locator('#vehicle-list_price')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#vehicle-list_price-error')).toHaveText(BELOW_COST);
    await expect(page).toHaveURL(`${PATH}/edit`);
    expect(storedVehicle(VIN)).toEqual(testVehicle(VIN));
    expect(errors).toEqual([]);
});
