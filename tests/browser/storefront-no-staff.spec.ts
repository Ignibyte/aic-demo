import { expect, type Page, test } from '@playwright/test';
import { signIn, sql } from './support/inventory';
import { dollars, NEWEST, PAGE, SLUG } from './support/storefront';

// Holds that the page has the public header and footer and nothing of the staff pages: no drawer, no sidebar, no
// main navigation, no account menu and no sign-in or sign-out control.
async function expectPublicOnly(page: Page): Promise<void> {
    await expect(page.locator('#store-header')).toContainText('Ridgeway Motors');
    await expect(page.locator('#store-footer')).toBeVisible();
    await expect(page.locator('#lotline-drawer')).toHaveCount(0);
    await expect(page.locator('[data-region="sidebar"], [data-region="header"]')).toHaveCount(0);
    await expect(page.getByRole('navigation', { name: 'Main' })).toHaveCount(0);
    await expect(page.locator('#account')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Sign out' })).toHaveCount(0);
}

// Holds that the page shows no VIN, and none of these costs.
async function expectNoVinOrCost(page: Page, costs: number[]): Promise<void> {
    const text = await page.locator('body').innerText();
    const html = await page.content();
    expect(html).not.toMatch(/0LT[0-9A-HJ-NPR-Z]{14}/);

    for (const cost of costs) {
        expect(text).not.toContain(dollars(cost));
    }
}

test('the public pages hold no staff navigation, VIN or cost, signed out or in, and no staff page holds the public header', async ({ page }) => {
    const rows = sql(`SELECT ${SLUG}, cost FROM vehicles WHERE status = 'in_stock' ORDER BY ${NEWEST} LIMIT ${PAGE}`);
    const costs = rows.map((row) => Number(row.split('|')[1]));
    const [slug, cost] = rows[0].split('|');

    for (const path of ['/cars', `/cars/${slug}`]) {
        await page.goto(path);
        await expectPublicOnly(page);
        await expectNoVinOrCost(page, path === '/cars' ? costs : [Number(cost)]);
    }

    await signIn(page);

    for (const path of ['/cars', `/cars/${slug}`]) {
        await page.goto(path);
        await expectPublicOnly(page);
        await expectNoVinOrCost(page, path === '/cars' ? costs : [Number(cost)]);
    }

    for (const path of ['/app', '/app/inventory']) {
        await page.goto(path);
        await expect(page.getByRole('navigation', { name: 'Main' })).toBeVisible();
        await expect(page.locator('#store-header, #store-footer')).toHaveCount(0);
    }
});
