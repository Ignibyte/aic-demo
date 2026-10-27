import { expect, type Page } from '@playwright/test';
import { checkDigitHolds, literal, sql } from './inventory';
import { insertCustomer, testCustomer } from './customers';
import { insertVehicle, testVehicle } from './vehicles';

// The gate's user, made by `bin/aic user:add` for each run: an admin whose name is its login, and the looks' seller.
export const login = process.env.AIC_GATE_LOGIN ?? '';
const password = process.env.AIC_GATE_PASSWORD ?? '';

// The stem of the dashboard looks' VINs, `0LTPWDSH{check digit}SR99940{n}`: fictional, and no other look's.
const STEM = '0LTPWDSH';

// The `n`th of the dashboard looks' VINs, its check digit found by trying each one `checkDigitHolds` accepts.
function dashboardVin(n: number): string {
    const serial = `SR99940${n}`;
    const digit = [...'0123456789X'].find((candidate) => checkDigitHolds(`${STEM}${candidate}${serial}`)) ?? '';

    return `${STEM}${digit}${serial}`;
}

// The looks' two vehicles: one in stock, one sold today.
export const VINS = { stock: dashboardVin(1), sold: dashboardVin(2) };

// The looks' buyer: `pw.` and `@example.test`, so no seeded customer and no native step shares the email.
export const BUYER = 'pw.dashboard@example.test';

// The price of the looks' sale, in whole dollars.
export const PRICE = 18750;

// Deletes the looks' sale, then their vehicles and their buyer, so a look starts and ends with none of its own; the sale
// goes first, since its foreign keys hold the rows it names.
export function removeDashboardLooks(): void {
    sql(`DELETE FROM sales WHERE vehicle_id IN (SELECT id FROM vehicles WHERE vin LIKE '${STEM}%')`
        + ` OR customer_id IN (SELECT id FROM customers WHERE email = ${literal(BUYER)})`);
    sql(`DELETE FROM vehicles WHERE vin LIKE '${STEM}%'`);
    sql(`DELETE FROM customers WHERE email = ${literal(BUYER)}`);
}

// Puts the looks' rows in the database: a vehicle in stock for thirty days, and one sold today by the gate's user.
export function insertDashboardLooks(): void {
    const monthAgo = sql('SELECT (current_date - 30)::text')[0];
    insertVehicle({ ...testVehicle(VINS.stock), addedOn: monthAgo });
    insertVehicle({ ...testVehicle(VINS.sold, 'sold'), addedOn: monthAgo });
    insertCustomer(testCustomer('Dashboard Buyer', BUYER));
    sql(
        'INSERT INTO sales (vehicle_id, customer_id, salesperson_id, price, sold_on, created_at)'
            + ` SELECT v.id, c.id, u.id, ${PRICE}, current_date, now() FROM vehicles v, customers c, users u`
            + ` WHERE v.vin = ${literal(VINS.sold)} AND c.email = ${literal(BUYER)} AND u.email = ${literal(login)}`,
    );
}

// Deletes the looks' sale alone, leaving its vehicle sold.
export function removeDashboardSale(): void {
    sql(`DELETE FROM sales WHERE vehicle_id IN (SELECT id FROM vehicles WHERE vin LIKE '${STEM}%')`);
}

// Signs the gate's user in through `/app`, which sends a signed-out visitor to sign in and back.
export async function signIn(page: Page): Promise<void> {
    await page.goto('/app');
    await page.locator('#sign-in-email').fill(login);
    await page.locator('#sign-in-password').fill(password);
    await page.locator('#sign-in-submit').click();
    await expect(page).toHaveURL(/\/app$/);
}

// Whole numbers and dollars as the page writes them.
export function count(value: number): string {
    return value.toLocaleString('en-US');
}

export function money(dollars: number): string {
    return `$${count(dollars)}`;
}

// A count with its noun, as the page writes it.
export function counted(value: number, singular: string, plural: string): string {
    return `${count(value)} ${value === 1 ? singular : plural}`;
}

// The first day of the database's month, and of the month eleven before it: the window the charts count.
const THIS_MONTH = "date_trunc('month', current_date)::date";
const FIRST_MONTH = "(date_trunc('month', current_date) - interval '11 months')::date";

// The tiles' figures, worked out here and not taken from the app.
export interface Tiles {
    inStock: number;
    onHold: number;
    sales: number;
    lastSales: number;
    revenue: number;
    lastRevenue: number;
    notSold: number;
    days: number;
}

export function tiles(): Tiles {
    const thisMonth = `sold_on >= ${THIS_MONTH} AND sold_on < ${THIS_MONTH} + interval '1 month'`;
    const lastMonth = `sold_on >= ${THIS_MONTH} - interval '1 month' AND sold_on < ${THIS_MONTH}`;
    const [inStock, onHold, sales, lastSales, revenue, lastRevenue, notSold, days] = sql(
        "SELECT (SELECT count(*) FROM vehicles WHERE status = 'in_stock'), (SELECT count(*) FROM vehicles WHERE status = 'on_hold'),"
            + ` (SELECT count(*) FROM sales WHERE ${thisMonth}), (SELECT count(*) FROM sales WHERE ${lastMonth}),`
            + ` (SELECT coalesce(sum(price), 0) FROM sales WHERE ${thisMonth}), (SELECT coalesce(sum(price), 0) FROM sales WHERE ${lastMonth}),`
            + " (SELECT count(*) FROM vehicles WHERE status IN ('in_stock', 'on_hold')),"
            + " (SELECT coalesce(round(avg(current_date - added_on)), 0) FROM vehicles WHERE status IN ('in_stock', 'on_hold'))",
    )[0].split('|').map(Number);

    return { inStock, onHold, sales, lastSales, revenue, lastRevenue, notSold, days };
}

// One month of the sales chart: its short name, the year under it, its full name and its count.
export interface Month {
    label: string;
    note: string;
    name: string;
    sales: number;
}

// The last twelve months, the oldest first, each with its sales; the year shows under the first and under January.
export function months(): Month[] {
    return sql(
        "SELECT to_char(d, 'Mon'), to_char(d, 'MM'), to_char(d, 'YYYY'), trim(to_char(d, 'Month')) || ' ' || to_char(d, 'YYYY'),"
            + " (SELECT count(*) FROM sales WHERE sold_on >= d AND sold_on < d + interval '1 month')"
            + ` FROM generate_series(${FIRST_MONTH}, ${THIS_MONTH}, interval '1 month') AS d ORDER BY d`,
    ).map((row, index) => {
        const [label, number, year, name, sales] = row.split('|');

        return { label, note: index === 0 || number === '01' ? year : '', name, sales: Number(sales) };
    });
}

// One bar: its label and amount.
export interface Bar {
    label: string;
    amount: number;
}

// The vehicles in stock or on hold per make, the most first, then by make.
export function makes(): Bar[] {
    return bars(
        "SELECT make, count(*) AS n FROM vehicles WHERE status IN ('in_stock', 'on_hold') GROUP BY make ORDER BY n DESC, make",
    );
}

// Each member of staff's sales since the first of the twelve months, in whole dollars, the most first, then by name.
export function salespeople(): Bar[] {
    return bars(
        `SELECT u.name, sum(s.price) AS total FROM users u JOIN sales s ON s.salesperson_id = u.id WHERE s.sold_on >= ${FIRST_MONTH}`
            + ' GROUP BY u.id, u.name ORDER BY total DESC, u.name',
    );
}

function bars(statement: string): Bar[] {
    return sql(statement).map((row) => {
        const [label, amount] = row.split('|');

        return { label, amount: Number(amount) };
    });
}
