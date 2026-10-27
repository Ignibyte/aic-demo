import { expect, test } from '@playwright/test';
import { EMAILS, insertCustomer, removeTestCustomers, signInAt, storedCustomer, testCustomer } from './support/customers';

const doomed = testCustomer('Probe Deleted', EMAILS.deleted);
let doomedId = 0;

test.beforeEach(() => {
    removeTestCustomers();
    doomedId = insertCustomer(doomed);
});
test.afterEach(() => removeTestCustomers());

test('deleting a customer asks first, and then the customer is gone', async ({ page }) => {
    await signInAt(page, `/app/customers/${doomedId}`);
    await page.locator('#customer-delete').click();
    await expect(page).toHaveURL(new RegExp(`/app/customers/${doomedId}/delete$`));
    await expect(page.getByRole('heading', { name: 'Delete Probe Deleted?' })).toBeVisible();
    await expect(page.getByText(EMAILS.deleted)).toBeVisible();

    await page.getByRole('link', { name: 'Keep it' }).click();
    await expect(page).toHaveURL(new RegExp(`/app/customers/${doomedId}$`));
    expect(storedCustomer(EMAILS.deleted)).toEqual({ id: doomedId, ...doomed });

    await page.locator('#customer-delete').click();
    await page.locator('#customer-delete-confirm').click();

    await expect(page).toHaveURL(/\/app\/customers$/);
    await expect(page.locator('#customers-notice')).toHaveText("Deleted Probe Deleted's record.");
    expect(storedCustomer(EMAILS.deleted)).toBeNull();

    const answer = await page.goto(`/app/customers/${doomedId}`);
    expect(answer?.status()).toBe(404);
    await expect(page.locator('#customer-missing')).toBeVisible();
});
