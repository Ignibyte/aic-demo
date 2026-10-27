import { expect, test, type Page } from '@playwright/test';
import { signIn } from './support/inventory';
import { fillCustomerForm, insertCustomer, storedCustomer, testCustomer } from './support/customers';
import { fillSale, login, storedSales, today } from './support/sales';
import { EMAILS, VINS, collectFormErrors, customerId, ownCustomers, ownSales, ownVehicles, recordRequests, removeFormLooks, type SentRequest } from './support/forms';
import { fillVehicleForm, insertVehicle, storedVehicle, testVehicle } from './support/vehicles';

test.use({ javaScriptEnabled: false });

test.beforeEach(() => removeFormLooks());
test.afterEach(() => removeFormLooks());

// Sends the form with its button and waits for the page it leads to.
async function send(page: Page, requests: SentRequest[], button: string): Promise<void> {
    requests.length = 0;
    await Promise.all([page.waitForLoadState('load'), page.locator(button).click()]);
}

// The submit was a plain document POST to the action, with no Tessera header, answered with this status.
function expectPosted(request: SentRequest | undefined, action: string, status: number): void {
    expect(request?.method).toBe('POST');
    expect(request?.resourceType).toBe('document');
    expect(request?.path).toBe(action);
    expect(request?.tesseraRequest).toBe('');
    expect(request?.writeId).toBe('');
    expect(request?.status).toBe(status);
}

// A refused post: the whole page again, 422, with the summary and the field's message.
async function expectRefused(page: Page, requests: SentRequest[], action: string, summary: string, error: string, message: string): Promise<void> {
    await expect.poll(() => requests.length).toBe(1);
    expectPosted(requests[0], action, 422);
    expect(requests[0]?.contentType).toMatch(/^text\/html/);
    await expect(page.locator(summary)).toHaveText('Fix the fields marked below.');
    await expect(page.locator(error)).toHaveText(message);
}

// A saved post: 303 to the record's page, which the browser then loads (the route answers the post as the server did,
// and the browser follows the redirect itself).
async function expectSaved(page: Page, requests: SentRequest[], action: string, location: RegExp): Promise<string> {
    await expect.poll(() => requests.length).toBe(1);
    const [post] = requests;
    expectPosted(post, action, 303);
    expect(post?.location).toMatch(location);
    await expect(page).toHaveURL(new RegExp(`${post?.location}$`));

    return post?.location ?? '';
}

test('with JavaScript off the vehicle, customer and sale forms post as documents and still work', async ({ page }) => {
    const errors = collectFormErrors(page);
    await signIn(page);
    const requests = await recordRequests(page);

    // The vehicle form: a bad VIN refused, then the vehicle added.
    const vehicle = testVehicle(VINS.plain);
    await page.goto('/app/inventory/new');
    await fillVehicleForm(page, { ...vehicle, vin: 'NOTAVIN' });
    await send(page, requests, '#vehicle-save');
    await expectRefused(page, requests, '/app/inventory', '#vehicle-error', '#vehicle-vin-error', 'A VIN is 17 letters and digits, without I, O or Q.');
    expect(ownVehicles()).toBe(0);
    await page.locator('#vehicle-vin').fill(VINS.plain);
    await send(page, requests, '#vehicle-save');
    await expectSaved(page, requests, '/app/inventory', new RegExp(`^/app/inventory/${VINS.plain}$`));
    await expect(page.locator('#vehicle-notice')).toHaveText('Added this vehicle.');
    expect(storedVehicle(VINS.plain)).toEqual(vehicle);

    // The customer form: no name refused, then the customer added.
    const customer = testCustomer('Form Plain', EMAILS.plain);
    await page.goto('/app/customers/new');
    await fillCustomerForm(page, { ...customer, name: '' });
    await send(page, requests, '#customer-save');
    await expectRefused(page, requests, '/app/customers', '#customer-error', '#customer-name-error', "Enter the customer's name.");
    expect(ownCustomers()).toBe(0);
    await page.locator('#customer-name').fill(customer.name);
    await send(page, requests, '#customer-save');
    const customerPath = await expectSaved(page, requests, '/app/customers', /^\/app\/customers\/\d+$/);
    await expect(page.locator('#customer-notice')).toHaveText('Added this customer.');
    expect(customerPath).toBe(`/app/customers/${customerId(EMAILS.plain)}`);
    expect(storedCustomer(EMAILS.plain)).toEqual({ id: customerId(EMAILS.plain), ...customer });

    // The sale form: a price of 0 refused, then the sale recorded.
    insertVehicle(testVehicle(VINS.plainSold));
    insertCustomer(testCustomer('Form Buyer', EMAILS.buyer));
    const sale = { customer: EMAILS.buyer, salesperson: login, price: 15900, soldOn: today() };
    await page.goto(`/app/sales/new?vehicle=${VINS.plainSold}`);
    await fillSale(page, { ...sale, price: 0 });
    await send(page, requests, '#sale-save');
    await expectRefused(page, requests, '/app/sales', '#sale-error', '#sale-price-error', 'The sale price is whole dollars, from 1 to 9,999,999.');
    expect(ownSales()).toBe(0);
    await page.locator('#sale-price').fill(String(sale.price));
    await send(page, requests, '#sale-save');
    const salePath = await expectSaved(page, requests, '/app/sales', /^\/app\/sales\/\d+$/);
    await expect(page.locator('#sale-notice')).toHaveText('Recorded the sale.');
    const stored = storedSales(VINS.plainSold);
    expect(stored).toEqual([{ id: expect.any(Number), ...sale }]);
    expect(salePath).toBe(`/app/sales/${stored[0]?.id}`);

    expect(errors).toEqual([]);
});
