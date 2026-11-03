// Inline edit under AIC's profiler (#28): the toolbar lists each exchange a row makes, as the server recorded it, with
// the row's anchor as its only target: the form asked for, the save, the refusal and the row asked back. No profile of
// them reads the list: none pages it, searches it, counts it whole or reads its makes. gate:23 runs it in its profiled
// pass only; it skips on a site without the profiler.

import { expect, test, type Page } from '@playwright/test';
import { signIn } from './support/inventory';
import { insertCustomer, testCustomer } from './support/customers';
import { CUSTOMERS, customerRow, EMAILS, removeInlineLooks, VEHICLES, vehicleRow, VINS } from './support/inline-edit';
import { collectProfilerErrors, exchangeLine, expectExchange, expectProfile, skipUnlessProfiled, toolbar } from './support/profiler';
import { ready } from './support/tessera';
import { insertVehicle, testVehicle } from './support/vehicles';

const VIN = VINS.edited;
const PATH = `/app/inventory/${VIN}`;

// What a read of a list looks like in a profile's Database tab: a page of it, a search of it, its count with no
// condition, or the makes its filter offers.
const LIST_READ = /LIMIT 15|ILIKE|DISTINCT vehicles\.make|AS total FROM (vehicles|customers)$/;

// The toolbar's line for a row's action, which names the row alone: after `→`, one target (no comma), ending in its
// anchor, then the figures as `exchangeLine` writes them.
function rowLine(method: string, path: string, anchor: string, status: string): RegExp {
    return new RegExp(`^action ${method} ${path.replaceAll('/', '\\/')} → [^,·]* @${anchor} · ${status} · \\d+\\.\\d ms · \\d+ SQL · \\d+ bytes$`);
}

// Signs in and opens a list, its toolbar summing up the list's own load. The toolbar stays shut while the look edits,
// since open it lies over the table.
async function openList(page: Page, path: string, listed: string): Promise<void> {
    await signIn(page);
    await page.goto(path);
    await ready(page);
    await expect(toolbar(page).locator('summary')).toHaveText(exchangeLine('AIC · load', 'GET', listed, '', '200'));
}

// Opens the toolbar to read its list of exchanges.
async function openToolbar(page: Page): Promise<void> {
    await toolbar(page).locator('summary').click();
    await expect(toolbar(page).locator('details')).toHaveAttribute('open', '');
}

test.beforeEach(async ({ request }) => {
    await skipUnlessProfiled(request);
    removeInlineLooks();
});
test.afterEach(() => removeInlineLooks());

test('the toolbar lists each exchange of a vehicle row with the row alone, and none of them reads the list', async ({ page }) => {
    test.setTimeout(90_000);
    const errors = collectProfilerErrors(page);
    insertVehicle(testVehicle(VIN));
    await openList(page, VEHICLES, '/app/inventory');
    const row = vehicleRow(page, VIN);
    const anchor = `vehicle-${VIN}`;
    const price = page.locator(`#list_price-${anchor}`);

    // The form asked for, then the save with Enter.
    await row.locator('a', { hasText: '$17,900' }).click();
    await expect(price).toBeFocused();
    await page.keyboard.type('18250');
    await price.press('Enter');
    await expect(row).toContainText('$18,250');

    // The form again, a refusal below the cost, and Escape for the row back.
    await row.locator('a', { hasText: '$18,250' }).click();
    await expect(price).toBeFocused();
    await page.keyboard.type('100');
    await price.press('Enter');
    await expect(price).toHaveAttribute('aria-invalid', 'true');
    await price.press('Escape');
    await expect(row.locator('input')).toHaveCount(0);

    await openToolbar(page);
    const form = rowLine('GET', `${PATH}/row/edit`, anchor, '200');
    await expectProfile(page, await expectExchange(page, form, 1), `GET ${PATH}/row/edit`, '200', { absent: LIST_READ });
    await expectProfile(page, await expectExchange(page, form, 2), `GET ${PATH}/row/edit`, '200', { absent: LIST_READ });
    const saved = rowLine('PUT', `${PATH}/row`, anchor, '200');
    await expectProfile(page, await expectExchange(page, saved, 1), `PUT ${PATH}/row`, '200', { absent: LIST_READ });
    const refused = rowLine('PUT', `${PATH}/row`, anchor, '422');
    await expectProfile(page, await expectExchange(page, refused, 1), `PUT ${PATH}/row`, '422', { absent: LIST_READ });
    const back = rowLine('GET', `${PATH}/row`, anchor, '200');
    await expectProfile(page, await expectExchange(page, back, 1), `GET ${PATH}/row`, '200', { absent: LIST_READ });
    expect(errors).toEqual([]);
});

test('the toolbar lists each exchange of a customer row with the row alone, and none of them reads the list', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectProfilerErrors(page);
    const customer = testCustomer('Inline Profiled', EMAILS.edited);
    const id = insertCustomer(customer);
    await openList(page, CUSTOMERS, '/app/customers');
    const row = customerRow(page, id);
    const anchor = `customer-${id}`;
    const path = `/app/customers/${id}`;
    const phone = page.locator(`#phone-${anchor}`);

    await row.locator('a', { hasText: customer.phone }).click();
    await expect(phone).toBeFocused();
    await page.keyboard.type('(503) 555-0102');
    await phone.press('Enter');
    await expect(row).toContainText('(503) 555-0102');

    await openToolbar(page);
    const form = rowLine('GET', `${path}/row/edit`, anchor, '200');
    await expectProfile(page, await expectExchange(page, form, 1), `GET ${path}/row/edit`, '200', { absent: LIST_READ });
    const saved = rowLine('PUT', `${path}/row`, anchor, '200');
    await expectProfile(page, await expectExchange(page, saved, 1), `PUT ${path}/row`, '200', { absent: LIST_READ });
    expect(errors).toEqual([]);
});
