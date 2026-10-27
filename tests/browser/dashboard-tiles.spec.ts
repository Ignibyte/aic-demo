import { expect, test, type Page } from '@playwright/test';
import {
    PRICE, counted, count, insertDashboardLooks, login, money, removeDashboardLooks, removeDashboardSale, signIn, tiles, type Tiles,
} from './support/dashboard';

test.beforeEach(() => {
    removeDashboardLooks();
    insertDashboardLooks();
});
test.afterEach(() => removeDashboardLooks());

// Checks each tile's figure and the line under it against what Postgres gives.
async function expectTiles(page: Page, figures: Tiles): Promise<void> {
    await expect(page.locator('#dashboard-tiles')).toHaveAttribute('aria-label', 'This month on the lot');
    await expect(page.locator('#dashboard-tiles .stat-title')).toHaveText([
        'Vehicles in stock', 'Sales this month', 'Revenue this month', 'Average days on the lot',
    ]);
    await expect(page.locator('#tile-in-stock')).toHaveText(count(figures.inStock));
    await expect(page.locator('#tile-sales')).toHaveText(count(figures.sales));
    await expect(page.locator('#tile-revenue')).toHaveText(money(figures.revenue));
    await expect(page.locator('#tile-days')).toHaveText(counted(figures.days, 'day', 'days'));
    await expect(page.locator('#dashboard-tiles .stat-desc')).toHaveText([
        `${count(figures.onHold)} more on hold`,
        `${count(figures.lastSales)} last month`,
        `${money(figures.lastRevenue)} last month`,
        `over the ${counted(figures.notSold, 'vehicle', 'vehicles')} not sold`,
    ]);
}

test('the dashboard greets the member in its header, and its tiles match Postgres', async ({ page }) => {
    await signIn(page);

    // The greeting the old page showed, now in the header; the old welcome panel is gone.
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Welcome, ${login}`);
    await expect(page.locator('header p')).toHaveText('Admin at Ridgeway Motors');
    await expect(page.locator('#welcome-heading')).toHaveCount(0);
    await expect(page.locator('#app-coming')).toHaveCount(0);

    // The sidebar's Dashboard goes to /app, current here and on no other staff page.
    const dashboard = page.getByRole('link', { name: 'Dashboard', exact: true });
    await expect(dashboard).toHaveAttribute('href', '/app');
    await expect(dashboard).toHaveAttribute('aria-current', 'page');

    // The look's sale today is counted in this month's figures.
    const withSale = tiles();
    expect(withSale.sales).toBeGreaterThanOrEqual(1);
    expect(withSale.revenue).toBeGreaterThanOrEqual(PRICE);
    await expectTiles(page, withSale);

    // Without it, this month has one sale and its price fewer.
    removeDashboardSale();
    await page.reload();
    const withoutSale = tiles();
    expect(withoutSale.sales).toBe(withSale.sales - 1);
    expect(withoutSale.revenue).toBe(withSale.revenue - PRICE);
    await expectTiles(page, withoutSale);

    await page.getByRole('link', { name: 'Inventory', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/inventory$/);
    await expect(page.getByRole('link', { name: 'Dashboard', exact: true })).not.toHaveAttribute('aria-current', 'page');
});
