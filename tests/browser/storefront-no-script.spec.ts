import { expect, test } from '@playwright/test';
import { literal } from './support/inventory';
import { busiestMake, countInStock, expectCars, NEWEST, PAGE, PRICE, slugs } from './support/storefront';

test.use({ javaScriptEnabled: false });

test('with JavaScript off, the filter form and the page links are plain GETs that answer the whole page', async ({ page }) => {
    await page.goto('/cars');
    const all = countInStock();
    await expectCars(page, slugs('', NEWEST, PAGE), all);

    const make = busiestMake();
    await page.locator('#cars-make').selectOption(make);
    await page.locator('#cars-sort').selectOption('price');
    await page.locator('#car-filters').getByRole('button', { name: 'Filter' }).click();
    await expect(page).toHaveURL(new RegExp(`/cars\\?make=${make}&year=&min_price=&max_price=&sort=price$`));
    const condition = `AND make = ${literal(make)}`;
    await expectCars(page, slugs(condition, PRICE, PAGE), countInStock(condition));
    await expect(page.locator('#cars-make')).toHaveValue(make);
    await expect(page.locator('#cars-sort')).toHaveValue('price');

    await page.goto('/cars?sort=price');
    await page.locator('#car-pages').getByRole('link', { name: '2', exact: true }).click();
    await expect(page).toHaveURL(/\/cars\?sort=price&page=2$/);
    await expectCars(page, slugs('', PRICE, PAGE, PAGE), all, 2);

    // A malformed query falls back to every car, newest first.
    await page.goto('/cars?make=Nope&year=1900&min_price=cheap&sort=cost&page=0');
    await expectCars(page, slugs('', NEWEST, PAGE), all);
    await expect(page.locator('#cars-make')).toHaveValue('');
});
