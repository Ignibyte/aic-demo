import { expect, test, type Page } from '@playwright/test';
import { signIn } from './support/inventory';
import { insertCustomer, testCustomer } from './support/customers';
import { fillSale, login, today } from './support/sales';
import {
    EMAILS, VINS, collectFormErrors, expectRefusedInPlace, ownCustomers, ownSales, ownVehicles, recordRequests, removeFormLooks, type SentRequest,
} from './support/forms';
import { expectMarksKept, markPage, ready } from './support/tessera';
import { fillVehicleForm, insertVehicle, testVehicle } from './support/vehicles';

test.beforeEach(() => removeFormLooks());
test.afterEach(() => removeFormLooks());

// Opens a form, waits for Tessera and marks the page; the record then holds only what the form asks for.
async function openForm(page: Page, requests: SentRequest[], path: string): Promise<void> {
    await page.goto(path);
    await ready(page);
    await markPage(page);
    requests.length = 0;
}

// The refusal landed in the same document: its summary has the focus, the URL is the form's, and the marks are kept.
async function expectAnsweredInPlace(page: Page, summary: string, path: string): Promise<void> {
    await expect(page.locator(summary)).toHaveText('Fix the fields marked below.');
    await expect(page.locator(summary)).toBeFocused();
    expect(new URL(page.url()).pathname + new URL(page.url()).search).toBe(path);
    await expectMarksKept(page);
}

test('a refused vehicle, customer and sale form are each answered in place, the error summary focused', async ({ page }) => {
    const errors = collectFormErrors(page);
    await signIn(page);
    const requests = await recordRequests(page);

    // The vehicle form, its VIN malformed: one submit, answered 422 with `content`, the VIN marked and the rest kept.
    await openForm(page, requests, '/app/inventory/new');
    await fillVehicleForm(page, { ...testVehicle(VINS.added), vin: 'NOTAVIN' });
    await page.locator('#vehicle-save').click();
    await expectRefusedInPlace(requests, '/app/inventory');
    await expectAnsweredInPlace(page, '#vehicle-error', '/app/inventory/new');
    await expect(page.locator('[aria-invalid="true"]')).toHaveCount(1);
    await expect(page.locator('#vehicle-vin')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#vehicle-vin-error')).toHaveText('A VIN is 17 letters and digits, without I, O or Q.');
    await expect(page.locator('#vehicle-vin')).toHaveValue('NOTAVIN');
    await expect(page.locator('#vehicle-model')).toHaveValue('Hatch');
    expect(ownVehicles()).toBe(0);

    // The customer form with no name.
    await openForm(page, requests, '/app/customers/new');
    await page.locator('#customer-email').fill(EMAILS.added);
    await page.locator('#customer-save').click();
    await expectRefusedInPlace(requests, '/app/customers');
    await expectAnsweredInPlace(page, '#customer-error', '/app/customers/new');
    await expect(page.locator('[aria-invalid="true"]')).toHaveCount(1);
    await expect(page.locator('#customer-name-error')).toHaveText("Enter the customer's name.");
    await expect(page.locator('#customer-email')).toHaveValue(EMAILS.added);
    expect(ownCustomers()).toBe(0);

    // The sale form, its price 0.
    insertVehicle(testVehicle(VINS.sold));
    insertCustomer(testCustomer('Form Buyer', EMAILS.buyer));
    const salePath = `/app/sales/new?vehicle=${VINS.sold}`;
    await openForm(page, requests, salePath);
    await fillSale(page, { customer: EMAILS.buyer, salesperson: login, price: 0, soldOn: today() });
    await page.locator('#sale-save').click();
    await expectRefusedInPlace(requests, '/app/sales');
    await expectAnsweredInPlace(page, '#sale-error', salePath);
    await expect(page.locator('[aria-invalid="true"]')).toHaveCount(1);
    await expect(page.locator('#sale-price-error')).toHaveText('The sale price is whole dollars, from 1 to 9,999,999.');
    await expect(page.locator('#sale-vehicle')).toHaveValue(VINS.sold);
    expect(ownSales()).toBe(0);

    expect(errors).toEqual([]);
});
