// Bulk edit of the inventory list (#29): ticked rows moved to a lot location at once, by one Tessera action answered
// with those rows and the totals line alone. The location is the vehicle's own column, which only a bulk move writes;
// nothing else of the vehicles changes.

import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { collectFormErrors, recordRequests } from './support/forms';
import { expectRowsKept, markRows } from './support/inline-edit';
import { box, expectBulkAction, LIST, removeBulkLooks, row, sentFields, stored, VINS } from './support/bulk-edit';
import { ready } from './support/tessera';
import { insertVehicle, storedVehicle, testVehicle } from './support/vehicles';

test.beforeEach(() => removeBulkLooks());
test.afterEach(() => removeBulkLooks());

// A row's Location cell: the tenth of its cells.
const location = (page: Parameters<typeof row>[0], vin: string) => row(page, vin).locator('td').nth(9);

test('ticked rows are moved to the back lot by one action, and only their location changes', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectFormErrors(page);
    insertVehicle(testVehicle(VINS.first));
    insertVehicle(testVehicle(VINS.second, 'on_hold'));
    insertVehicle(testVehicle(VINS.beside));
    await signIn(page);
    await page.goto(LIST);
    await ready(page);
    await expect(page.locator('#vehicles-table thead th').nth(9)).toHaveText('Location');
    await expect(location(page, VINS.first)).toHaveText('—');
    const before = { first: storedVehicle(VINS.first), second: storedVehicle(VINS.second) };

    await box(page, VINS.first).check();
    await box(page, VINS.second).check();
    await markRows(page);
    const requests = await recordRequests(page);
    await page.locator('#vehicles-bulk-choice').selectOption('location:back_lot');
    await page.locator('#vehicles-bulk-apply').click();
    const request = await expectBulkAction(requests, [VINS.first, VINS.second], 200);
    expect(sentFields(request).bulk).toEqual(['location:back_lot']);

    // The two rows say Back lot, their statuses as they were; the row beside them has no location yet.
    await expect(location(page, VINS.first)).toHaveText('Back lot');
    await expect(location(page, VINS.second)).toHaveText('Back lot');
    await expect(location(page, VINS.beside)).toHaveText('—');
    await expect(row(page, VINS.second).locator('.badge')).toHaveText('On hold');
    await expect(page.locator('#vehicles-table-outcome')).toHaveText('Moved 2 vehicles to Back lot.');
    await expect(page.locator('#vehicles-table-counts')).toHaveText('2 in stock · 1 on hold');
    await expect(page.locator('#vehicles-bulk-count')).toBeHidden();
    await expectRowsKept([row(page, VINS.first), row(page, VINS.second), row(page, VINS.beside)]);

    // Postgres holds the location, and every other value as it was.
    expect(stored(VINS.first).location).toBe('back_lot');
    expect(stored(VINS.second).location).toBe('back_lot');
    expect(stored(VINS.beside).location).toBe('');
    expect(storedVehicle(VINS.first)).toEqual(before.first);
    expect(storedVehicle(VINS.second)).toEqual(before.second);

    // A row's own save (#28) keeps the location, and shows it back.
    await row(page, VINS.first).locator('a', { hasText: '$17,900' }).click();
    await expect(page.locator(`#list_price-vehicle-${VINS.first}`)).toBeFocused();
    await expect(location(page, VINS.first)).toHaveText('Back lot');
    await page.locator(`#list_price-vehicle-${VINS.first}`).fill('18100');
    await page.locator(`#list_price-vehicle-${VINS.first}`).press('Enter');
    await expect(row(page, VINS.first).locator('a', { hasText: '$18,100' })).toBeVisible();
    await expect(location(page, VINS.first)).toHaveText('Back lot');
    expect(stored(VINS.first).location).toBe('back_lot');
    expect(errors).toEqual([]);
});
