import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { sql } from './support/inventory';
import { csrfToken, fetchInPage } from './support/photos';
import { DOMAIN, insertMember, removeSettingsLooks, signInMember, storedMember } from './support/settings';

test.beforeEach(async () => removeSettingsLooks());
test.afterEach(async () => removeSettingsLooks());

test('a sales manager and a salesperson see no Settings link and are refused at every settings route', async ({ page }) => {
    const errors = collectFormErrors(page);
    const target = insertMember('Eve Look', 'salesperson');

    for (const role of ['sales_manager', 'salesperson']) {
        const member = insertMember(`Fay ${role} Look`, role);
        await signInMember(page, member);
        const navigation = page.getByRole('navigation', { name: 'Main' });
        await expect(navigation.getByRole('link', { name: 'Customers' })).toBeVisible();
        await expect(navigation.getByRole('link', { name: 'Settings' })).toHaveCount(0);

        // The pages: 403 in the app's look.
        for (const path of ['/app/settings', `/app/settings/staff/${target.id}`]) {
            const answer = await page.goto(path);
            expect(answer?.status()).toBe(403);
            await expect(page).toHaveTitle('Not allowed · Lotline');
            await expect(page.locator('#settings-refused')).toHaveText('Only an admin can change the settings and the staff.');
        }

        // The posts, with their own session's token: 403, nothing changed.
        await page.goto('/app');
        const token = await csrfToken(page);
        const posts: [string, Record<string, string>][] = [
            ['/app/settings/profile', { name: 'Taken Over Motors' }],
            ['/app/settings/staff', { name: 'Mal Look', email: `mal${DOMAIN}`, role: 'admin' }],
            [`/app/settings/staff/${target.id}`, { name: 'Eve Taken', role: 'admin' }],
            [`/app/settings/staff/${target.id}/active`, { active: '0' }],
            [`/app/settings/staff/${target.id}/link`, {}],
        ];

        for (const [path, fields] of posts) {
            const posted = await fetchInPage(page, path, { method: 'POST', fields: { aic_csrf: token, ...fields } });
            expect(posted.status).toBe(403);
            expect(posted.body.toString('utf8')).toContain('Only an admin can change the settings and the staff.');
        }

        expect(storedMember(target.id)).toEqual({ name: 'Eve Look', role: 'salesperson', deactivatedOn: '' });
        expect(sql(`SELECT count(*) FROM users WHERE email = 'mal${DOMAIN}'`)).toEqual(['0']);
        expect(sql(`SELECT count(*) FROM password_links WHERE user_id = ${target.id}`)).toEqual(['0']);
        expect(sql("SELECT count(*) FROM dealership_profile WHERE name = 'Taken Over Motors'")).toEqual(['0']);
        await page.context().clearCookies();
    }

    // Chrome logs each refused page as a failed load; nothing else may be logged.
    expect(errors.filter((error) => !error.includes('status of 403'))).toEqual([]);
});
