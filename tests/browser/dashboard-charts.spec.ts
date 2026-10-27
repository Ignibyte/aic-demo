import { expect, test, type Page } from '@playwright/test';
import {
    counted, count, insertDashboardLooks, login, makes, money, months, removeDashboardLooks, salespeople, signIn,
} from './support/dashboard';

test.beforeEach(() => {
    removeDashboardLooks();
    insertDashboardLooks();
});
test.afterEach(() => removeDashboardLooks());

// Checks a chart's title and summary: the SVG's title and description, which label it, and the caption.
async function expectLabelled(page: Page, id: string, title: string, summary: string): Promise<void> {
    await expect(page.locator(`#${id} svg`)).toHaveAttribute('role', 'img');
    await expect(page.locator(`#${id} svg`)).toHaveAttribute('aria-labelledby', `${id}-title ${id}-desc`);
    await expect(page.locator(`#${id}-title`)).toHaveText(title);
    await expect(page.locator(`#${id}-desc`)).toHaveText(summary);
    await expect(page.locator(`#${id} figcaption`)).toHaveText(summary);
}

test('each chart has its title and summary, and its bars or columns match Postgres', async ({ page }) => {
    await signIn(page);

    // Sales by month: twelve columns, the oldest first, the look's sale today in the last.
    const twelve = months();
    expect(twelve).toHaveLength(12);
    expect(twelve[11].sales).toBeGreaterThanOrEqual(1);
    const total = twelve.reduce((sum, month) => sum + month.sales, 0);
    const busiest = twelve.reduce((most, month) => (month.sales >= most.sales ? month : most));
    await expect(page.locator('#sales-heading')).toHaveText('Sales by month');
    await expectLabelled(
        page,
        'sales-chart',
        'Sales by month',
        `${counted(total, 'sale', 'sales')} over the last twelve months; the most, ${count(busiest.sales)}, in ${busiest.name}.`,
    );
    await expect(page.locator('#sales-chart rect > title')).toHaveText(
        twelve.map((month) => `${month.label} ${month.note}: ${count(month.sales)}`),
    );

    // Inventory by make: a bar per make in stock or on hold, the most first.
    const byMake = makes();
    const stock = byMake.reduce((sum, bar) => sum + bar.amount, 0);
    await expect(page.locator('#makes-heading')).toHaveText('Inventory by make');
    await expectLabelled(
        page,
        'makes-chart',
        'Inventory by make',
        `${counted(stock, 'vehicle', 'vehicles')} in stock or on hold across ${counted(byMake.length, 'make', 'makes')};`
            + ` ${byMake[0].label} has the most, ${count(byMake[0].amount)}.`,
    );
    await expect(page.locator('#makes-chart rect > title')).toHaveText(byMake.map((bar) => `${bar.label}: ${count(bar.amount)}`));

    // Revenue by salesperson: a bar per member of staff with sales in the twelve months, the most first, the gate's user
    // among them for the look's sale.
    const bySeller = salespeople();
    const revenue = bySeller.reduce((sum, bar) => sum + bar.amount, 0);
    expect(bySeller.map((bar) => bar.label)).toContain(login);
    await expect(page.locator('#salespeople-heading')).toHaveText('Revenue by salesperson');
    await expectLabelled(
        page,
        'salespeople-chart',
        'Revenue by salesperson',
        `${money(revenue)} over the last twelve months from ${counted(bySeller.length, 'salesperson', 'salespeople')};`
            + ` ${bySeller[0].label} sold the most, ${money(bySeller[0].amount)}.`,
    );
    await expect(page.locator('#salespeople-chart rect > title')).toHaveText(
        bySeller.map((bar) => `${bar.label}: ${money(bar.amount)}`),
    );

    // Drawn on the server: no script draws the charts, and no element carries an inline style.
    await expect(page.locator('script:not([src])')).toHaveCount(0);
    await expect(page.locator('[style]')).toHaveCount(0);
});
