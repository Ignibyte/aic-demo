import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { insertMember, removeSettingsLooks, signInMember, storedMember } from './support/settings';

test.beforeEach(async () => removeSettingsLooks());
test.afterEach(async () => removeSettingsLooks());

test("a deactivated member's open session is signed out on its next request, and they cannot sign in until reactivated", async ({ page, browser }) => {
    const errors = collectFormErrors(page);
    const admin = insertMember('Ada Look', 'admin');
    const member = insertMember('Cal Look', 'salesperson');

    // The member, signed in in a browser of their own, on the inventory.
    const context = await browser.newContext();
    const theirs = await context.newPage();
    await signInMember(theirs, member);
    await theirs.goto('/app/inventory');
    await expect(theirs.locator('#account-name')).toHaveText('Cal Look');

    // The admin deactivates them; the settings table says since when.
    await signInMember(page, admin);
    await page.goto(`/app/settings/staff/${member.id}`);
    await page.locator('#member-active').click();
    await expect(page.locator('#member-notice')).toHaveText('Deactivated Cal Look. They are signed out and cannot sign in.');
    await expect(page.locator('#member-active')).toHaveText('Reactivate');
    const deactivatedOn = storedMember(member.id).deactivatedOn;
    expect(deactivatedOn).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    await page.goto('/app/settings');
    await expect(page.locator('#members-table tr', { hasText: member.email })).toContainText(`Deactivated on ${deactivatedOn}`);

    // Their next request, a click in the sidebar, lands on sign-in: the session is gone.
    await theirs.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Customers' }).click();
    await expect(theirs).toHaveURL(/\/sign-in\?next=%2Fapp%2Fcustomers$/);
    await expect(theirs.locator('#account-name')).toHaveCount(0);

    // Signing in again is refused as a wrong password is.
    await theirs.locator('#sign-in-email').fill(member.email);
    await theirs.locator('#sign-in-password').fill(member.password);
    await theirs.locator('#sign-in-submit').click();
    await expect(theirs.locator('#sign-in-error')).toHaveText('Wrong email or password.');

    // Deactivated, they are no salesperson a new sale may name; the sales list's filter still has them.
    await page.goto('/app/sales/new');
    await expect(page.locator(`#sale-salesperson option[value="${member.email}"]`)).toHaveCount(0);
    await page.goto('/app/sales');
    await expect(page.locator(`option[value="${member.email}"]`)).toHaveCount(1);

    // Reactivated, they sign in again, and the sale form offers them.
    await page.goto(`/app/settings/staff/${member.id}`);
    await page.locator('#member-active').click();
    await expect(page.locator('#member-notice')).toHaveText('Reactivated Cal Look. They can sign in again.');
    expect(storedMember(member.id).deactivatedOn).toBe('');
    await page.goto('/app/sales/new');
    await expect(page.locator(`#sale-salesperson option[value="${member.email}"]`)).toHaveCount(1);
    await signInMember(theirs, member);
    await expect(theirs.locator('#account-name')).toHaveText('Cal Look');
    await context.close();
    expect(errors).toEqual([]);
});
