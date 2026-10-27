import { expect, type Page } from '@playwright/test';
import { checkDigitHolds, literal, sql } from './inventory';
import { insertCustomer, testCustomer } from './customers';
import { insertVehicle, testVehicle } from './vehicles';
import { money } from './dashboard';

// The gate's user, made by `bin/aic user:add` for each run: the live looks' seller.
export const login = process.env.AIC_GATE_LOGIN ?? '';

// The stem of the live looks' VINs, `0LTPWLIV{check digit}SR9996{nn}`: fictional, and no other look's.
const STEM = '0LTPWLIV';

// The `n`th of the live looks' VINs, its check digit found by trying each one `checkDigitHolds` accepts.
function liveVin(n: number): string {
    const serial = `SR9996${String(n).padStart(2, '0')}`;
    const digit = [...'0123456789X'].find((candidate) => checkDigitHolds(`${STEM}${candidate}${serial}`)) ?? '';

    return `${STEM}${digit}${serial}`;
}

// The live looks' vehicles, each in stock until a look sells it.
export const VINS = [liveVin(1), liveVin(2)];

// The live looks' buyer: `pw.` and `@example.test`, so no seeded customer and no native step shares the email.
export const BUYER = 'pw.live@example.test';

// The live looks' buyer's name, as the feed shows it.
export const BUYER_NAME = 'Live Buyer';

// Deletes the live looks' sales, then their vehicles and their buyer; the sales go first, since their foreign keys hold
// the rows they name.
export function removeLiveLooks(): void {
    sql(`DELETE FROM sales WHERE vehicle_id IN (SELECT id FROM vehicles WHERE vin LIKE '${STEM}%')`
        + ` OR customer_id IN (SELECT id FROM customers WHERE email = ${literal(BUYER)})`);
    sql(`DELETE FROM vehicles WHERE vin LIKE '${STEM}%'`);
    sql(`DELETE FROM customers WHERE email = ${literal(BUYER)}`);
}

// Puts the live looks' vehicles in stock and their buyer in the database.
export function insertLiveLooks(): void {
    for (const vin of VINS) {
        insertVehicle(testVehicle(vin));
    }

    insertCustomer(testCustomer(BUYER_NAME, BUYER));
}

// Records a sale of a live look's vehicle today, as the app would, its vehicle marked sold.
export function insertLiveSale(vin: string, price: number): void {
    sql(
        'INSERT INTO sales (vehicle_id, customer_id, salesperson_id, price, sold_on, created_at)'
            + ` SELECT v.id, c.id, u.id, ${price}, current_date, now() FROM vehicles v, customers c, users u`
            + ` WHERE v.vin = ${literal(vin)} AND c.email = ${literal(BUYER)} AND u.email = ${literal(login)}`,
    );
    sql(`UPDATE vehicles SET status = 'sold' WHERE vin = ${literal(vin)}`);
}

// The sales' fingerprint, worked out here: their count, their prices summed and the highest id, joined by hyphens.
export function fingerprint(): string {
    return sql("SELECT count(*) || '-' || coalesce(sum(price), 0) || '-' || coalesce(max(id), 0) FROM sales")[0];
}

// The five sales recorded last, the latest first, each row's cells as the feed writes them.
export function latestSales(): string[][] {
    return sql(
        "SELECT to_char(s.sold_on, 'YYYY-MM-DD'), trim(v.year || ' ' || v.make || ' ' || v.model || ' ' || v.trim), c.name, u.name, s.price"
            + ' FROM sales s JOIN vehicles v ON v.id = s.vehicle_id JOIN customers c ON c.id = s.customer_id'
            + ' JOIN users u ON u.id = s.salesperson_id ORDER BY s.id DESC LIMIT 5',
    ).map((row) => {
        const [soldOn, vehicle, customer, salesperson, price] = row.split('|');

        return [soldOn, vehicle, customer, salesperson, money(Number(price))];
    });
}

// The feed's rows as the page shows them, each row's cells.
export async function shownFeed(page: Page): Promise<string[][]> {
    return page.locator('#sales-feed tbody tr').evaluateAll((rows) => rows.map(
        (row) => [...row.querySelectorAll('td')].map((cell) => (cell.textContent ?? '').trim()),
    ));
}

// The feed shows the five sales Postgres gives, in its order.
export async function expectFeed(page: Page): Promise<void> {
    await expect.poll(() => shownFeed(page), { timeout: 10_000 }).toEqual(latestSales());
}

// One connection the page opened to the dashboard's stream.
export interface StreamConnection {
    path: string;
    tesseraRequest: string;
    lastEventId: string;
}

// Records every connection the page opens to the dashboard's stream, and every document it loads.
export function recordStreams(page: Page): { connections: StreamConnection[]; documents: string[] } {
    const connections: StreamConnection[] = [];
    const documents: string[] = [];
    page.on('request', async (request) => {
        const url = new URL(request.url());

        if (request.resourceType() === 'document') {
            documents.push(url.pathname);
        }

        if (url.pathname === '/app/events') {
            const headers = await request.allHeaders();
            connections.push({
                path: url.pathname + url.search,
                tesseraRequest: headers['tessera-request'] ?? '',
                lastEventId: headers['last-event-id'] ?? '',
            });
        }
    });

    return { connections, documents };
}
