import { randomBytes } from 'node:crypto';
import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { sql } from './support/inventory';
import { insertMember, mailedLinks, removeSettingsLooks, signInMember } from './support/settings';

test.beforeEach(async () => removeSettingsLooks());
test.afterEach(async () => removeSettingsLooks());

// Chrome logs each refused page as a failed load; nothing else may be logged.
function unexpected(errors: string[]): string[] {
    return errors.filter((error) => !/status of (400|404)/.test(error));
}

test('a link sets a password once: refused a second time, when a newer one replaced it, altered or past 24 hours', async ({ page, browser }) => {
    const errors = collectFormErrors(page);
    const admin = insertMember('Ada Look', 'admin');
    const member = insertMember('Ben Look', 'salesperson');
    await signInMember(page, admin);

    // Two links sent from the member's page: the newer voids the older.
    await page.goto(`/app/settings/staff/${member.id}`);
    await page.locator('#member-link').click();
    await expect(page.locator('#member-notice')).toHaveText('Sent Ben Look a link to set a new password. It works once, for 24 hours.');
    await page.locator('#member-link').click();
    await expect(page.locator('#member-notice')).toBeVisible();
    const [older, newer] = await mailedLinks(member.email);
    expect(older.subject).toMatch(/^Set a new password for your .+ account$/);
    expect(older.text).toContain('your old password works until you set a new one');

    const context = await browser.newContext();
    const theirs = await context.newPage();
    const theirErrors = collectFormErrors(theirs);

    // The older link: used up by the newer, in the app's look.
    const voided = await theirs.goto(older.path);
    expect(voided?.status()).toBe(404);
    await expect(theirs).toHaveTitle('Link used or expired · Lotline');
    await expect(theirs.locator('#password-link-spent')).toContainText('This link has been used, replaced by a newer one');

    // The newer sets the password, after which it is refused too, by GET and by POST.
    const password = `ben-${randomBytes(9).toString('hex')}`;
    await theirs.goto(newer.path);
    await theirs.locator('#password-new').fill(password);
    await theirs.locator('#password-confirm').fill(password);
    await theirs.locator('#password-save').click();
    await expect(theirs).toHaveURL(/\/sign-in$/);
    const again = await theirs.goto(newer.path);
    expect(again?.status()).toBe(404);
    await expect(theirs.locator('#password-link-spent')).toBeVisible();
    await expect(theirs.locator('a', { hasText: 'Go to sign in' })).toHaveAttribute('href', '/sign-in');

    // An altered token, in the app's look; nothing changed.
    const hash = sql(`SELECT password_hash FROM users WHERE id = ${member.id}`);
    const altered = await theirs.goto(`${newer.path.slice(0, -2)}xx`);
    expect(altered?.status()).toBe(400);
    await expect(theirs).toHaveTitle('Link not accepted · Lotline');
    await expect(theirs.locator('#password-link-altered')).toContainText('This link was altered');

    // A link past its 24 hours by the database's clock.
    await page.locator('#member-link').click();
    await expect(page.locator('#member-notice')).toBeVisible();
    const links = await mailedLinks(member.email);
    const expired = links[links.length - 1];
    sql(`UPDATE password_links SET created_at = now() - interval '25 hours' WHERE user_id = ${member.id} AND NOT used`);
    const late = await theirs.goto(expired.path);
    expect(late?.status()).toBe(404);
    await expect(theirs.locator('#password-link-spent')).toBeVisible();
    expect(sql(`SELECT password_hash FROM users WHERE id = ${member.id}`)).toEqual(hash);
    await context.close();
    expect(unexpected(errors)).toEqual([]);
    expect(unexpected(theirErrors)).toEqual([]);
});
