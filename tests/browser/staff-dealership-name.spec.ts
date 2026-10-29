import { expect, type Page, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { sql } from './support/inventory';
import {
    dealershipName, fillProfile, insertMember, removeSettingsLooks, restoreProfile, savedProfile, signInMember,
} from './support/settings';

let profile: string[] = [];

test.beforeEach(async () => {
    profile = savedProfile();
    await removeSettingsLooks();
});
test.afterEach(async () => {
    restoreProfile(profile);
    await removeSettingsLooks();
});

// A customer who bought, and that customer's latest sale: the record pages the names show on.
function boughtRecords(): { customer: string; sale: string } {
    const [row] = sql('SELECT customer_id || \'|\' || id FROM sales ORDER BY id DESC LIMIT 1');
    const [customer, sale] = row.split('|');

    return { customer, sale };
}

// Checks every staff page that names the dealership names it so.
async function expectNamed(page: Page, signedOut: Page, name: string): Promise<void> {
    const { customer, sale } = boughtRecords();
    await signedOut.goto('/sign-in');
    await expect(signedOut.locator('#sign-in-heading + p')).toHaveText(`${name} staff only.`);
    await expect(signedOut.locator('footer').last()).toHaveText(`Lotline for ${name}`);
    await page.goto('/app');
    await expect(page.getByText(`Admin at ${name}`)).toBeVisible();
    await expect(page.locator('footer').last()).toHaveText(`Lotline for ${name}`);
    await page.goto('/app/customers');
    await expect(page.locator('#customers-heading + p')).toHaveText(`Search and page the people ${name} serves`);
    await page.goto(`/app/customers/${customer}`);
    await expect(page.getByText(`What ${name} keeps on this customer.`)).toBeVisible();
    await page.goto(`/app/customers/${customer}/delete`);
    await expect(page.getByText(`This customer has bought from ${name}, so the record stays.`)).toBeVisible();
    await page.goto('/app/sales');
    await expect(page.locator('#sales-heading + p')).toHaveText(`What ${name} sold, by month and salesperson, with the totals`);
    await page.goto(`/app/sales/${sale}`);
    await expect(page.getByText(`What ${name} keeps on this sale.`)).toBeVisible();
    await signedOut.goto('/');
    await expect(signedOut.getByText(name, { exact: true })).toBeVisible();
}

test("after an admin renames the dealership, the staff pages name it so; put back, they name it as before", async ({ page, browser }) => {
    test.setTimeout(60_000);
    const errors = collectFormErrors(page);
    const admin = insertMember('Pat Look', 'admin');
    const context = await browser.newContext();
    const signedOut = await context.newPage();
    const before = dealershipName();
    await signInMember(page, admin);
    await expectNamed(page, signedOut, before);

    // The admin renames it in the settings.
    await page.goto('/app/settings');
    await fillProfile(page, { name: 'Harbour Light Cars' });
    await page.locator('#profile-save').click();
    await expect(page.locator('#settings-notice')).toHaveText("Saved the dealership's profile.");
    await expectNamed(page, signedOut, 'Harbour Light Cars');

    // Put back, every page names it as before.
    restoreProfile(profile);
    await expectNamed(page, signedOut, before);
    await context.close();
    expect(errors).toEqual([]);
});
