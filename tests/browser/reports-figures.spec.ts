import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { fetchInPage } from './support/photos';
import {
    RANGE, REPORTS, type Report, expectedLines, insertMember, insertReportData, rangePath, removeReportLooks, shownLine,
    signInMember, tableLines,
} from './support/reports';

test.beforeEach(async () => removeReportLooks());
test.afterEach(async () => removeReportLooks());

test('each report shows exactly the figures the database gives for a chosen range, and the API answers the same', async ({ page }) => {
    const errors = collectFormErrors(page);
    insertReportData();
    const manager = insertMember('Mia Look', 'sales_manager');
    await signInMember(page, manager);

    // The sidebar's Reports link opens the reports, current there.
    const link = page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Reports' });
    await link.click();
    await expect(page).toHaveURL(/\/app\/reports$/);
    await expect(link).toHaveAttribute('aria-current', 'page');

    await page.goto(rangePath());
    await expect(page.locator('#report-range-panel-heading')).toHaveText('Range');

    for (const report of Object.keys(REPORTS) as Report[]) {
        await expect(page.locator(`#report-${report}-heading`)).toHaveText(REPORTS[report]);
        const lines = expectedLines(report);
        expect(lines.length, report).toBeGreaterThan(0);
        expect(await tableLines(page, report), report).toEqual(lines.map((line) => shownLine(report, line)));
        await expect(page.locator(`#report-${report}-chart-title`)).toHaveText(REPORTS[report]);
    }

    // The looks' own figures, as a check on the SQL above: two months, Ann ahead of Bob, three makes, a sale from
    // each kind of lead, and the records outside the range left out.
    expect(expectedLines('sales')).toEqual([
        { label: 'March 2001', count: 1, amount: 20000 },
        { label: 'April 2001', count: 2, amount: 43000 },
    ]);
    expect(expectedLines('salespeople')).toEqual([
        { label: 'Ann Look', count: 2, amount: 38000 },
        { label: 'Bob Look', count: 1, amount: 25000 },
    ]);
    expect(expectedLines('inventory').map((line) => [line.label, line.count])).toEqual([['Alphalook', 2], ['Betalook', 1], ['Gammalook', 1]]);
    expect(expectedLines('inventory')[0].amount).toBe(42);
    expect(expectedLines('leads')).toEqual([
        { label: 'Questions', count: 1, amount: 1 },
        { label: 'Test drives', count: 2, amount: 1 },
    ]);

    // The API, from the page's own session.
    const answer = await fetchInPage(page, `/api/reports/sales?from=${RANGE.from}&to=${RANGE.to}`);
    expect(answer.status).toBe(200);
    expect(answer.headers['content-type']).toMatch(/^application\/json/);
    const months = expectedLines('sales');
    expect(JSON.parse(answer.body.toString('utf8'))).toEqual({
        from: RANGE.from,
        to: RANGE.to,
        sales: 3,
        revenue: 63000,
        months: [
            { month: '2001-03', sales: months[0].count, revenue: months[0].amount },
            { month: '2001-04', sales: months[1].count, revenue: months[1].amount },
        ],
    });

    const refused = await fetchInPage(page, '/api/reports/sales?from=2001-02-30&to=2001-03-31');
    expect(refused.status).toBe(422);
    expect(JSON.parse(refused.body.toString('utf8'))).toEqual({
        error: 'That range cannot be reported on.',
        fields: { from: 'Give a day as YYYY-MM-DD, from 2000-01-01 to the end of next year.' },
    });

    expect(errors).toEqual([]);
});
