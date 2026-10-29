import { expect, test } from '@playwright/test';
import { collectFormErrors, expectRefusedInPlace, expectSavedVisit, recordRequests } from './support/forms';
import { firstCar } from './support/leads';
import { expectMarksKept, markPage } from './support/tessera';
import { fillProfile, insertMember, removeSettingsLooks, restoreProfile, savedProfile, signInMember } from './support/settings';

let profile: string[] = [];

test.beforeEach(async () => {
    profile = savedProfile();
    await removeSettingsLooks();
});
test.afterEach(async () => {
    restoreProfile(profile);
    await removeSettingsLooks();
});

test("an admin's profile change lands in place and shows in the public header, the footer and the test-drive hours", async ({ page }) => {
    const errors = collectFormErrors(page);
    const admin = insertMember('Ada Look', 'admin');
    const name = `Look Motors ${Date.now()}`;
    await signInMember(page, admin);

    // The sidebar's Settings link opens the page; the form shows the profile as it stands.
    await page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Settings' }).click();
    await expect(page).toHaveURL(/\/app\/settings$/);
    await expect(page).toHaveTitle('Settings · Lotline');
    await markPage(page);
    const requests = await recordRequests(page);

    // A blank name is refused in place, beside its field.
    await fillProfile(page, { name: '' });
    await page.locator('#profile-save').click();
    await expectRefusedInPlace(requests, '/app/settings/profile');
    await expect(page.locator('#profile-name-error')).toHaveText("Enter the dealership's name.");
    await expect(page.locator('#profile-error')).toBeVisible();

    // A new name, address, phone and hours, Saturday closed: saved in the same document, with its notice.
    await fillProfile(page, { name, address: '12 Look Lane, Portland', phone: '(503) 555-0142', opens_at: '08:00', closes_at: '17:00' });
    await page.locator('#profile-day-6').uncheck();
    await page.locator('#profile-save').click();
    await expectSavedVisit(requests, '/app/settings/profile', /^\/app\/settings$/);
    await expect(page.locator('#settings-notice')).toHaveText("Saved the dealership's profile.");
    await expect(page.locator('#profile-name')).toHaveValue(name);
    await expect(page.locator('#profile-day-6')).not.toBeChecked();
    await expectMarksKept(page);

    // The public list: the header names the dealership, the footer gives its address, phone and hours.
    await page.goto('/cars');
    await expect(page.locator('#store-header')).toContainText(name);
    await expect(page.locator('#store-footer')).toHaveText(
        `${name}. Every car here is on our lot today. 12 Look Lane, Portland · (503) 555-0142 · Open Monday to Friday, 08:00 to 17:00.`,
    );

    // A car's page: the same header, and the test drive's line follows the hours, its last start an hour before closing.
    await page.goto(firstCar().path);
    await expect(page.locator('#store-header')).toContainText(name);
    await expect(page.locator('[data-t-anchor="car-test-drive"]')).toContainText('Monday to Friday, from 08:00 to 16:00. We call to confirm.');
    expect(errors).toEqual([]);
});
