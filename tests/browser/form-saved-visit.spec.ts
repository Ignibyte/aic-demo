import { expect, test, type Page } from '@playwright/test';
import { signIn } from './support/inventory';
import { fillCustomerForm, insertCustomer, storedCustomer, testCustomer } from './support/customers';
import { fillSale, login, storedSales, today, vehicleStatus } from './support/sales';
import { EMAILS, VINS, collectFormErrors, customerId, expectSavedVisit, recordRequests, removeFormLooks, type SentRequest } from './support/forms';
import { expectMarksKept, markPage, ready } from './support/tessera';
import { fillVehicleForm, insertVehicle, storedVehicle, testVehicle } from './support/vehicles';

test.beforeEach(() => removeFormLooks());
test.afterEach(() => removeFormLooks());

// Opens a form, waits for Tessera and marks the page; the record then holds only what the form asks for.
async function openForm(page: Page, requests: SentRequest[], path: string): Promise<void> {
    await page.goto(path);
    await ready(page);
    await markPage(page);
    requests.length = 0;
}

// The save landed on the record's page in the same document: the URL is the location, its notice shows, the marks are
// kept, and no document was loaded on the way.
async function expectLanded(page: Page, location: string, notice: string, message: string): Promise<void> {
    await expect(page).toHaveURL(new RegExp(`${location.replaceAll('?', '\\?')}$`));
    await expect(page.locator(notice)).toHaveText(message);
    await expectMarksKept(page);
}

test('a saved vehicle, customer and sale form each land on the record\'s page without a new document', async ({ page }) => {
    const errors = collectFormErrors(page);
    await signIn(page);
    const requests = await recordRequests(page);

    // A vehicle added: the submit is answered with its page's location, and Tessera visits it.
    const added = testVehicle(VINS.added);
    await openForm(page, requests, '/app/inventory/new');
    await fillVehicleForm(page, added);
    await page.locator('#vehicle-save').click();
    let location = await expectSavedVisit(requests, '/app/inventory', new RegExp(`^/app/inventory/${VINS.added}$`));
    await expectLanded(page, location, '#vehicle-notice', 'Added this vehicle.');
    expect(storedVehicle(VINS.added)).toEqual(added);

    // A vehicle edited.
    insertVehicle(testVehicle(VINS.edited));
    await openForm(page, requests, `/app/inventory/${VINS.edited}/edit`);
    await page.locator('#vehicle-model').fill('Wagon');
    await page.locator('#vehicle-save').click();
    location = await expectSavedVisit(requests, `/app/inventory/${VINS.edited}`, new RegExp(`^/app/inventory/${VINS.edited}$`));
    await expectLanded(page, location, '#vehicle-notice', 'Saved your changes.');
    expect(storedVehicle(VINS.edited)).toEqual({ ...testVehicle(VINS.edited), model: 'Wagon' });

    // A customer added.
    const customer = testCustomer('Form Added', EMAILS.added);
    await openForm(page, requests, '/app/customers/new');
    await fillCustomerForm(page, customer);
    await page.locator('#customer-save').click();
    location = await expectSavedVisit(requests, '/app/customers', /^\/app\/customers\/\d+$/);
    await expectLanded(page, location, '#customer-notice', 'Added this customer.');
    expect(location).toBe(`/app/customers/${customerId(EMAILS.added)}`);
    expect(storedCustomer(EMAILS.added)).toEqual({ id: customerId(EMAILS.added), ...customer });

    // A customer edited.
    const editedId = insertCustomer(testCustomer('Form Edited', EMAILS.edited));
    await openForm(page, requests, `/app/customers/${editedId}/edit`);
    await page.locator('#customer-phone').fill('(503) 555-0199');
    await page.locator('#customer-save').click();
    location = await expectSavedVisit(requests, `/app/customers/${editedId}`, new RegExp(`^/app/customers/${editedId}$`));
    await expectLanded(page, location, '#customer-notice', 'Saved your changes.');
    expect(storedCustomer(EMAILS.edited)).toEqual({ id: editedId, ...testCustomer('Form Edited', EMAILS.edited), phone: '(503) 555-0199' });

    // A sale recorded.
    insertVehicle(testVehicle(VINS.sold));
    insertCustomer(testCustomer('Form Buyer', EMAILS.buyer));
    const sale = { customer: EMAILS.buyer, salesperson: login, price: 17400, soldOn: today() };
    await openForm(page, requests, `/app/sales/new?vehicle=${VINS.sold}`);
    await fillSale(page, sale);
    await page.locator('#sale-save').click();
    location = await expectSavedVisit(requests, '/app/sales', /^\/app\/sales\/\d+$/);
    await expectLanded(page, location, '#sale-notice', 'Recorded the sale.');
    const stored = storedSales(VINS.sold);
    expect(stored).toEqual([{ id: expect.any(Number), ...sale }]);
    expect(location).toBe(`/app/sales/${stored[0]?.id}`);
    expect(vehicleStatus(VINS.sold)).toBe('sold');

    expect(errors).toEqual([]);
});
