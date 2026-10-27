import { expect, test, type Page } from '@playwright/test';
import { literal, sql } from './support/inventory';
import { insertVehicle, testVehicle } from './support/vehicles';
import { BUYER, PAGE, filterVin, insertSale, login, money, removeSaleLooks, signIn } from './support/sales';
import { insertCustomer, testCustomer } from './support/customers';

// The month the look's own sales fall in: before every seeded sale, so only they are in it.
const MONTH = '2025-06';

// How many sales the look makes there, one more than a page.
const OWN = PAGE + 1;

// The sales joined as the list shows them.
const FROM = 'FROM sales s JOIN vehicles v ON v.id = s.vehicle_id JOIN customers c ON c.id = s.customer_id JOIN users u ON u.id = s.salesperson_id';

// The condition the filters keep, worked out here and not taken from the app.
function filtered(month: string, salesperson: string): string {
    const conditions = [
        ...(month === '' ? [] : [`to_char(s.sold_on, 'YYYY-MM') = ${literal(month)}`]),
        ...(salesperson === '' ? [] : [`u.email = ${literal(salesperson)}`]),
    ];

    return conditions.length === 0 ? '' : `WHERE ${conditions.join(' AND ')}`;
}

// One page of the rows Postgres keeps, as the table's cells read: day, vehicle, customer, salesperson, price.
function pageCells(where: string, offset = 0): string[] {
    return sql(
        "SELECT to_char(s.sold_on, 'YYYY-MM-DD'), trim(v.year || ' ' || v.make || ' ' || v.model || ' ' || v.trim), c.name, u.name, s.price "
            + `${FROM} ${where} ORDER BY s.sold_on DESC, s.id DESC LIMIT ${PAGE} OFFSET ${offset}`,
    ).flatMap((row) => {
        const [day, vehicle, customer, salesperson, price] = row.split('|');

        return [day, vehicle, customer, salesperson, money(Number(price))];
    });
}

// What the list's summary says for that many sales at that page.
function summary(count: number, page = 1): string {
    const pages = Math.ceil(count / PAGE);
    const sales = `${count} ${count === 1 ? 'sale' : 'sales'}`;

    return pages > 1 ? `${sales}, page ${page} of ${pages}` : sales;
}

// Checks the totals, the summary and the page's rows against Postgres.
async function expectList(page: Page, where: string, at = 1): Promise<void> {
    const [count, revenue, gross] = sql(`SELECT count(*), coalesce(sum(s.price), 0), coalesce(sum(s.price - v.cost), 0) ${FROM} ${where}`)[0]
        .split('|')
        .map(Number);
    await expect(page.locator('#sales-count')).toHaveText(count.toLocaleString('en-US'));
    await expect(page.locator('#sales-revenue')).toHaveText(money(revenue));
    await expect(page.locator('#sales-gross')).toHaveText(money(gross));
    await expect(page.locator('#sales-table-summary')).toHaveText(summary(count, at));
    await expect(page.locator('#sales-table tbody tr td')).toHaveText(pageCells(where, (at - 1) * PAGE));
}

test.beforeEach(() => {
    removeSaleLooks();
    insertCustomer(testCustomer('Probe Buyer', BUYER));

    for (let n = 1; n <= OWN; n += 1) {
        const vin = filterVin(n);
        insertVehicle({ ...testVehicle(vin), addedOn: '2025-05-20', cost: 15000 + n * 10 });
        insertSale(vin, { customer: BUYER, salesperson: login, price: 15500 + n * 100, soldOn: `${MONTH}-${String(n).padStart(2, '0')}` });
    }
});
test.afterEach(() => removeSaleLooks());

test('the list, filtered by month and salesperson, and its totals match Postgres', async ({ page }) => {
    await signIn(page);
    await page.getByRole('link', { name: 'Sales', exact: true }).click();
    await expect(page).toHaveURL(/\/app\/sales$/);
    await expect(page.getByRole('link', { name: 'Sales', exact: true })).toHaveAttribute('aria-current', 'page');
    await expectList(page, '');
    await expect(page.locator('#sales-pages').getByRole('link', { name: '2' })).toHaveAttribute('href', '/app/sales?page=2');

    // A seeded month and salesperson: the rows and totals Postgres gives for the same filters.
    const [month, seller] = sql(`SELECT to_char(s.sold_on, 'YYYY-MM'), u.email ${FROM} WHERE u.email <> ${literal(login)} ORDER BY s.sold_on DESC LIMIT 1`)[0].split('|');
    await page.locator('#filter-month').selectOption(month);
    await page.locator('#filter-salesperson').selectOption(seller);
    await page.locator('#sales-filters').getByRole('button', { name: 'Filter' }).click();
    await expect(page).toHaveURL(new RegExp(`/app/sales\\?month=${month}&salesperson=${encodeURIComponent(seller)}$`));
    await expectList(page, filtered(month, seller));
    await expect(page.locator('#filter-month')).toHaveValue(month);
    await expect(page.locator('#filter-salesperson')).toHaveValue(seller);

    // The look's own month and seller: two pages, and the page links keep both filters.
    await page.locator('#filter-month').selectOption(MONTH);
    await page.locator('#filter-salesperson').selectOption(login);
    await page.locator('#sales-filters').getByRole('button', { name: 'Filter' }).click();
    const where = filtered(MONTH, login);
    await expectList(page, where);
    const second = page.locator('#sales-pages').getByRole('link', { name: '2' });
    await expect(second).toHaveAttribute('href', `/app/sales?month=${MONTH}&salesperson=${encodeURIComponent(login)}&page=2`);
    await second.click();
    await expectList(page, where, 2);

    // Clear goes back to every sale.
    await page.locator('#sales-filters').getByRole('link', { name: 'Clear' }).click();
    await expectList(page, '');
});
