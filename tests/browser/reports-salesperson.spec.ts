import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { csrfToken, fetchInPage } from './support/photos';
import { RANGE, insertMember, removeReportLooks, signInMember, storedExports } from './support/reports';

test.beforeEach(async () => removeReportLooks());
test.afterEach(async () => removeReportLooks());

test('a salesperson sees no Reports link and is refused the page, the export and the API', async ({ page }) => {
    const errors = collectFormErrors(page);
    const seller = insertMember('Sam Look', 'salesperson');
    await signInMember(page, seller);

    const navigation = page.getByRole('navigation', { name: 'Main' });
    await expect(navigation.getByRole('link', { name: 'Holds' })).toBeVisible();
    await expect(navigation.getByRole('link', { name: 'Reports' })).toHaveCount(0);

    // The page: 403 in the app's look, the sidebar and all.
    const answer = await page.goto('/app/reports');
    expect(answer?.status()).toBe(403);
    await expect(page).toHaveTitle('Not allowed · Lotline');
    await expect(page.locator('#reports-refused')).toHaveText('Only a sales manager or an admin can read the reports.');
    await expect(navigation.getByRole('link', { name: 'Dashboard' })).toBeVisible();
    await expect(page.locator('#report-sales-heading')).toHaveCount(0);

    // Posting an export anyway, with their own session's token: refused, nothing stored.
    await page.goto('/app');
    const token = await csrfToken(page);
    const posted = await fetchInPage(page, '/app/reports/exports', {
        method: 'POST',
        fields: { aic_csrf: token, report: 'sales', from: RANGE.from, to: RANGE.to },
    });
    expect(posted.status).toBe(403);
    expect(posted.body.toString('utf8')).toContain('Only a sales manager or an admin can read the reports.');
    expect(storedExports(seller)).toEqual([]);

    // The API: 403 as JSON.
    const api = await fetchInPage(page, '/api/reports/sales');
    expect(api.status).toBe(403);
    expect(api.headers['content-type']).toMatch(/^application\/json/);
    expect(JSON.parse(api.body.toString('utf8'))).toEqual({ error: 'Only a sales manager or an admin can read the sales report.' });

    // Chromium logs each refusal as a failed load; nothing else may be logged.
    expect(errors.filter((error) => !error.includes('status of 403'))).toEqual([]);
});
