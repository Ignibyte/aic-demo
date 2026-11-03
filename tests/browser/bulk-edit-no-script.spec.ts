// Bulk edit of the inventory list (#29) with JavaScript off: the boxes post with the action bar as a plain form. Apply
// lands back on the searched list with a notice; a delete answers with the ticked rows and the bar asking first, and
// Delete them lands back with a notice; a refusal answers 422 with the rows and the reason.

import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { BULK, box, LIST, insertRecordedSale, present, removeBulkLooks, row, stored, VINS } from './support/bulk-edit';
import { insertVehicle, testVehicle } from './support/vehicles';

test.use({ javaScriptEnabled: false });
test.beforeEach(() => removeBulkLooks());
test.afterEach(() => removeBulkLooks());

test('without script the bar posts plainly: back to the list with a notice, a page that asks first, a 422', async ({ page }) => {
    test.setTimeout(90_000);
    insertVehicle(testVehicle(VINS.first));
    insertVehicle(testVehicle(VINS.second));
    insertVehicle(testVehicle(VINS.sold));
    insertRecordedSale(VINS.sold);
    await signIn(page);
    await page.goto(LIST);

    // The page's box, the count and the card need script; the bar is a plain form, open.
    await expect(page.getByRole('checkbox', { name: 'Select every vehicle on this page' })).toBeHidden();
    await expect(page.locator('#vehicles-bulk-count')).toBeHidden();
    await expect(page.locator('#vehicles-bulk-confirm')).toBeHidden();
    await expect(page.locator('#vehicles-bulk-apply')).toBeEnabled();

    // Apply posts the ticked boxes and lands back on the searched list, with a notice.
    await box(page, VINS.first).check();
    await box(page, VINS.second).check();
    await page.locator('#vehicles-bulk-choice').selectOption('status:on_hold');
    const [posted] = await Promise.all([
        page.waitForResponse((response) => new URL(response.url()).pathname === BULK),
        page.locator('#vehicles-bulk-apply').click(),
    ]);
    expect(posted.status()).toBe(303);
    await expect(page).toHaveURL(/\/app\/inventory\?.*q=0LTBULKE/);
    await expect(page.getByText('Set 2 vehicles to On hold.')).toBeVisible();
    await expect(row(page, VINS.first).locator('.badge')).toHaveText('On hold');
    expect(stored(VINS.first).status).toBe('on_hold');
    expect(stored(VINS.second).status).toBe('on_hold');

    // Delete answers with the ticked row and the bar asking first; Delete them lands back, the vehicle gone.
    await box(page, VINS.second).check();
    await page.locator('#vehicles-bulk-choice').selectOption('delete');
    const [asked] = await Promise.all([
        page.waitForResponse((response) => new URL(response.url()).pathname === BULK),
        page.locator('#vehicles-bulk-apply').click(),
    ]);
    expect(asked.status()).toBe(200);
    await expect(page.locator('#vehicles-bulk-confirm')).toBeVisible();
    await expect(page.locator('#vehicles-bulk-ticked')).toHaveText('1 ticked');
    await expect(box(page, VINS.second)).toBeChecked();
    await expect(row(page, VINS.first)).toHaveCount(0);
    expect(present([VINS.second])).toBe(1);
    await page.locator('#vehicles-bulk-delete').click();
    await expect(page).toHaveURL(/\/app\/inventory\?.*q=0LTBULKE/);
    await expect(page.getByText('Deleted 1 vehicle.')).toBeVisible();
    await expect(row(page, VINS.second)).toHaveCount(0);
    expect(present([VINS.second])).toBe(0);

    // Keep them, on the page that asks first, goes back to the list and deletes nothing.
    await box(page, VINS.first).check();
    await page.locator('#vehicles-bulk-choice').selectOption('delete');
    await page.locator('#vehicles-bulk-apply').click();
    await expect(page.locator('#vehicles-bulk-confirm')).toBeVisible();
    await page.locator('#vehicles-bulk-keep').click();
    await expect(page).toHaveURL(/\/app\/inventory\?.*q=0LTBULKE/);
    expect(present([VINS.first])).toBe(1);

    // A move of a sold vehicle answers 422, the rows with the reason, nothing written.
    const before = stored(VINS.first);
    await box(page, VINS.first).check();
    await box(page, VINS.sold).check();
    await page.locator('#vehicles-bulk-choice').selectOption('location:back_lot');
    const [refused] = await Promise.all([
        page.waitForResponse((response) => new URL(response.url()).pathname === BULK),
        page.locator('#vehicles-bulk-apply').click(),
    ]);
    expect(refused.status()).toBe(422);
    await expect(page.locator(`#refused-${VINS.sold}`)).toHaveText('It is sold and has left the lot.');
    await expect(page.locator('#vehicles-table-outcome')).toContainText(`VIN ${VINS.sold}: It is sold and has left the lot.`);
    expect(stored(VINS.first)).toEqual(before);
});
