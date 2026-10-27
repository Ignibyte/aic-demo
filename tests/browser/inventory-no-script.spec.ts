import { expect, test } from '@playwright/test';
import { countWhere, fillSignIn, pageVins, shownVins, summary } from './support/inventory';

test.use({ javaScriptEnabled: false });

test('signed out the list goes to sign-in, and with JavaScript off the filter form is a plain GET', async ({ page }) => {
    await page.goto('/app/inventory');
    await expect(page).toHaveURL(/\/sign-in\?next=%2Fapp%2Finventory$/);
    await fillSignIn(page);

    await page.locator('#filter-q').fill('a');
    await page.locator('#filter-status').selectOption('on_hold');
    await page.locator('#inventory-filters').getByRole('button', { name: 'Filter' }).click();
    await expect(page).toHaveURL(/\/app\/inventory\?q=a&make=&status=on_hold&min_price=&max_price=&sort=$/);

    const kept = "WHERE (make ILIKE '%a%' OR model ILIKE '%a%' OR vin ILIKE '%a%') AND status = 'on_hold'";
    const expected = pageVins(kept, 'added_on DESC');
    expect(expected.length).toBeGreaterThan(0);
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(kept)));
    await expect(shownVins(page)).toHaveText(expected);
    await expect(page.locator('#filter-q')).toHaveValue('a');
    await expect(page.locator('#filter-status')).toHaveValue('on_hold');
});
