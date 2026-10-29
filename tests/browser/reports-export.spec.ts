import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { mailFor } from './support/leads';
import { fetchInPage } from './support/photos';
import { work } from './support/schedules';
import {
    RANGE, REPORTS, type Report, csvLines, expectedLines, exportFileExists, exportReport, insertMember, insertReportData,
    notificationsOf, rangePath, removeReportLooks, signInMember, storedExports, tableLines,
} from './support/reports';

test.beforeEach(async () => removeReportLooks());
test.afterEach(async () => removeReportLooks());

test('an export, once the worker runs, notifies its member and downloads the same rows, and resumes by range', async ({ page }) => {
    const errors = collectFormErrors(page);
    insertReportData();
    const manager = insertMember('Eve Look', 'sales_manager');
    await signInMember(page, manager);
    const reports = Object.keys(REPORTS) as Report[];

    // Each report's button asks for its export and lands on the reports over the range, the export preparing.
    for (const report of reports) {
        await page.goto(rangePath());
        await exportReport(page, report);
        await expect(page.locator('#report-export-status')).toHaveText(
            `The ${REPORTS[report]} CSV for ${RANGE.from} to ${RANGE.to} is being prepared. You will be notified when it is ready.`,
        );
        await expect(page.locator('#report-export-download')).toHaveCount(0);
    }

    const exports = storedExports(manager);
    expect(exports.map((stored) => [stored.report, stored.from, stored.to, stored.status])).toEqual(
        reports.map((report) => [report, RANGE.from, RANGE.to, 'queued']),
    );
    const pending = await fetchInPage(page, `/app/reports/exports/${exports[0].id}`);
    expect(pending.status).toBe(404);

    // One worker writes them all and tells the member, kept and mailed, each linking its download.
    work();
    expect(storedExports(manager).map((stored) => stored.status)).toEqual(reports.map(() => 'ready'));
    const notifications = notificationsOf(manager);
    expect(notifications).toEqual([...exports].reverse().map((stored) => ({
        subject: `Your ${REPORTS[stored.report as Report]} CSV for ${RANGE.from} to ${RANGE.to} is ready`,
        link: `/app/reports/exports/${stored.id}`,
    })));
    await expect.poll(async () => (await mailFor(manager.email)).length).toBe(reports.length);

    // Each download is the table's rows, under the report's and range's name.
    for (const [index, report] of reports.entries()) {
        const { id } = exports[index];
        expect(exportFileExists(id)).toBe(true);
        await page.goto(`${rangePath()}&export=${id}`);
        await expect(page.locator('#report-export-status')).toHaveText(`The ${REPORTS[report]} CSV for ${RANGE.from} to ${RANGE.to} is ready.`);
        const saving = page.waitForEvent('download');
        await page.locator('#report-export-download').click();
        const download = await saving;
        expect(download.suggestedFilename()).toBe(`${report}-${RANGE.from}-to-${RANGE.to}.csv`);
        const lines = csvLines(readFileSync(await download.path(), 'utf8'));
        const header = await page.locator(`#report-${report}-table thead th`).allTextContents();
        expect(lines[0]).toEqual(header.map((cell) => cell.trim()));
        expect(lines.slice(1)).toEqual(expectedLines(report).map((line) => [line.label, String(line.count), String(line.amount)]));
        expect(lines.slice(1).map((line) => line[0])).toEqual((await tableLines(page, report)).map((cells) => cells[0]));
    }

    // The download whole, then resumed from its tenth byte.
    const path = `/app/reports/exports/${exports[0].id}`;
    const whole = await fetchInPage(page, path);
    expect(whole.status).toBe(200);
    expect(whole.headers['content-type']).toBe('text/csv; charset=utf-8');
    expect(whole.headers['content-disposition']).toContain(`sales-${RANGE.from}-to-${RANGE.to}.csv`);
    const rest = await fetchInPage(page, path, { headers: { Range: 'bytes=10-' } });
    expect(rest.status).toBe(206);
    expect(rest.headers['content-range']).toBe(`bytes 10-${whole.body.length - 1}/${whole.body.length}`);
    expect(Buffer.concat([whole.body.subarray(0, 10), rest.body]).equals(whole.body)).toBe(true);

    // Opened from the inbox, the notification saves the CSV too.
    await page.goto('/app/inbox');
    const saving = page.waitForEvent('download');
    await page.locator('#inbox-table tbody tr a').first().click();
    expect((await saving).suggestedFilename()).toBe(`leads-${RANGE.from}-to-${RANGE.to}.csv`);

    // Chromium logs the pending download's 404 as a failed load; nothing else may be logged.
    expect(errors.filter((error) => !error.includes('status of 404'))).toEqual([]);
});
