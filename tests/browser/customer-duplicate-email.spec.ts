import { expect, test, type Page } from '@playwright/test';
import {
    EMAILS,
    emailCount,
    fillCustomerForm,
    insertCustomer,
    removeTestCustomers,
    signInAt,
    storedCustomer,
    testCustomer,
} from './support/customers';

const holder = testCustomer('Probe Holder', EMAILS.holder);
const other = testCustomer('Probe Other', EMAILS.other);

// The holder's email in other letters: the same address, as the check reads it.
const shouted = EMAILS.holder.toUpperCase();

// Saves the form and waits for the post's answer, which must refuse it.
async function saveRefused(page: Page): Promise<void> {
    const answer = page.waitForResponse((response) => response.request().method() === 'POST');
    await page.locator('#customer-save').click();
    expect((await answer).status()).toBe(422);
}

// The email field is the only one marked, the message beside it, and every value is as typed.
async function expectEmailRefused(page: Page, typed: { name: string; phone: string; address: string; notes: string }): Promise<void> {
    await expect(page.locator('#customer-error')).toHaveText('Fix the fields marked below.');
    await expect(page.locator('#customer-email')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#customer-email-error')).toHaveText('Another customer has this email.');
    await expect(page.locator('[aria-invalid="true"]')).toHaveCount(1);
    await expect(page.locator('#customer-email')).toHaveValue(shouted);
    await expect(page.locator('#customer-name')).toHaveValue(typed.name);
    await expect(page.locator('#customer-phone')).toHaveValue(typed.phone);
    await expect(page.locator('#customer-address')).toHaveValue(typed.address);
    await expect(page.locator('#customer-notes')).toHaveValue(typed.notes);
}

let otherId = 0;

test.beforeEach(() => {
    removeTestCustomers();
    insertCustomer(holder);
    otherId = insertCustomer(other);
});
test.afterEach(() => removeTestCustomers());

test('an email a customer already has is refused beside its field, for a new customer and for an edit', async ({ page }) => {
    const typed = { ...testCustomer('Probe Second', shouted), phone: '503.555.0166', notes: 'Typed by the look.' };

    await signInAt(page, '/app/customers/new');
    await fillCustomerForm(page, typed);
    await saveRefused(page);
    await expectEmailRefused(page, typed);
    expect(emailCount(EMAILS.holder)).toBe(1);

    // Editing another customer to the same email is refused the same way, and the edit changes nothing.
    await page.goto(`/app/customers/${otherId}/edit`);
    await page.locator('#customer-email').fill(shouted);
    await page.locator('#customer-notes').fill('Changed by the look.');
    await saveRefused(page);
    await expectEmailRefused(page, { ...other, notes: 'Changed by the look.' });

    expect(emailCount(EMAILS.holder)).toBe(1);
    expect(storedCustomer(EMAILS.other)).toEqual({ id: otherId, ...other });
});
