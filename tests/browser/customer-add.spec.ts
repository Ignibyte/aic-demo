import { expect, test } from '@playwright/test';
import { EMAILS, fillCustomerForm, removeTestCustomers, signInAt, storedCustomer, testCustomer } from './support/customers';

const added = { ...testCustomer('Probe Added', EMAILS.added), address: '12 Probe Lane, Ridgeway' };

test.beforeEach(() => removeTestCustomers());
test.afterEach(() => removeTestCustomers());

test('adding a customer lands on its page, and Postgres holds it', async ({ page }) => {
    await signInAt(page, '/app/customers');
    await page.getByRole('link', { name: 'Add a customer' }).click();
    await expect(page).toHaveURL(/\/app\/customers\/new$/);

    // The email typed with capitals and spaces around it is kept trimmed and in small letters.
    await fillCustomerForm(page, { ...added, email: `  ${EMAILS.added.toUpperCase()} ` });
    await page.locator('#customer-save').click();
    await expect(page).toHaveURL(/\/app\/customers\/\d+$/);

    const stored = storedCustomer(EMAILS.added);
    expect(stored).toEqual({ id: expect.any(Number), ...added });
    await expect(page).toHaveURL(new RegExp(`/app/customers/${stored?.id}$`));
    await expect(page.locator('#customer-notice')).toHaveText('Added this customer.');
    await expect(page.locator('#customer-title')).toHaveText('Probe Added');
    await expect(page.locator('#customer-badge')).toHaveText('No purchases yet');
    await expect(page.locator('dd')).toHaveText([
        EMAILS.added,
        '(503) 555-0177',
        '12 Probe Lane, Ridgeway',
        'A browser look made this.',
        /^\d{4}-\d{2}-\d{2}$/,
    ]);
    await expect(page.locator('#customer-purchases-summary')).toHaveText('Vehicles bought');
    await expect(page.locator('#customer-purchases-empty')).toHaveText('No vehicles bought yet.');

    // The notice is for the redirect only.
    await page.reload();
    await expect(page.locator('#customer-notice')).toHaveCount(0);

    // The list finds it and links its name to the page.
    await page.goto(`/app/customers?q=${encodeURIComponent(EMAILS.added)}`);
    await expect(page.getByRole('link', { name: 'Probe Added' })).toHaveAttribute('href', `/app/customers/${stored?.id}`);
});
