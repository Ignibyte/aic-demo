import { expect, test } from '@playwright/test';
import { countWhere, literal, pageVins, shownVins, signIn, sql, summary } from './support/inventory';

test('signed in, the sidebar links to the inventory, and filtering gives the rows Postgres gives', async ({ page }) => {
    await signIn(page);

    const inventory = page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Inventory' });
    await expect(inventory).toHaveAttribute('href', '/app/inventory');
    await expect(inventory).toHaveAttribute('aria-current', 'page');

    const plain = countWhere('');
    expect(plain).toBeGreaterThan(0);
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(plain));
    await expect(shownVins(page)).toHaveText(pageVins('', 'added_on DESC'));

    // The cost is the lot's own: no head names it and no row shows it.
    await expect(page.locator('#vehicles-table thead')).not.toContainText('Cost');
    for (const line of sql('SELECT vin, cost FROM vehicles ORDER BY added_on DESC, vin ASC LIMIT 15')) {
        const [vin, cost] = line.split('|');
        const row = page.locator('#vehicles-table tbody tr', { has: page.locator('td:first-child', { hasText: vin }) });
        await expect(row).not.toContainText(`$${Number(cost).toLocaleString('en-US')}`);
    }

    // The make with the most vehicles in stock, so the filters keep some.
    const make = sql("SELECT make FROM vehicles WHERE status = 'in_stock' GROUP BY make ORDER BY count(*) DESC, make LIMIT 1")[0];
    await page.locator('#filter-make').selectOption(make);
    await page.locator('#filter-status').selectOption('in_stock');
    await page.locator('#filter-min-price').fill('10000');
    await page.locator('#filter-max-price').fill('45000');
    await page.locator('#inventory-filters').getByRole('button', { name: 'Filter' }).click();

    const filtered = `WHERE make = ${literal(make)} AND status = 'in_stock' AND list_price >= 10000 AND list_price <= 45000`;
    const expected = pageVins(filtered, 'added_on DESC');
    expect(expected.length).toBeGreaterThan(0);
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(filtered)));
    await expect(shownVins(page)).toHaveText(expected);
    await expect(page.locator('#filter-make')).toHaveValue(make);
    await expect(page.locator('#filter-status')).toHaveValue('in_stock');
    await expect(page.locator('#filter-min-price')).toHaveValue('10000');
    await expect(page.locator('#filter-max-price')).toHaveValue('45000');

    // Clear is a Tessera visit: the search waits for its answer, or it types into the old form and sends its filters.
    await page.locator('#inventory-filters').getByRole('link', { name: 'Clear' }).click();
    await expect(page).toHaveURL(/\/app\/inventory$/);
    await expect(page.locator('#filter-make')).toHaveValue('');
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(plain));
    await page.locator('#filter-q').fill('cr');
    await page.locator('#inventory-filters').getByRole('button', { name: 'Filter' }).click();

    const searched = "WHERE make ILIKE '%cr%' OR model ILIKE '%cr%' OR vin ILIKE '%cr%'";
    const found = pageVins(searched, 'added_on DESC');
    expect(found.length).toBeGreaterThan(0);
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(searched)));
    await expect(shownVins(page)).toHaveText(found);
    await expect(page.locator('#filter-q')).toHaveValue('cr');
});
