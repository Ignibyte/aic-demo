import { randomBytes } from 'node:crypto';
import { expect, type Page, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { expectBellCount } from './support/inbox';
import { literal, sql } from './support/inventory';
import { work } from './support/schedules';
import { insertMember, mailedLinks, removeSettingsLooks, signInMember } from './support/settings';

test.beforeEach(async () => removeSettingsLooks());
test.afterEach(async () => removeSettingsLooks());

// Sets a new password on the link's page, in that page, and lands on sign-in.
async function setPassword(page: Page, path: string): Promise<string> {
    const password = `look-${randomBytes(9).toString('hex')}`;
    await page.goto(path);
    await page.locator('#password-new').fill(password);
    await page.locator('#password-confirm').fill(password);
    await page.locator('#password-save').click();
    await expect(page).toHaveURL(/\/sign-in$/);

    return password;
}

// Whether the page's next visit to the customers lands on sign-in, as a signed-out session does.
async function signedOut(page: Page): Promise<boolean> {
    await page.goto('/app/customers');

    return new URL(page.url()).pathname === '/sign-in';
}

test("a password set through a link signs the member out of their other sessions, and keeps the one that set it", async ({ page, browser }) => {
    test.setTimeout(90_000);
    const errors = collectFormErrors(page);
    const admin = insertMember('Kit Look', 'admin');
    const member = insertMember('Lou Look', 'salesperson');

    // The member is signed in in one browser; an admin sends them a link from another.
    const memberContext = await browser.newContext();
    const theirs = await memberContext.newPage();
    await signInMember(theirs, member);
    await signInMember(page, admin);
    await page.goto(`/app/settings/staff/${member.id}`);
    await page.locator('#member-link').click();
    await expect(page.locator('#member-notice')).toHaveText('Sent Lou Look a link to set a new password. It works once, for 24 hours.');
    const [link] = await mailedLinks(member.email);

    // A third browser, signed in as no one, sets the new password: the member's open session is signed out.
    const linkContext = await browser.newContext();
    const password = await setPassword(await linkContext.newPage(), link.path);
    expect(await signedOut(theirs)).toBe(true);
    expect(new URL(theirs.url()).search).toBe('?next=%2Fapp%2Fcustomers');

    // The new password signs them in again.
    await signInMember(theirs, { ...member, password });
    expect(await signedOut(theirs)).toBe(false);

    // An admin who sends a link to themselves gets it as anyone does: the notice, the bell, the mail.
    const otherContext = await browser.newContext();
    const elsewhere = await otherContext.newPage();
    await signInMember(elsewhere, admin);
    await page.goto(`/app/settings/staff/${admin.id}`);
    await page.locator('#member-link').click();
    await expect(page).toHaveURL(new RegExp(`/app/settings/staff/${admin.id}$`));
    await expect(page.locator('#member-notice')).toHaveText('Sent Kit Look a link to set a new password. It works once, for 24 hours.');
    work();
    await page.goto('/app');
    await expectBellCount(page, 1);
    const [own] = await mailedLinks(admin.email);
    expect(own.path).toMatch(/^\/password\/[\w-]+\.[\w-]+$/);
    const [kept] = sql(`SELECT link FROM aic_notifications WHERE user_id = ${literal(admin.id)}`);
    expect(kept).toBe(own.path);

    // Set from the admin's own session, the password keeps that session and ends the other one.
    await setPassword(page, own.path);
    expect(await signedOut(page)).toBe(false);
    expect(await signedOut(elsewhere)).toBe(true);

    await memberContext.close();
    await linkContext.close();
    await otherContext.close();
    expect(errors).toEqual([]);
});
