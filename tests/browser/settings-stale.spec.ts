import { expect, test } from '@playwright/test';
import { collectFormErrors, expectSavedVisit, recordRequests } from './support/forms';
import {
    expectAnsweredInPlace,
    fillProfile,
    insertMember,
    removeSettingsLooks,
    restoreProfile,
    savedProfile,
    signInMember,
    storedMember,
} from './support/settings';
import { sql } from './support/inventory';
import { expectMarksKept, markPage } from './support/tessera';

let profile: string[] = [];

test.beforeEach(async () => {
    profile = savedProfile();
    await removeSettingsLooks();
});
test.afterEach(async () => {
    restoreProfile(profile);
    await removeSettingsLooks();
});

// Chrome logs a 409 as a failed load; nothing else may be logged.
function unexpected(errors: string[]): string[] {
    return errors.filter((error) => !error.includes('status of 409'));
}

test('a save made on a stale copy answers 409 in place with what changed, overwrites nothing, and saving again saves', async ({ page, browser }) => {
    const errors = collectFormErrors(page);
    const first = insertMember('Ada Look', 'admin');
    const second = insertMember('Bea Look', 'admin');
    const member = insertMember('Dee Look', 'salesperson');

    // Both admins open the member's page.
    const context = await browser.newContext();
    const theirs = await context.newPage();
    const theirErrors = collectFormErrors(theirs);
    await signInMember(page, first);
    await signInMember(theirs, second);
    await page.goto(`/app/settings/staff/${member.id}`);
    await theirs.goto(`/app/settings/staff/${member.id}`);

    // The first renames them.
    await page.locator('#member-name').fill('Dee Renamed');
    await page.locator('#member-save').click();
    await expect(page.locator('#member-notice')).toHaveText('Saved Dee Renamed.');

    // The second, on the old copy, makes them a sales manager: 409 in place, what changed listed, nothing overwritten.
    await markPage(theirs);
    const requests = await recordRequests(theirs);
    await theirs.locator('#member-role').selectOption('sales_manager');
    await theirs.locator('#member-save').click();
    await expectAnsweredInPlace(requests, `/app/settings/staff/${member.id}`, 409);
    await expect(theirs.locator('#member-stale')).toContainText('Someone saved this member after you opened it, so your changes were not saved.');
    await expect(theirs.locator('#member-stale')).toContainText('Name was "Dee Look" and is now "Dee Renamed"');
    await expect(theirs.locator('#member-role')).toHaveValue('sales_manager');
    await expectMarksKept(theirs);
    expect(storedMember(member.id)).toEqual({ name: 'Dee Renamed', role: 'salesperson', deactivatedOn: '' });

    // Saving again, knowing what changed, saves what the form holds.
    await theirs.locator('#member-name').fill('Dee Renamed');
    await theirs.locator('#member-save').click();
    await expectSavedVisit(requests, `/app/settings/staff/${member.id}`, new RegExp(`^/app/settings/staff/${member.id}$`));
    expect(storedMember(member.id)).toEqual({ name: 'Dee Renamed', role: 'sales_manager', deactivatedOn: '' });

    // The profile too: both open it, the first changes the phone, the second's address change answers 409.
    await page.goto('/app/settings');
    await theirs.goto('/app/settings');
    await fillProfile(page, { phone: '(503) 555-0199' });
    await page.locator('#profile-save').click();
    await expect(page.locator('#settings-notice')).toBeVisible();
    const kept = sql("SELECT address || '|' || phone FROM dealership_profile WHERE slot = 'main'");
    const theirRequests = await recordRequests(theirs);
    await fillProfile(theirs, { address: '1 Stale Street' });
    await theirs.locator('#profile-save').click();
    await expectAnsweredInPlace(theirRequests, '/app/settings/profile', 409);
    await expect(theirs.locator('#profile-stale')).toContainText('Someone saved the profile after you opened it');
    await expect(theirs.locator('#profile-stale')).toContainText('is now "(503) 555-0199"');
    await expect(theirs.locator('#profile-address')).toHaveValue('1 Stale Street');
    expect(sql("SELECT address || '|' || phone FROM dealership_profile WHERE slot = 'main'")).toEqual(kept);
    await context.close();
    expect(errors).toEqual([]);
    expect(unexpected(theirErrors)).toEqual([]);
});
