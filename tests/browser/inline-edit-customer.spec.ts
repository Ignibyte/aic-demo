// Inline edit of a customer's row (#28): a click on the phone asks for that row as a small form, Enter writes the phone
// with `@put`, and Escape asks for the row back. Each exchange is one Tessera action naming the row alone and answered
// with that row alone; the row beside it stays the node it was.

import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { collectFormErrors, recordRequests } from './support/forms';
import { insertCustomer, storedCustomer, testCustomer } from './support/customers';
import {
    CUSTOMERS, customerRow, EMAILS, expectFocusedAndSelected, expectRowAction, expectRowWrite, expectRowsKept, markRows,
    removeInlineLooks,
} from './support/inline-edit';
import { ready } from './support/tessera';

test.beforeEach(() => removeInlineLooks());
test.afterEach(() => removeInlineLooks());

test('a customer is edited in its row: the phone saved with Enter, Escape saving nothing', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectFormErrors(page);
    const customer = testCustomer('Inline Edited', EMAILS.edited);
    const id = insertCustomer(customer);
    const besideId = insertCustomer(testCustomer('Inline Beside', EMAILS.beside));
    const path = `/app/customers/${id}`;
    const anchor = `customer-${id}`;
    await signIn(page);
    await page.goto(CUSTOMERS);
    await ready(page);
    const row = customerRow(page, id);
    const beside = customerRow(page, besideId);
    await expect(row.locator('a', { hasText: customer.phone })).toHaveAttribute('href', `${path}/edit`);
    await markRows(page);
    const requests = await recordRequests(page);

    // A click on the phone asks for the row as a form, the phone focused with its text selected.
    await row.locator('a', { hasText: customer.phone }).click();
    await expectRowAction(requests, 'GET', `${path}/row/edit`, anchor, 200);
    const phone = page.locator(`#phone-${anchor}`);
    await expectFocusedAndSelected(phone);
    await expect(phone).toHaveValue(customer.phone);

    // Typing replaces it; Enter saves it, and the row comes back shown with it.
    await page.keyboard.type('+1 503 555 0101');
    await phone.press('Enter');
    expectRowWrite(await expectRowAction(requests, 'PUT', `${path}/row`, anchor, 200), { phone: '+1 503 555 0101' });
    await expect(row.locator('input')).toHaveCount(0);
    await expect(row).toContainText('+1 503 555 0101');
    expect(storedCustomer(EMAILS.edited)).toEqual({ id, ...customer, phone: '+1 503 555 0101' });

    // Escape asks for the row back with what is stored, whatever was typed.
    await row.locator('a', { hasText: '+1 503 555 0101' }).click();
    await expectRowAction(requests, 'GET', `${path}/row/edit`, anchor, 200);
    await page.keyboard.type('555');
    await phone.press('Escape');
    await expectRowAction(requests, 'GET', `${path}/row`, anchor, 200);
    await expect(row.locator('input')).toHaveCount(0);
    await expect(row).toContainText('+1 503 555 0101');
    expect(storedCustomer(EMAILS.edited)?.phone).toBe('+1 503 555 0101');

    // The row beside it never changed, and the list stayed where it was.
    await expectRowsKept([beside, row]);
    expect(storedCustomer(EMAILS.beside)).toEqual({ id: besideId, ...testCustomer('Inline Beside', EMAILS.beside) });
    await expect(page).toHaveURL(CUSTOMERS);
    expect(requests).toEqual([]);
    expect(errors).toEqual([]);
});
