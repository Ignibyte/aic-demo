import { expect, type Locator, type Page } from '@playwright/test';
import { checkDigitHolds, literal, sql } from './inventory';
import { type SentRequest } from './forms';
import { insertStaff } from './leads';

// The stem of the bulk-edit looks' VINs, `0LTBULKE{check digit}SR9996{nn}`: fictional, and no other look's.
const STEM = '0LTBULKE';

// The `n`th of the looks' VINs, its check digit found by trying each one `checkDigitHolds` accepts.
function bulkVin(n: number): string {
    const serial = `SR9996${String(n).padStart(2, '0')}`;
    const digit = [...'0123456789X'].find((candidate) => checkDigitHolds(`${STEM}${candidate}${serial}`)) ?? '';

    return `${STEM}${digit}${serial}`;
}

// The looks' own VINs: three a look ticks, one beside them it leaves alone, and a sold one with a recorded sale.
export const VINS = {
    first: bulkVin(1),
    second: bulkVin(2),
    third: bulkVin(3),
    beside: bulkVin(4),
    sold: bulkVin(5),
};

// The list searched for the looks' vehicles alone: the search matches part of a VIN.
export const LIST = `/app/inventory?q=${STEM}`;

// The bulk edit's route.
export const BULK = '/app/inventory/bulk';

// The looks' own addresses: their sales manager and their buyer, so no seeded member, no native step and no other
// look's cleanup touches them.
export const DOMAIN = '@bulk-look.test';

// Deletes what the looks made: the queued audits that name their vehicles, their sales, vehicles, buyer, manager and
// the manager's notifications, so a look starts and ends with none of its own.
export function removeBulkLooks(): void {
    sql(`DELETE FROM aic_jobs WHERE job = 'notify' AND payload LIKE '%${STEM}%'`);
    sql(`DELETE FROM aic_notifications WHERE body LIKE '%${STEM}%'`);
    sql(`DELETE FROM sales WHERE vehicle_id IN (SELECT id FROM vehicles WHERE vin LIKE '${STEM}%')`);
    sql(`DELETE FROM vehicles WHERE vin LIKE '${STEM}%'`);
    sql(`DELETE FROM customers WHERE email LIKE '%${DOMAIN}'`);
    sql(`DELETE FROM users WHERE email LIKE '%${DOMAIN}'`);
}

// Records a sale of the vehicle to the looks' own buyer, by the look's own salesperson, and marks the vehicle sold, as
// the sales module would.
export function insertRecordedSale(vin: string): void {
    const buyer = `buyer${DOMAIN}`;
    const seller = `seller${DOMAIN}`;
    sql(
        'INSERT INTO customers (name, email, phone, address, notes, created_at, updated_at) '
            + `VALUES ('Bulk Buyer', ${literal(buyer)}, '', '', '', now(), now()) ON CONFLICT DO NOTHING`,
    );

    if (sql(`SELECT count(*) FROM users WHERE email = ${literal(seller)}`)[0] === '0') {
        insertStaff(seller, 'Bulk Seller', 'salesperson');
    }

    sql(
        'INSERT INTO sales (vehicle_id, customer_id, salesperson_id, price, sold_on, created_at)'
            + ` SELECT v.id, c.id, u.id, v.list_price, current_date, now() FROM vehicles v, customers c, users u`
            + ` WHERE v.vin = ${literal(vin)} AND c.email = ${literal(buyer)} AND u.email = ${literal(seller)}`,
    );
    sql(`UPDATE vehicles SET status = 'sold' WHERE vin = ${literal(vin)}`);
}

// Makes a sales manager of the looks' own and gives their user id, which their audits carry.
export function insertManager(): string {
    const email = `manager.${Date.now()}${DOMAIN}`;
    insertStaff(email, 'Bulk Manager', 'sales_manager');

    return sql(`SELECT id FROM users WHERE email = ${literal(email)}`)[0];
}

// A vehicle's row in the list, by its anchor.
export function row(page: Page, vin: string): Locator {
    return page.locator(`[data-t-anchor="vehicle-${vin}"]`);
}

// A vehicle's box, in its row's VIN cell.
export function box(page: Page, vin: string): Locator {
    return row(page, vin).locator('input[type="checkbox"][name="vins[]"]');
}

// What the database holds of a vehicle: its status, its location (empty for none) and when it was last written.
export function stored(vin: string): { status: string; location: string; written: string } {
    const [line] = sql(`SELECT status || '|' || coalesce(location, '') || '|' || updated_at::text FROM vehicles WHERE vin = ${literal(vin)}`);
    const [status, location, written] = (line ?? '||').split('|');

    return { status, location, written };
}

// How many vehicles of the looks' own the database holds with these VINs.
export function present(vins: string[]): number {
    return Number(sql(`SELECT count(*) FROM vehicles WHERE vin IN (${vins.map(literal).join(', ')})`)[0]);
}

// The audits queued for a member: each `notify` job's channel and subject, oldest first.
export function auditsFor(userId: string): { channel: string; subject: string; body: string }[] {
    return sql(
        "SELECT payload::jsonb->>'channel', payload::jsonb->>'subject', replace(payload::jsonb->>'body', E'\\n', ' / ') FROM aic_jobs "
            + `WHERE job = 'notify' AND payload::jsonb->>'user' = ${literal(userId)} AND payload LIKE '%${STEM}%' ORDER BY id`,
    ).map((line) => {
        const [channel, subject, body] = line.split('|');

        return { channel, subject, body };
    });
}

// How many audits naming the looks' vehicles are queued, for anyone.
export function queuedAudits(): number {
    return Number(sql(`SELECT count(*) FROM aic_jobs WHERE job = 'notify' AND payload LIKE '%${STEM}%'`)[0]);
}

// Holds the one request the bar made to a Tessera action, POST to the bulk route, naming each of these rows by its
// anchor (with its props) and the totals line, and nothing else; answered at that status with a fragment for each.
export async function expectBulkAction(requests: SentRequest[], vins: string[], status: number): Promise<SentRequest> {
    await expect.poll(() => requests.length).toBe(1);
    const [request] = requests;
    expect(request.method).toBe('POST');
    expect(request.resourceType).toBe('fetch');
    expect(request.path).toBe(BULK);
    expect(request.tesseraRequest).toBe('action');
    const entries = request.targets.split(',').map((entry) => entry.trim().split(' '));
    const anchors = entries.map((entry) => entry[entry.length - 1]).sort();
    expect(anchors).toEqual([...vins.map((vin) => `@vehicle-${vin}`), '@inventory-totals'].sort());

    for (const entry of entries) {
        if (entry[entry.length - 1] !== '@inventory-totals') {
            expect(entry[1]).toMatch(/^[A-Za-z0-9_.~+/=-]+$/);
        }
    }

    expect(request.status).toBe(status);
    expect(request.contentType).toMatch(/^text\/vnd\.tessera\+html/);
    expect([...request.templates].sort()).toEqual(entries.map((entry) => entry[0]).sort());
    requests.length = 0;

    return request;
}

// The form fields a bulk action sent, each list as its values.
export function sentFields(request: SentRequest): Record<string, string[]> {
    const fields: Record<string, string[]> = {};

    for (const [name, value] of new URLSearchParams(request.body)) {
        fields[name] = [...(fields[name] ?? []), value];
    }

    return fields;
}
