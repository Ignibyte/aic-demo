// Bulk edit of the inventory list (#29): a refusal for any ticked row refuses the whole batch. Each answer is 422 with
// the ticked rows, the refused ones set apart with why and every box still ticked, and the totals line naming each
// refused row by VIN; Postgres and the queue are as they were. Refused: a status for a vehicle with a recorded sale
// (the vehicle form's own check), a move or a delete of a sold vehicle, and the status sold, which a sale gives.

import { expect, test, type Page } from '@playwright/test';
import { signIn } from './support/inventory';
import { collectFormErrors, recordRequests, type SentRequest } from './support/forms';
import { box, expectBulkAction, LIST, insertRecordedSale, queuedAudits, removeBulkLooks, row, stored, VINS } from './support/bulk-edit';
import { ready } from './support/tessera';
import { insertVehicle, testVehicle } from './support/vehicles';

test.beforeEach(() => removeBulkLooks());
test.afterEach(() => removeBulkLooks());

// What the database holds of each of the looks' vehicles, to hold it unchanged.
function snapshot(): Record<string, ReturnType<typeof stored>> {
    return Object.fromEntries([VINS.first, VINS.second, VINS.sold].map((vin) => [vin, stored(vin)]));
}

// Applies the bar's choice and holds the answer to a refusal of the batch: 422 with the ticked rows and the totals, the
// refused row set apart with its reason and the other ticked row not, every box still ticked, the totals line naming
// the refused rows, and nothing written or queued.
async function expectRefused(
    page: Page,
    requests: SentRequest[],
    ticked: string[],
    reasons: Record<string, string>,
): Promise<void> {
    const before = snapshot();
    await page.locator('#vehicles-bulk-apply').click();
    await expectBulkAction(requests, ticked, 422);
    const outcome = page.locator('#vehicles-table-outcome');
    await expect(outcome).toHaveAttribute('role', 'alert');
    await expect(outcome).toContainText('Nothing was changed.');

    for (const vin of ticked) {
        await expect(box(page, vin)).toBeChecked();

        if (vin in reasons) {
            await expect(page.locator(`#refused-${vin}`)).toHaveText(reasons[vin]);
            await expect(box(page, vin)).toHaveAttribute('aria-invalid', 'true');
            await expect(outcome).toContainText(`VIN ${vin}: ${reasons[vin]}`);
        } else {
            await expect(box(page, vin)).not.toHaveAttribute('aria-invalid', 'true');
            await expect(outcome).not.toContainText(vin);
        }
    }

    await expect(page.locator('#vehicles-bulk-count')).toHaveText(`${ticked.length} selected`);
    expect(snapshot()).toEqual(before);
    expect(queuedAudits()).toBe(0);
}

test('a refused row refuses the whole batch: 422 naming it, every box still ticked, nothing written', async ({ page }) => {
    test.setTimeout(90_000);
    const errors = collectFormErrors(page);
    insertVehicle(testVehicle(VINS.first));
    insertVehicle(testVehicle(VINS.second));
    insertVehicle(testVehicle(VINS.sold));
    insertRecordedSale(VINS.sold);
    await signIn(page);
    await page.goto(LIST);
    await ready(page);
    await box(page, VINS.first).check();
    await box(page, VINS.sold).check();
    const requests = await recordRequests(page);

    // A status for a vehicle with a recorded sale: the vehicle form's own check refuses it.
    await page.locator('#vehicles-bulk-choice').selectOption('status:on_hold');
    await expectRefused(page, requests, [VINS.first, VINS.sold], {
        [VINS.sold]: 'This vehicle has a recorded sale. Void the sale to put it back on the lot.',
    });

    // A move of a sold vehicle.
    await page.locator('#vehicles-bulk-choice').selectOption('location:showroom');
    await expectRefused(page, requests, [VINS.first, VINS.sold], { [VINS.sold]: 'It is sold and has left the lot.' });

    // A delete of a sold vehicle, confirmed in the card.
    await page.locator('#vehicles-bulk-choice').selectOption('delete');
    await page.locator('#vehicles-bulk-apply').click();
    await expect(page.locator('#vehicles-bulk-confirm')).toBeVisible();
    expect(requests).toEqual([]);
    const before = snapshot();
    await page.locator('#vehicles-bulk-delete').click();
    await expectBulkAction(requests, [VINS.first, VINS.sold], 422);
    await expect(page.locator(`#refused-${VINS.sold}`)).toHaveText('It is sold, so it stays on the record.');
    await expect(page.locator('#vehicles-bulk-confirm')).toBeHidden();
    await expect(box(page, VINS.first)).toBeChecked();
    expect(snapshot()).toEqual(before);
    expect(queuedAudits()).toBe(0);

    // The status sold, which the bar does not offer, posted for two vehicles in stock: refused for each.
    await box(page, VINS.sold).uncheck();
    await box(page, VINS.second).check();
    await page.locator('#vehicles-bulk-choice').evaluate((select: HTMLSelectElement) => {
        select.add(new Option('Set to Sold', 'status:sold'));
    });
    await page.locator('#vehicles-bulk-choice').selectOption('status:sold');
    const sale = 'A sale marks a vehicle sold: record the sale on its page.';
    await expectRefused(page, requests, [VINS.first, VINS.second], { [VINS.first]: sale, [VINS.second]: sale });
    await expect(row(page, VINS.first).locator('.badge')).toHaveText('In stock');
    expect(errors).toEqual([]);
});
