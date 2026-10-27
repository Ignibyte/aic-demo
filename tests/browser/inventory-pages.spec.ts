import { expect, test } from '@playwright/test';
import { checkDigitHolds, countWhere, PAGE, pageVins, shownVins, signIn, summary } from './support/inventory';

test('the pages hold 15 rows each, the rows Postgres gives there, and their links keep the filter and the sort', async ({ page }) => {
    await signIn(page);
    await page.goto('/app/inventory?status=in_stock&sort=price');

    const kept = "WHERE status = 'in_stock'";
    const total = countWhere(kept);
    const pages = Math.ceil(total / PAGE);
    expect(pages).toBeGreaterThan(2);

    const shown: string[] = [];
    const link = (label: string) => page.locator('#vehicles-pages a', { hasText: new RegExp(`^${label}$`) });

    await expect(shownVins(page)).toHaveCount(PAGE);
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'list_price ASC'));
    shown.push(...(await shownVins(page).allTextContents()));
    await expect(link('2')).toHaveAttribute('href', '/app/inventory?status=in_stock&sort=price&page=2');
    await expect(link('1')).toHaveAttribute('aria-current', 'page');

    await link('2').click();
    await expect(link('2')).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(total, 2));
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'list_price ASC', PAGE));
    shown.push(...(await shownVins(page).allTextContents()));

    await link(String(pages)).click();
    await expect(link(String(pages))).toHaveAttribute('aria-current', 'page');
    await expect(shownVins(page)).toHaveCount(total - PAGE * (pages - 1));
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'list_price ASC', PAGE * (pages - 1)));
    await expect(link('1')).toHaveAttribute('href', '/app/inventory?status=in_stock&sort=price');
    shown.push(...(await shownVins(page).allTextContents()));

    // Every VIN seen is invented: the unassigned maker 0LT, with a valid check digit.
    for (const vin of shown) {
        expect(vin.startsWith('0LT'), vin).toBe(true);
        expect(checkDigitHolds(vin), vin).toBe(true);
    }
});
