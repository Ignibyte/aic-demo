// Inline edit of a vehicle's row (#28): a click on its price or its status asks the server for that row as a small form,
// Enter or Save writes it with `@put`, and Escape or Cancel asks for the row back. Each exchange is one Tessera action
// naming the row alone and answered with that row alone; the row beside it stays the node it was.

import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { collectFormErrors, recordRequests } from './support/forms';
import {
    expectFocusedAndSelected, expectRowAction, expectRowWrite, expectRowsKept, markRows, removeInlineLooks, VEHICLES,
    vehicleRow, VINS,
} from './support/inline-edit';
import { ready } from './support/tessera';
import { insertVehicle, storedVehicle, testVehicle } from './support/vehicles';

const VIN = VINS.edited;
const PATH = `/app/inventory/${VIN}`;
const ANCHOR = `vehicle-${VIN}`;

test.beforeEach(() => removeInlineLooks());
test.afterEach(() => removeInlineLooks());

test('a vehicle is edited in its row: the price saved with Enter, the status with Save, Escape and Cancel saving nothing', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectFormErrors(page);
    insertVehicle(testVehicle(VIN));
    insertVehicle(testVehicle(VINS.beside));
    await signIn(page);
    await page.goto(VEHICLES);
    await ready(page);
    const row = vehicleRow(page, VIN);
    const beside = vehicleRow(page, VINS.beside);
    await expect(row.locator('a', { hasText: '$17,900' })).toHaveAttribute('href', `${PATH}/edit`);
    await markRows(page);
    const requests = await recordRequests(page);

    // A click on the price asks for the row as a form: the price focused with its text selected, the status a select.
    await row.locator('a', { hasText: '$17,900' }).click();
    await expectRowAction(requests, 'GET', `${PATH}/row/edit`, ANCHOR, 200);
    const price = page.locator(`#list_price-${ANCHOR}`);
    const status = page.locator(`#status-${ANCHOR}`);
    await expectFocusedAndSelected(price);
    await expect(price).toHaveValue('17900');
    await expect(status).toHaveValue('in_stock');

    // Typing replaces the selected price; Enter saves it, and the row comes back shown with it.
    await page.keyboard.type('18250');
    await price.press('Enter');
    const saved = await expectRowAction(requests, 'PUT', `${PATH}/row`, ANCHOR, 200);
    expectRowWrite(saved, { list_price: '18250', status: 'in_stock' });
    await expect(row.locator('input, select')).toHaveCount(0);
    await expect(row).toContainText('$18,250');
    expect(storedVehicle(VIN)).toEqual({ ...testVehicle(VIN), listPrice: 18250 });

    // A click on the status asks for the form again; Save sends the chosen status.
    await row.locator('a', { hasText: 'In stock' }).click();
    await expectRowAction(requests, 'GET', `${PATH}/row/edit`, ANCHOR, 200);
    await status.selectOption('on_hold');
    await row.getByRole('button', { name: 'Save' }).click();
    expectRowWrite(await expectRowAction(requests, 'PUT', `${PATH}/row`, ANCHOR, 200), { list_price: '18250', status: 'on_hold' });
    await expect(row.locator('.badge')).toHaveText('On hold');
    expect(storedVehicle(VIN)?.status).toBe('on_hold');

    // Escape asks for the row back with what is stored, whatever was typed.
    await row.locator('a', { hasText: '$18,250' }).click();
    await expectRowAction(requests, 'GET', `${PATH}/row/edit`, ANCHOR, 200);
    await page.keyboard.type('99999');
    await price.press('Escape');
    await expectRowAction(requests, 'GET', `${PATH}/row`, ANCHOR, 200);
    await expect(row.locator('input, select')).toHaveCount(0);
    await expect(row).toContainText('$18,250');

    // Cancel does the same.
    await row.locator('a', { hasText: 'On hold' }).click();
    await expectRowAction(requests, 'GET', `${PATH}/row/edit`, ANCHOR, 200);
    await status.selectOption('sold');
    await row.getByRole('button', { name: 'Cancel' }).click();
    await expectRowAction(requests, 'GET', `${PATH}/row`, ANCHOR, 200);
    await expect(row.locator('.badge')).toHaveText('On hold');
    expect(storedVehicle(VIN)).toEqual({ ...testVehicle(VIN), listPrice: 18250, status: 'on_hold' });

    // The row beside it never changed, and the list stayed where it was.
    await expectRowsKept([beside, row]);
    expect(storedVehicle(VINS.beside)).toEqual(testVehicle(VINS.beside));
    await expect(page).toHaveURL(VEHICLES);
    expect(requests).toEqual([]);
    expect(errors).toEqual([]);
});
