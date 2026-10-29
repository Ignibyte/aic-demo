import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { expectBellCount } from './support/inbox';
import { literal, sql } from './support/inventory';
import { inquiryThanks, mailFor, mailText } from './support/leads';
import { FIXTURES, addPhoto } from './support/photos';
import { fillVehicleForm } from './support/vehicles';
import {
    VISITOR, carPath, carStatus, carTitle, csvLines, insertMember, journeyCar, money, monthStart, removeJourneyLooks,
    restoreSalespeople, setAsideSalespeople, signInMember, storedLead, today, work,
} from './support/journey';

test.beforeEach(async () => removeJourneyLooks());
test.afterEach(async () => removeJourneyLooks());

// One day on the site, end to end: the lot, the public list, the inbox, holds, sales, the dashboard and the reports
// together, as the people who use them would go through them. This look depends on DDEV, as the leads' and reports'
// looks do: the worker runs in its web container (`ddev exec bin/aic queue:work`), and the mail is read from Mailpit.
test('a car added in the morning is asked about, held, sold, counted and exported by the evening', async ({ browser, page }) => {
    test.setTimeout(180_000);
    const errors = collectFormErrors(page);
    const car = journeyCar(1);
    const title = carTitle(car);
    const manager = insertMember('Mia Journey', 'sales_manager');
    const seller = insertMember('Sam Journey', 'salesperson');

    // A sales manager adds the car from the inventory, then two photos on its page.
    await signInMember(page, manager);
    await page.getByRole('link', { name: 'Inventory' }).first().click();
    await expect(page).toHaveURL(/\/app\/inventory$/);
    await page.getByRole('link', { name: 'Add a vehicle' }).click();
    await expect(page).toHaveURL(/\/app\/inventory\/new$/);
    await fillVehicleForm(page, car);
    await page.locator('#vehicle-save').click();
    await expect(page).toHaveURL(new RegExp(`/app/inventory/${car.vin}$`));
    await addPhoto(page, FIXTURES.jpeg);
    await expect(page.locator('#vehicle-photo-1')).toHaveAttribute('src', /^\/files\/photos\/[0-9a-f]{64}\.jpg$/);
    await addPhoto(page, FIXTURES.png);
    await expect(page.locator('#vehicle-photo-2')).toHaveAttribute('src', /^\/files\/photos\/[0-9a-f]{64}\.png$/);

    // The salesperson is on their dashboard, nothing in the bell.
    const sellerContext = await browser.newContext();
    const sellerPage = await sellerContext.newPage();
    const sellerErrors = collectFormErrors(sellerPage);
    await signInMember(sellerPage, seller);
    await expectBellCount(sellerPage, 0);

    // A visitor finds the car on the public list by its make, opens it and asks about it.
    const visitorContext = await browser.newContext();
    const visitor = await visitorContext.newPage();
    await visitor.goto('/cars');
    await visitor.locator('#cars-make').selectOption(car.make);
    await visitor.locator('#car-filters').getByRole('button', { name: 'Filter' }).click();
    await expect(visitor).toHaveURL(new RegExp(`/cars\\?make=${car.make}&`));
    const path = carPath(car);
    const card = visitor.locator(`a[href="${path}"]`).first();
    await expect(card).toBeVisible();
    await expect(visitor.locator(`#car-${path.slice('/cars/'.length)} img`)).toHaveAttribute('src', /^\/files\/photos\/[0-9a-f]{64}\.jpg$/);
    await card.click();
    await expect(visitor).toHaveURL(new RegExp(`${path}$`));
    await expect(visitor.locator('#car-photo-1')).toHaveAttribute('alt', `${car.year} ${car.make} ${car.model}, photo 1 of 2`);
    await expect.poll(() => visitor.locator('#car-photo-1').evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);
    await visitor.locator('#inquiry-name').fill(VISITOR.name);
    await visitor.locator('#inquiry-email').fill(VISITOR.email);
    await visitor.locator('#inquiry-phone').fill(VISITOR.phone);
    await visitor.locator('#inquiry-message').fill(VISITOR.message);
    const setAside = setAsideSalespeople();

    try {
        await visitor.locator('#inquiry-send').click();
        await expect(visitor.locator('#inquiry-thanks')).toHaveText(inquiryThanks(VISITOR));
    } finally {
        restoreSalespeople(setAside);
    }

    const lead = storedLead();
    expect(lead.seller).toBe(seller.email);

    // The worker runs: the salesperson's bell lights in the page they have open, and its item opens the lead.
    expect(work()).toMatch(/^queue:work ran \d+ job\(s\)\.$/);
    await expectBellCount(sellerPage, 1);
    await sellerPage.locator('#bell').click();
    const item = sellerPage.locator('#bell-items > li').first();
    await expect(item).toContainText(`New question about the ${title}`);
    await item.locator('a').click();
    await expect(sellerPage).toHaveURL(new RegExp(`/app/leads/${lead.id}$`));
    await expect(sellerPage.locator('#lead-title')).toHaveText(VISITOR.name);
    await expect(sellerPage.locator('dd', { hasText: VISITOR.message })).toHaveCount(1);
    await expectBellCount(sellerPage, 0);

    // The visitor is no customer yet: the salesperson adds them from the lead, then holds the car for them.
    await expect(sellerPage.locator('dd', { hasText: 'No customer has this email' })).toHaveCount(1);
    await sellerPage.getByRole('link', { name: 'Customers' }).first().click();
    await sellerPage.getByRole('link', { name: 'Add a customer' }).click();
    await sellerPage.locator('#customer-name').fill(VISITOR.name);
    await sellerPage.locator('#customer-email').fill(VISITOR.email);
    await sellerPage.locator('#customer-phone').fill(VISITOR.phone);
    await sellerPage.locator('#customer-address').fill('8 Harbour Road, Ridgeway');
    await sellerPage.locator('#customer-save').click();
    await expect(sellerPage.locator('#customer-notice')).toHaveText('Added this customer.');
    await sellerPage.goto(`/app/holds/new?vehicle=${car.vin}`);
    await expect(sellerPage.locator('#hold-vehicle')).toHaveValue(car.vin);
    await sellerPage.locator('#hold-customer').selectOption(VISITOR.email);
    await sellerPage.locator('#hold-note').fill('Coming Saturday to drive it');
    await sellerPage.locator('#hold-save').click();
    await expect(sellerPage.locator('#holds-notice')).toHaveText(`Put the ${title} on hold for ${VISITOR.name} for 48 hours.`);
    expect(carStatus(car)).toBe('on_hold');

    // Held, the car leaves the public list.
    await visitor.goto(`/cars?make=${car.make}`);
    await expect(visitor.locator(`a[href="${path}"]`)).toHaveCount(0);

    // The salesperson sells it to the customer it is held for.
    await sellerPage.goto(`/app/sales/new?vehicle=${car.vin}`);
    await expect(sellerPage.locator('#sale-price')).toHaveValue(String(car.listPrice));
    await sellerPage.locator('#sale-customer').selectOption(VISITOR.email);
    await sellerPage.locator('#sale-salesperson').selectOption(seller.email);
    await sellerPage.locator('#sale-save').click();
    await expect(sellerPage).toHaveURL(/\/app\/sales\/\d+$/);
    await expect(sellerPage.locator('#sale-notice')).toHaveText('Recorded the sale.');
    expect(carStatus(car)).toBe('sold');

    // Sold, the car is off the public list and its page is gone.
    await visitor.goto(`/cars?make=${car.make}`);
    await expect(visitor.locator(`a[href="${path}"]`)).toHaveCount(0);
    expect((await visitor.request.get(path)).status()).toBe(404);

    // The manager's dashboard counts the sale this month and shows it first in the latest sales.
    await page.goto('/app');
    const [sales, revenue] = sql(
        "SELECT count(*) || '|' || coalesce(sum(price), 0) FROM sales WHERE sold_on >= date_trunc('month', current_date) "
            + "AND sold_on < date_trunc('month', current_date) + interval '1 month'",
    )[0].split('|').map(Number);
    await expect(page.locator('#tile-sales')).toHaveText(sales.toLocaleString('en-US'));
    await expect(page.locator('#tile-revenue')).toHaveText(money(revenue));
    await expect(page.locator('#sales-feed tbody tr').first()).toContainText(title);
    await expect(page.locator('#sales-feed tbody tr').first()).toContainText(VISITOR.name);

    // The sales report over the month names the salesperson with the sale.
    const range = `from=${monthStart()}&to=${today()}`;
    await page.goto(`/app/reports?${range}`);
    const sellerLine = page.locator('#report-salespeople-table tbody tr', { hasText: seller.name });
    await expect(sellerLine.locator('td')).toHaveText([seller.name, '1', money(car.listPrice)]);

    // The manager exports it; once the worker has run, the CSV holds the sale.
    await page.locator('#report-export-salespeople-button').click();
    await expect(page).toHaveURL(new RegExp(`/app/reports\\?${range}&export=\\d+$`));
    expect(work()).toMatch(/^queue:work ran \d+ job\(s\)\.$/);
    await page.reload();
    await expect(page.locator('#report-export-status')).toHaveText(`The Revenue and units by salesperson CSV for ${monthStart()} to ${today()} is ready.`);
    const saving = page.waitForEvent('download');
    await page.locator('#report-export-download').click();
    const download = await saving;
    expect(download.suggestedFilename()).toBe(`salespeople-${monthStart()}-to-${today()}.csv`);
    const lines = csvLines(readFileSync(await download.path(), 'utf8'));
    expect(lines).toContainEqual([seller.name, '1', String(car.listPrice)]);

    // The visitor has their confirmation in Mailpit, naming the car and the dealership.
    const toVisitor = async () => (await mailFor(VISITOR.email)).filter((found) => found.to.includes(VISITOR.email));
    await expect.poll(async () => (await toVisitor()).length).toBe(1);
    const [mail] = await toVisitor();
    const text = await mailText(mail);
    expect(text).toContain(title);
    const [dealership] = sql("SELECT coalesce((SELECT name FROM dealership_profile WHERE slot = 'main'), 'Ridgeway Motors')");
    expect(text).toContain(dealership);
    expect(sql(`SELECT count(*) FROM sales s JOIN customers c ON c.id = s.customer_id WHERE c.email = ${literal(VISITOR.email)}`)).toEqual(['1']);

    await visitorContext.close();
    await sellerContext.close();
    expect(errors).toEqual([]);
    expect(sellerErrors).toEqual([]);
});
