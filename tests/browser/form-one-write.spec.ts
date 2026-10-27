import { expect, test, type Page } from '@playwright/test';
import { signIn } from './support/inventory';
import { emailCount, fillCustomerForm, insertCustomer, testCustomer } from './support/customers';
import { fillSale, login, storedSales, today } from './support/sales';
import { EMAILS, VINS, collectFormErrors, expectSavedVisit, recordRequests, removeFormLooks, type SentRequest } from './support/forms';
import { ready } from './support/tessera';
import { insertVehicle, testVehicle } from './support/vehicles';

test.beforeEach(() => removeFormLooks());
test.afterEach(() => removeFormLooks());

// The headers a write is sent again with: Tessera's, the CSRF header, the body's type and the cookie the page sent. The
// cookie goes as it was sent, since the request context keeps a `Secure` cookie off the compiled site's plain http,
// where the browser still sends it.
const REPEATED = [
    'tessera-request', 'tessera-protocol', 'tessera-layout', 'tessera-targets', 'tessera-write-id', 'x-aic-csrf', 'content-type', 'cookie',
];

// Sends a recorded submit again, its write id and its body unchanged, and holds the answer to the first one's.
async function expectRepeatAnswered(page: Page, submit: SentRequest, location: string): Promise<void> {
    const headers = Object.fromEntries(REPEATED.filter((name) => name in submit.headers).map((name) => [name, submit.headers[name]]));
    expect(headers['tessera-write-id']).toBe(submit.writeId);
    expect(headers['cookie']).toContain('aic_session=');
    const answer = await page.request.post(submit.path, { headers, data: submit.body, maxRedirects: 0 });
    expect(answer.status()).toBe(200);
    expect(answer.headers()['tessera-location']).toBe(location);
}

// Double-clicks Save and gives the one submit it sent, after Tessera visited the record's page.
async function saveTwice(page: Page, requests: SentRequest[], button: string, action: string, location: RegExp): Promise<{ submit: SentRequest; location: string }> {
    await page.locator(button).dblclick();
    await expect.poll(() => requests.length).toBe(2);
    const [submit] = requests;
    const landed = await expectSavedVisit(requests, action, location);
    await expect(page).toHaveURL(new RegExp(`${landed}$`));
    // Nothing else is sent once the page has landed.
    await page.waitForTimeout(500);
    expect(requests).toEqual([]);

    return { submit, location: landed };
}

test('a double click on Save records one write, and a repeat of its write id is answered from the ledger', async ({ page }) => {
    const errors = collectFormErrors(page);
    await signIn(page);
    const requests = await recordRequests(page);

    // A customer: one submit, one row; the same submit again gets the first answer and no second row.
    await page.goto('/app/customers/new');
    await ready(page);
    requests.length = 0;
    await fillCustomerForm(page, testCustomer('Form Twice', EMAILS.twice));
    const customer = await saveTwice(page, requests, '#customer-save', '/app/customers', /^\/app\/customers\/\d+$/);
    expect(emailCount(EMAILS.twice)).toBe(1);
    await expectRepeatAnswered(page, customer.submit, customer.location);
    expect(emailCount(EMAILS.twice)).toBe(1);

    // A sale: one submit, one sale; the same submit again records nothing more.
    insertVehicle(testVehicle(VINS.twice));
    insertCustomer(testCustomer('Form Buyer', EMAILS.buyer));
    await page.goto(`/app/sales/new?vehicle=${VINS.twice}`);
    await ready(page);
    requests.length = 0;
    await fillSale(page, { customer: EMAILS.buyer, salesperson: login, price: 16900, soldOn: today() });
    const sale = await saveTwice(page, requests, '#sale-save', '/app/sales', /^\/app\/sales\/\d+$/);
    expect(storedSales(VINS.twice)).toHaveLength(1);
    await expectRepeatAnswered(page, sale.submit, sale.location);
    expect(storedSales(VINS.twice)).toHaveLength(1);

    expect(errors).toEqual([]);
});
