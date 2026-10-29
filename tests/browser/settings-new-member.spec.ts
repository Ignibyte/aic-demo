import { randomBytes } from 'node:crypto';
import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { literal, sql } from './support/inventory';
import { dealershipName, insertMember, lookEmail, mailedLinks, removeSettingsLooks, signInMember, storedMember } from './support/settings';

test.beforeEach(async () => removeSettingsLooks());
test.afterEach(async () => removeSettingsLooks());

test('a new member sets a password from the mailed link and signs in', async ({ page, browser }) => {
    const errors = collectFormErrors(page);
    const admin = insertMember('Ada Look', 'admin');
    const email = lookEmail('Nia Look');
    await signInMember(page, admin);
    await page.goto('/app/settings');

    // A taken email is refused beside its field; nothing is added.
    await page.locator('#member-add-name').fill('Nia Look');
    await page.locator('#member-add-email').fill(admin.email);
    await page.locator('#member-add-role').selectOption('sales_manager');
    await page.locator('#member-add').click();
    await expect(page.locator('#member-add-email-error')).toHaveText('A member already signs in with this email.');

    // Added: their page, with the notice, and a member with no password who cannot sign in yet.
    await page.locator('#member-add-email').fill(email.toUpperCase());
    await page.locator('#member-add').click();
    await expect(page).toHaveURL(/\/app\/settings\/staff\/\d+$/);
    await expect(page.locator('#member-notice')).toHaveText('Added Nia Look and mailed them a link to set a password.');
    const [id] = sql(`SELECT id FROM users WHERE email = ${literal(email)}`);
    expect(storedMember(id)).toEqual({ name: 'Nia Look', role: 'sales_manager', deactivatedOn: '' });
    expect(sql(`SELECT password_hash = '' FROM users WHERE id = ${id}`)).toEqual(['t']);

    // One mail, naming the dealership, its link absolute and to the set-password page.
    const dealership = dealershipName();
    const [mail] = await mailedLinks(email);
    expect(mail.subject).toBe(`Your ${dealership} account: set your password`);
    expect(mail.text).toContain(`An admin at ${dealership} has added you to Lotline as Sales manager. You sign in as ${email}.`);
    expect(mail.text).toMatch(/Set your password here\. The link works once, for 24 hours:\s+https:\/\/\S+\/password\/\S+/);
    expect(mail.path).toMatch(/^\/password\/[\w-]+\.[\w-]+$/);

    // In a browser of their own: the link's form, a short password refused, then a good one set.
    const context = await browser.newContext();
    const theirs = await context.newPage();
    const theirErrors = collectFormErrors(theirs);
    await theirs.goto(mail.path);
    await expect(theirs).toHaveTitle('Set your password · Lotline');
    await expect(theirs.locator('#settings-heading')).toHaveText('Set your password');
    await theirs.locator('#password-new').fill('too-short');
    await theirs.locator('#password-confirm').fill('too-short');
    await theirs.locator('#password-save').click();
    await expect(theirs.locator('#password-new-error')).toHaveText('Choose a password of 12 to 72 characters.');

    const password = `nia-${randomBytes(9).toString('hex')}`;
    await theirs.locator('#password-new').fill(password);
    await theirs.locator('#password-confirm').fill(password);
    await theirs.locator('#password-save').click();
    await expect(theirs).toHaveURL(/\/sign-in$/);
    await expect(theirs.locator('#sign-in-notice')).toHaveText('Your password is set. Sign in with it.');

    // They sign in with it, and the header names them.
    await theirs.locator('#sign-in-email').fill(email);
    await theirs.locator('#sign-in-password').fill(password);
    await theirs.locator('#sign-in-submit').click();
    await expect(theirs).toHaveURL(/\/app$/);
    await expect(theirs.locator('#account-name')).toHaveText('Nia Look');
    await context.close();
    expect(errors).toEqual([]);
    expect(theirErrors).toEqual([]);
});
