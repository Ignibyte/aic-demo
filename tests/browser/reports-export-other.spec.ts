import { expect, test } from '@playwright/test';
import { fetchInPage } from './support/photos';
import { work } from './support/schedules';
import {
    exportReport, insertMember, insertReportData, rangePath, removeReportLooks, signInMember, storedExports,
} from './support/reports';

test.beforeEach(async () => removeReportLooks());
test.afterEach(async () => removeReportLooks());

test('another manager opening the first member\'s ready export gets 404, and the first still downloads it', async ({ browser, page }) => {
    insertReportData();
    const owner = insertMember('Ola Look', 'sales_manager');
    const other = insertMember('Pat Look', 'admin');
    await signInMember(page, owner);
    await page.goto(rangePath());
    await exportReport(page, 'inventory');
    work();
    const [stored] = storedExports(owner);
    expect(stored.status).toBe('ready');
    const path = `/app/reports/exports/${stored.id}`;

    const context = await browser.newContext();
    const otherPage = await context.newPage();
    await signInMember(otherPage, other);
    const refused = await fetchInPage(otherPage, path);
    expect(refused.status).toBe(404);
    expect(refused.headers['content-type'] ?? '').not.toContain('text/csv');
    const seen = await otherPage.goto(`${rangePath()}&export=${stored.id}`);
    expect(seen?.status()).toBe(200);
    await expect(otherPage.locator('#report-export-status')).toHaveCount(0);
    await expect(otherPage.locator('#report-export-download')).toHaveCount(0);
    await context.close();

    const own = await fetchInPage(page, path);
    expect(own.status).toBe(200);
    expect(own.headers['content-type']).toBe('text/csv; charset=utf-8');
    expect(own.body.toString('utf8').startsWith('Make,Vehicles,Average days on the lot\r\n')).toBe(true);
});
