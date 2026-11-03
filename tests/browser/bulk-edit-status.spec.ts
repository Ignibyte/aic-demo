// Bulk edit of the inventory list (#29): ticked rows set to a status at once. The bar waits for a tick, counts the
// ticked rows, and Apply sends one Tessera action naming each ticked row's anchor and the totals line's; the answer is
// those fragments alone, the rows show the new status and the totals follow, and every other row stays the node it
// was. A refresh of the results that shows the rows again starts the selection empty.

import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { collectFormErrors, recordRequests } from './support/forms';
import { expectRowsKept, markRows } from './support/inline-edit';
import { box, expectBulkAction, LIST, removeBulkLooks, row, sentFields, stored, VINS } from './support/bulk-edit';
import { ready } from './support/tessera';
import { insertVehicle, testVehicle } from './support/vehicles';

test.beforeEach(() => removeBulkLooks());
test.afterEach(() => removeBulkLooks());

test('ticked rows are set On hold by one action answered with those rows and the totals alone', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectFormErrors(page);
    insertVehicle(testVehicle(VINS.first));
    insertVehicle(testVehicle(VINS.second));
    insertVehicle(testVehicle(VINS.beside));
    await signIn(page);
    await page.goto(LIST);
    await ready(page);
    const apply = page.locator('#vehicles-bulk-apply');
    const count = page.locator('#vehicles-bulk-count');
    const pageBox = page.getByRole('checkbox', { name: 'Select every vehicle on this page' });

    // Nothing ticked: the bar's controls wait, and no count shows.
    await expect(apply).toBeDisabled();
    await expect(page.locator('#vehicles-bulk-choice')).toBeDisabled();
    await expect(count).toBeHidden();

    // Ticking rows counts them and opens the bar; the page's box ticks every row, and unticks them.
    await box(page, VINS.first).check();
    await expect(count).toHaveText('1 selected');
    await expect(apply).toBeEnabled();
    await pageBox.check();
    await expect(count).toHaveText('3 selected');
    await expect(box(page, VINS.beside)).toBeChecked();
    await pageBox.uncheck();
    await expect(count).toBeHidden();
    await expect(box(page, VINS.first)).not.toBeChecked();
    await expect(apply).toBeDisabled();

    await box(page, VINS.first).check();
    await box(page, VINS.second).check();
    await expect(count).toHaveText('2 selected');
    await expect(pageBox).not.toBeChecked();
    await markRows(page);
    const requests = await recordRequests(page);

    // Apply sends one action naming the two rows and the totals, and the answer is those three fragments.
    await page.locator('#vehicles-bulk-choice').selectOption('status:on_hold');
    await apply.click();
    const request = await expectBulkAction(requests, [VINS.first, VINS.second], 200);
    const fields = sentFields(request);
    expect(fields['vins[]'].sort()).toEqual([VINS.first, VINS.second].sort());
    expect(fields.bulk).toEqual(['status:on_hold']);
    expect(fields.return[0]).toMatch(/^\/app\/inventory\?/);
    expect(fields.confirmed).toBeUndefined();
    expect(request.writeId).toMatch(/^[0-9a-f-]{36}$/);
    expect(request.headers['x-aic-csrf']).toMatch(/^[0-9a-f]{64}$/);

    // The rows show On hold, unticked; the totals follow; the selection is empty; no row was replaced.
    await expect(row(page, VINS.first).locator('.badge')).toHaveText('On hold');
    await expect(row(page, VINS.second).locator('.badge')).toHaveText('On hold');
    await expect(row(page, VINS.beside).locator('.badge')).toHaveText('In stock');
    await expect(box(page, VINS.first)).not.toBeChecked();
    await expect(box(page, VINS.second)).not.toBeChecked();
    await expect(count).toBeHidden();
    await expect(page.locator('#vehicles-table-summary')).toHaveText('3 vehicles');
    await expect(page.locator('#vehicles-table-counts')).toHaveText('1 in stock · 2 on hold');
    await expect(page.locator('#vehicles-table-outcome')).toHaveText('Set 2 vehicles to On hold.');
    await expectRowsKept([row(page, VINS.first), row(page, VINS.second), row(page, VINS.beside)]);
    expect(stored(VINS.first).status).toBe('on_hold');
    expect(stored(VINS.second).status).toBe('on_hold');
    expect(stored(VINS.beside).status).toBe('in_stock');
    expect(page.url()).toContain(LIST);

    // A sort refreshes the results: the rows come back and the selection starts empty.
    await box(page, VINS.beside).check();
    await expect(count).toHaveText('1 selected');
    await page.locator('#vehicles-table thead th', { hasText: 'Year' }).getByRole('link').click();
    await expect(page.locator('#vehicles-table thead th', { hasText: 'Year' })).toHaveAttribute('aria-sort', 'ascending');
    await expect(count).toBeHidden();
    await expect(box(page, VINS.beside)).not.toBeChecked();
    await expect(apply).toBeDisabled();
    expect(errors).toEqual([]);
});
