import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { insertMember, removeSettingsLooks, restoreAdmins, setAsideAdmins, signInMember, storedMember } from './support/settings';

let setAside: string[] = [];

test.beforeEach(async () => removeSettingsLooks());
test.afterEach(async () => {
    restoreAdmins(setAside);
    setAside = [];
    await removeSettingsLooks();
});

// Chrome logs each refusal as a failed load; nothing else may be logged.
function unexpected(errors: string[]): string[] {
    return errors.filter((error) => !error.includes('status of 422') && !error.includes('status of 403'));
}

test('the last active admin cannot be demoted or deactivated; with a second admin the demotion goes through', async ({ page }) => {
    const errors = collectFormErrors(page);
    const admin = insertMember('Ada Look', 'admin');
    await signInMember(page, admin);

    // Every other active admin set aside for the look, so this one is the last.
    setAside = setAsideAdmins(admin);
    await page.goto(`/app/settings/staff/${admin.id}`);

    // Demoting themselves is refused with the reason, beside the role too.
    await page.locator('#member-role').selectOption('salesperson');
    await page.locator('#member-save').click();
    await expect(page.locator('#member-error')).toHaveText('Lotline needs an active admin. Make another member an admin first.');
    await expect(page.locator('#member-role-error')).toHaveText('Lotline needs an active admin. Make another member an admin first.');
    expect(storedMember(admin.id).role).toBe('admin');

    // Deactivating themselves is refused too.
    await page.goto(`/app/settings/staff/${admin.id}`);
    await page.locator('#member-active').click();
    await expect(page.locator('#member-warning')).toHaveText('Lotline needs an active admin. Make another member an admin first.');
    expect(storedMember(admin.id)).toEqual({ name: 'Ada Look', role: 'admin', deactivatedOn: '' });

    // With a second active admin, the demotion is saved, and the Settings link and page are gone on the next request.
    insertMember('Bea Look', 'admin');
    await page.goto(`/app/settings/staff/${admin.id}`);
    await page.locator('#member-role').selectOption('sales_manager');
    await page.locator('#member-save').click();
    await expect(page.locator('#settings-refused')).toHaveText('Only an admin can change the settings and the staff.');
    await expect(page).toHaveURL(/\/app\/settings\/staff\/\d+$/);
    expect(storedMember(admin.id).role).toBe('sales_manager');
    await expect(page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Settings' })).toHaveCount(0);
    expect(unexpected(errors)).toEqual([]);
});
