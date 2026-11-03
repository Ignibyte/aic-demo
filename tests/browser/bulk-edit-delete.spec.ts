// Bulk edit of the inventory list (#29): ticked rows deleted at once, after a confirm card. Delete opens the card and
// sends nothing, Keep them closes it, and Delete them sends one Tessera action with the confirmation; AIC answers each
// deleted row's target removed (AIC #247), so the rows leave the page, the row beside them stays as it was, and the
// totals line's count goes down. A deleted vehicle's photo file goes too.

import { existsSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { collectFormErrors, recordRequests } from './support/forms';
import { expectRowsKept, markRows } from './support/inline-edit';
import { box, expectBulkAction, LIST, present, removeBulkLooks, row, sentFields, VINS } from './support/bulk-edit';
import { addPhoto, FIXTURES, photoCount, photoFiles, vehiclePath } from './support/photos';
import { ready } from './support/tessera';
import { insertVehicle, testVehicle } from './support/vehicles';

test.beforeEach(() => removeBulkLooks());
test.afterEach(() => removeBulkLooks());

test('ticked rows are deleted after the confirm card and leave the page, the count down', async ({ page }) => {
    test.setTimeout(90_000);
    const errors = collectFormErrors(page);
    insertVehicle(testVehicle(VINS.first));
    insertVehicle(testVehicle(VINS.second));
    insertVehicle(testVehicle(VINS.beside));
    await signIn(page);

    // The first vehicle has a photo, uploaded as staff upload one.
    await page.goto(vehiclePath(VINS.first));
    await ready(page);
    await addPhoto(page, FIXTURES.png);
    await expect.poll(() => photoCount(VINS.first)).toBe(1);
    const [photo] = photoFiles(VINS.first);
    expect(existsSync(photo.path)).toBe(true);

    await page.goto(LIST);
    await ready(page);
    await expect(page.locator('#vehicles-table-summary')).toHaveText('3 vehicles');
    await box(page, VINS.first).check();
    await box(page, VINS.second).check();
    await markRows(page);
    const requests = await recordRequests(page);
    const card = page.locator('#vehicles-bulk-confirm');
    await expect(card).toBeHidden();

    // Delete opens the card, counting the ticked rows, and sends nothing; Keep them closes it.
    await page.locator('#vehicles-bulk-choice').selectOption('delete');
    await page.locator('#vehicles-bulk-apply').click();
    await expect(card).toBeVisible();
    await expect(card.locator('#vehicles-bulk-question')).toHaveText('Delete the ticked vehicles?');
    await expect(card.locator('#vehicles-bulk-ticked')).toHaveText('2 ticked');
    await expect(page.locator('#vehicles-bulk-delete')).toBeFocused();
    await page.locator('#vehicles-bulk-keep').click();
    await expect(card).toBeHidden();
    expect(requests).toEqual([]);
    expect(present([VINS.first, VINS.second])).toBe(2);

    // Delete them sends one action, confirmed, answered with the two rows' targets removed and the totals.
    await page.locator('#vehicles-bulk-apply').click();
    await expect(card).toBeVisible();
    await page.locator('#vehicles-bulk-delete').click();
    const request = await expectBulkAction(requests, [VINS.first, VINS.second], 200);
    const fields = sentFields(request);
    expect(fields.bulk).toEqual(['delete']);
    expect(fields.confirmed).toEqual(['yes']);
    const rowPaths = request.targets.split(',').map((entry) => entry.trim().split(' '))
        .filter((entry) => entry[entry.length - 1] !== '@inventory-totals').map((entry) => entry[0]);
    expect([...request.removed].sort()).toEqual(rowPaths.sort());

    for (const vin of [VINS.first, VINS.second]) {
        await expect(row(page, vin)).toHaveCount(0);
    }

    await expect(card).toBeHidden();
    await expect(page.locator('#vehicles-table-summary')).toHaveText('1 vehicle');
    await expect(page.locator('#vehicles-table-outcome')).toHaveText('Deleted 2 vehicles.');
    await expect(page.locator('#vehicles-bulk-count')).toBeHidden();
    await expect(box(page, VINS.beside)).not.toBeChecked();
    await expectRowsKept([row(page, VINS.beside)]);
    expect(present([VINS.first, VINS.second])).toBe(0);
    expect(present([VINS.beside])).toBe(1);

    if (photo.held === 1) {
        expect(existsSync(photo.path)).toBe(false);
    }

    // The list's next read leaves them out.
    await page.reload();
    await expect(page.locator('#vehicles-table-summary')).toHaveText('1 vehicle');
    await expect(row(page, VINS.first)).toHaveCount(0);
    expect(errors).toEqual([]);
});
