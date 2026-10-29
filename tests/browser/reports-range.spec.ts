import { expect, test } from '@playwright/test';
import { sql } from './support/inventory';
import { collectFormErrors } from './support/forms';
import { ready } from './support/tessera';
import {
    RANGE, expectedLines, insertMember, insertReportData, removeReportLooks, shownLine, signInMember, tableLines,
} from './support/reports';

test.beforeEach(async () => removeReportLooks());
test.afterEach(async () => removeReportLooks());

// The database's first and last day of this month and of last month, worked out here and not taken from the app.
function month(offset: string): { from: string; to: string } {
    const [row] = sql(
        `SELECT to_char(date_trunc('month', current_date ${offset}), 'YYYY-MM-DD') || '|' `
            + `|| to_char(date_trunc('month', current_date ${offset}) + interval '1 month' - interval '1 day', 'YYYY-MM-DD')`,
    );
    const [from, to] = row.split('|');

    return { from, to };
}

test('the dates and a preset change the reports in place; a bad day is refused beside its field', async ({ page }) => {
    const errors = collectFormErrors(page);
    insertReportData();
    const manager = insertMember('Rae Look', 'admin');
    await signInMember(page, manager);
    const thisMonth = month('');
    const lastMonth = month("- interval '1 month'");

    // This month by default, its preset marked.
    await page.goto('/app/reports');
    await ready(page);
    await expect(page.locator('section[aria-labelledby="report-range-panel-heading"]')).toContainText(`${thisMonth.from} to ${thisMonth.to}`);
    await expect(page.locator('#report-ranges').getByRole('link', { name: 'This month' })).toHaveAttribute('aria-current', 'true');
    await expect(page.locator('#report-from')).toHaveValue(thisMonth.from);
    await page.evaluate(() => Object.assign(window, { reportsMark: 'kept' }));

    // The two dates, sent by the form: the reports over the range, in the same document.
    await page.locator('#report-from').fill(RANGE.from);
    await page.locator('#report-to').fill(RANGE.to);
    await page.locator('#report-range').getByRole('button', { name: 'Filter' }).click();
    await expect(page).toHaveURL(new RegExp(`/app/reports\\?from=${RANGE.from}&to=${RANGE.to}$`));
    await expect(page.locator('#report-sales-heading')).toBeVisible();
    await expect.poll(() => tableLines(page, 'sales')).toEqual(expectedLines('sales').map((line) => shownLine('sales', line)));
    await expect(page.locator('#report-ranges a[aria-current="true"]')).toHaveCount(0);
    expect(await page.evaluate(() => (window as Window & { reportsMark?: string }).reportsMark)).toBe('kept');

    // A preset: last month, in the same document, marked.
    await page.locator('#report-ranges').getByRole('link', { name: 'Last month' }).click();
    await expect(page).toHaveURL(new RegExp(`/app/reports\\?from=${lastMonth.from}&to=${lastMonth.to}$`));
    await expect(page.locator('#report-ranges').getByRole('link', { name: 'Last month' })).toHaveAttribute('aria-current', 'true');
    await expect(page.locator('#report-to')).toHaveValue(lastMonth.to);
    expect(await page.evaluate(() => (window as Window & { reportsMark?: string }).reportsMark)).toBe('kept');

    // A day that is not one: 422, its error beside its field, and this month's reports.
    const refused = await page.goto('/app/reports?from=2001-02-30&to=2001-03-31');
    expect(refused?.status()).toBe(422);
    await expect(page.locator('#report-from-error')).toHaveText('Give a day as YYYY-MM-DD, from 2000-01-01 to the end of next year.');
    await expect(page.locator('#report-from')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#report-to-error')).toHaveCount(0);
    await expect(page.locator('#report-range-notice')).toHaveText('That range cannot be reported on, so these are this month\'s reports.');
    await expect(page.locator('section[aria-labelledby="report-sales-heading"]')).toContainText(`${thisMonth.from} to ${thisMonth.to}`);

    // A last day before the first.
    const reversed = await page.goto(`/app/reports?from=${RANGE.to}&to=${RANGE.from}`);
    expect(reversed?.status()).toBe(422);
    await expect(page.locator('#report-to-error')).toHaveText('The last day comes before the first.');

    expect(errors).toEqual([]);
});

test('with JavaScript off the same form loads the range\'s reports', async ({ browser }) => {
    insertReportData();
    const manager = insertMember('Sol Look', 'sales_manager');
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await signInMember(page, manager);
    await page.goto('/app/reports');
    await page.locator('#report-from').fill(RANGE.from);
    await page.locator('#report-to').fill(RANGE.to);
    await page.locator('#report-range').getByRole('button', { name: 'Filter' }).click();
    await expect(page).toHaveURL(new RegExp(`/app/reports\\?from=${RANGE.from}&to=${RANGE.to}$`));

    for (const report of ['sales', 'salespeople', 'inventory', 'leads'] as const) {
        expect(await tableLines(page, report), report).toEqual(expectedLines(report).map((line) => shownLine(report, line)));
    }

    await page.locator('#report-ranges').getByRole('link', { name: 'This year' }).click();
    await expect(page.locator('#report-ranges').getByRole('link', { name: 'This year' })).toHaveAttribute('aria-current', 'true');
    await context.close();
});
