import { expect, test } from '@playwright/test';
import { literal, sql } from './support/inventory';
import {
    busiestMake, countInStock, documentKept, dollars, expectCars, markDocument, NEWEST, PAGE, PRICE, PRICE_DESCENDING, slugs,
} from './support/storefront';

test('signed out, the list filters, sorts and pages in place, and a card opens its car', async ({ page }) => {
    await page.goto('/cars');
    await expect(page).toHaveTitle('Cars for sale · Lotline');
    await expect(page.locator('meta[name="tessera-layout"]')).toHaveAttribute('content', 'lotline-store');

    const all = countInStock();
    expect(all).toBeGreaterThan(PAGE);
    await expectCars(page, slugs('', NEWEST, PAGE), all);
    await markDocument(page);

    // A make and a price range, through the filter form: the results alone are swapped and the URL follows.
    const make = busiestMake();
    const filtered = `AND make = ${literal(make)} AND list_price >= 10000 AND list_price <= 45000`;
    await page.locator('#cars-make').selectOption(make);
    await page.locator('#cars-min-price').fill('10000');
    await page.locator('#cars-max-price').fill('45000');
    await page.locator('#car-filters').getByRole('button', { name: 'Filter' }).click();
    await expect(page).toHaveURL(new RegExp(`/cars\\?make=${make}&year=&min_price=10000&max_price=45000&sort=$`));
    const kept = slugs(filtered, NEWEST, PAGE);
    expect(kept.length).toBeGreaterThan(0);
    await expectCars(page, kept, countInStock(filtered));

    // The same filters, highest price first.
    await page.locator('#cars-sort').selectOption('-price');
    await page.locator('#car-filters').getByRole('button', { name: 'Filter' }).click();
    await expect(page).toHaveURL(/sort=-price$/);
    await expectCars(page, slugs(filtered, PRICE_DESCENDING, PAGE), countInStock(filtered));
    expect(await documentKept(page)).toBe(true);

    // A year alone, lowest price first.
    const year = sql("SELECT year FROM vehicles WHERE status = 'in_stock' GROUP BY year ORDER BY count(*) DESC, year LIMIT 1")[0];
    await page.locator('#cars-make').selectOption('');
    await page.locator('#cars-min-price').fill('');
    await page.locator('#cars-max-price').fill('');
    await page.locator('#cars-year').selectOption(year);
    await page.locator('#cars-sort').selectOption('price');
    await page.locator('#car-filters').getByRole('button', { name: 'Filter' }).click();
    await expect(page).toHaveURL(new RegExp(`/cars\\?make=&year=${year}&min_price=&max_price=&sort=price$`));
    await expectCars(page, slugs(`AND year = ${year}`, PRICE, PAGE), countInStock(`AND year = ${year}`));

    // Every car again, then its second page through the page links, in place.
    await page.goto('/cars');
    await markDocument(page);
    await page.locator('#car-pages').getByRole('link', { name: '2', exact: true }).click();
    await expect(page).toHaveURL(/\/cars\?page=2$/);
    await expectCars(page, slugs('', NEWEST, PAGE, PAGE), all, 2);
    await expect(page.locator('#car-pages a[aria-current="page"]')).toHaveText('2');
    expect(await documentKept(page)).toBe(true);

    // A card opens its car's page with its details and price.
    const [first] = slugs('', NEWEST, 1, PAGE);
    const [line] = sql(`SELECT year, make, model, trim, body_style, mileage, exterior_colour, list_price FROM vehicles WHERE status = 'in_stock' ORDER BY ${NEWEST} LIMIT 1 OFFSET ${PAGE}`);
    const [carYear, carMake, carModel, trim, bodyStyle, mileage, colour, price] = line.split('|');
    await page.locator(`#car-${first}`).click();
    await expect(page).toHaveURL(new RegExp(`/cars/${first}$`));
    await expect(page.locator('h1#car')).toHaveText(`${carYear} ${carMake} ${carModel}`);
    await expect(page).toHaveTitle(`${carYear} ${carMake} ${carModel} · Lotline`);
    await expect(page.locator('#car-price')).toHaveText(dollars(Number(price)));
    await expect(page.locator('article dl dd')).toHaveText([
        carYear, carMake, carModel, trim, bodyStyle, `${Number(mileage).toLocaleString('en-US')} miles`, colour,
    ]);
    await page.getByRole('link', { name: 'All cars for sale' }).click();
    await expect(page).toHaveURL(/\/cars$/);
});
