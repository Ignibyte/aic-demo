import { expect, test } from '@playwright/test';
import { EMAILS, insertCustomer, removeTestCustomers, signInAt, storedCustomer, testCustomer } from './support/customers';

const edited = testCustomer('Probe Edited', EMAILS.edited);
const other = testCustomer('Probe Other', EMAILS.other);
let editedId = 0;
let otherId = 0;

test.beforeEach(() => {
    removeTestCustomers();
    editedId = insertCustomer(edited);
    otherId = insertCustomer(other);
});
test.afterEach(() => removeTestCustomers());

test("editing a customer changes its page and its row", async ({ page }) => {
    await signInAt(page, `/app/customers/${editedId}`);
    await page.locator('#customer-edit').click();
    await expect(page).toHaveURL(new RegExp(`/app/customers/${editedId}/edit$`));
    await expect(page.locator('#customer-name')).toHaveValue(edited.name);
    await expect(page.locator('#customer-email')).toHaveValue(edited.email);
    await expect(page.locator('#customer-notes')).toHaveValue(edited.notes);

    await page.locator('#customer-phone').fill('+1 503 555 0155');
    await page.locator('#customer-address').fill('40 Moved Road, Ridgeway');
    await page.locator('#customer-notes').fill('');
    await page.locator('#customer-save').click();

    await expect(page).toHaveURL(new RegExp(`/app/customers/${editedId}$`));
    await expect(page.locator('#customer-notice')).toHaveText('Saved your changes.');
    await expect(page.locator('dd')).toHaveText([edited.email, '+1 503 555 0155', '40 Moved Road, Ridgeway', 'None', /^\d{4}-\d{2}-\d{2}$/]);
    expect(storedCustomer(EMAILS.edited)).toEqual({
        id: editedId,
        ...edited,
        phone: '+1 503 555 0155',
        address: '40 Moved Road, Ridgeway',
        notes: '',
    });
});

test.describe('with the form sent as a plain post', () => {
    test.use({ javaScriptEnabled: false });

    test('an edit form pointed at another customer is refused and changes neither', async ({ page }) => {
        await signInAt(page, `/app/customers/${editedId}/edit`);
        await page.locator('#customer-notes').fill('Should not land anywhere.');
        await page.locator('#customer-form').evaluate((form, action) => form.setAttribute('action', action), `/app/customers/${otherId}`);

        const answer = page.waitForResponse((response) => response.request().method() === 'POST');
        await page.locator('#customer-save').click();
        expect((await answer).status()).toBe(400);
        await expect(page.locator('#customer-refused')).toContainText('This form was issued for another customer');

        expect(storedCustomer(EMAILS.edited)).toEqual({ id: editedId, ...edited });
        expect(storedCustomer(EMAILS.other)).toEqual({ id: otherId, ...other });
    });
});
