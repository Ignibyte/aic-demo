import { expect, type Page } from '@playwright/test';
import { checkDigitHolds, literal, sql } from './inventory';
import { insertCustomer, testCustomer } from './customers';
import { insertVehicle, testVehicle, type TestVehicle } from './vehicles';

// The gate's user, made by `bin/aic user:add` for each run: an admin whose name is its login, and the looks' seller.
export const login = process.env.AIC_GATE_LOGIN ?? '';
const password = process.env.AIC_GATE_PASSWORD ?? '';

// The sales a page of the list holds.
export const PAGE = 15;

// The sale looks' own VINs: fictional (`0LT`), check digits worked out by hand, `0LTPW` and a stem no other look uses.
export const VINS = {
    recorded: '0LTPWSAL2SR999201',
    again: '0LTPWAGN0SR999202',
    voided: '0LTPWVDD9SR999203',
    marked: '0LTPWMRK9SR999204',
};

// The stem of the list look's vehicles, `0LTPWFLT{check digit}SR9993{nn}`.
const FILTER_STEM = '0LTPWFLT';

// The sale looks' buyer: `pw.` and `@example.test`, so no seeded customer and no native step shares the email.
export const BUYER = 'pw.buyer@example.test';

// A sale as the looks put it in the database and read it back: the customer by email, the seller by login.
export interface TestSale {
    customer: string;
    salesperson: string;
    price: number;
    soldOn: string;
}

// The condition that keeps every row the sale looks own: their vehicles' and their buyer's sales.
function ownSales(): string {
    return `vehicle_id IN (SELECT id FROM vehicles WHERE vin IN (${Object.values(VINS).map(literal).join(', ')}) OR vin LIKE '${FILTER_STEM}%')`
        + ` OR customer_id IN (SELECT id FROM customers WHERE email = ${literal(BUYER)})`;
}

// Deletes the looks' sales, then their vehicles and their buyer, so a look starts and ends with none of its own; the
// sales go first, since their foreign keys hold the rows they name.
export function removeSaleLooks(): void {
    sql(`DELETE FROM sales WHERE ${ownSales()}`);
    sql(`DELETE FROM vehicles WHERE vin IN (${Object.values(VINS).map(literal).join(', ')}) OR vin LIKE '${FILTER_STEM}%'`);
    sql(`DELETE FROM customers WHERE email = ${literal(BUYER)}`);
}

// Puts a vehicle in stock and the buyer in the database, and gives the buyer's id.
export function insertVehicleAndBuyer(vin: string): { vehicle: TestVehicle; buyerId: number } {
    const vehicle = testVehicle(vin);
    insertVehicle(vehicle);

    return { vehicle, buyerId: insertCustomer(testCustomer('Probe Buyer', BUYER)) };
}

// Records a sale as the app would, its vehicle marked sold, and gives the sale's id.
export function insertSale(vin: string, sale: TestSale): number {
    const id = Number(sql(
        'INSERT INTO sales (vehicle_id, customer_id, salesperson_id, price, sold_on, created_at)'
            + ` SELECT v.id, c.id, u.id, ${sale.price}, ${literal(sale.soldOn)}, now() FROM vehicles v, customers c, users u`
            + ` WHERE v.vin = ${literal(vin)} AND c.email = ${literal(sale.customer)} AND u.email = ${literal(sale.salesperson)} RETURNING id`,
    )[0]);
    sql(`UPDATE vehicles SET status = 'sold' WHERE vin = ${literal(vin)}`);

    return id;
}

// The sales the database holds for a vehicle, with their ids.
export function storedSales(vin: string): (TestSale & { id: number })[] {
    return sql(
        "SELECT s.id, c.email, u.email, s.price, to_char(s.sold_on, 'YYYY-MM-DD') FROM sales s JOIN vehicles v ON v.id = s.vehicle_id"
            + ` JOIN customers c ON c.id = s.customer_id JOIN users u ON u.id = s.salesperson_id WHERE v.vin = ${literal(vin)} ORDER BY s.id`,
    ).map((row) => {
        const [id, customer, salesperson, price, soldOn] = row.split('|');

        return { id: Number(id), customer, salesperson, price: Number(price), soldOn };
    });
}

// A vehicle's stored status.
export function vehicleStatus(vin: string): string {
    return sql(`SELECT status FROM vehicles WHERE vin = ${literal(vin)}`)[0] ?? '';
}

// The database's today, as the form starts it.
export function today(): string {
    return sql('SELECT current_date::text')[0];
}

// The `n`th of the list look's VINs, its check digit found by trying each one `checkDigitHolds` accepts.
export function filterVin(n: number): string {
    const serial = `SR9993${String(n).padStart(2, '0')}`;
    const digit = [...'0123456789X'].find((candidate) => checkDigitHolds(`${FILTER_STEM}${candidate}${serial}`)) ?? '';

    return `${FILTER_STEM}${digit}${serial}`;
}

// Signs the gate's user in through the form and lands on /app.
export async function signIn(page: Page): Promise<void> {
    await page.goto('/sign-in?next=%2Fapp');
    await page.locator('#sign-in-email').fill(login);
    await page.locator('#sign-in-password').fill(password);
    await page.locator('#sign-in-submit').click();
    await expect(page).toHaveURL(/\/app$/);
}

// Signs the gate's user in and opens a path.
export async function signInAt(page: Page, path: string): Promise<void> {
    await signIn(page);
    await page.goto(path);
}

// Chooses the customer and the seller and types the price and the day; the vehicle is left as it is.
export async function fillSale(page: Page, sale: TestSale): Promise<void> {
    await page.locator('#sale-customer').selectOption(sale.customer);
    await page.locator('#sale-salesperson').selectOption(sale.salesperson);
    await page.locator('#sale-price').fill(String(sale.price));
    await page.locator('#sale-sold_on').fill(sale.soldOn);
}

// Sends the sale form and waits for the sale's page: Tessera follows the 303 itself, so the page lands there.
export async function recordSale(page: Page): Promise<void> {
    await page.locator('#sale-save').click();
    await expect(page).toHaveURL(/\/app\/sales\/\d+$/);
    await expect(page.locator('#sale-notice')).toHaveText('Recorded the sale.');
}

// Sends the sale form and gives the post's status, for a refusal, which Tessera answers in place.
export async function saveSale(page: Page): Promise<number> {
    const answer = page.waitForResponse((response) => response.request().method() === 'POST');
    await page.locator('#sale-save').click();

    return (await answer).status();
}

// Whole dollars as the pages write them.
export function money(dollars: number): string {
    return `${dollars < 0 ? '-' : ''}$${Math.abs(dollars).toLocaleString('en-US')}`;
}
