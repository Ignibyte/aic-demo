import { existsSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, type Page } from '@playwright/test';
import { literal, sql } from './inventory';
import { insertStaff, mailFor, removeMail } from './leads';
import { signInAs } from './photos';

// Every member of staff and customer the reports looks make ends here, and every vehicle's VIN starts `0LTPWR`
// (fictional, as the other looks' are), so their sales, leads, exports, notifications and mail are theirs alone and go
// afterwards. Their records sit in 2001, a range no other data has, so the figures each report shows are theirs alone.
export const DOMAIN = '@reports-look.test';

// The range the looks report on, and a day on each side of it that holds records it must leave out.
export const RANGE = { from: '2001-03-01', to: '2001-04-30' };

// The reports, by the value the export form posts, with their panels' titles.
export const REPORTS = {
    sales: 'Sales by month',
    salespeople: 'Revenue and units by salesperson',
    inventory: 'Inventory by make',
    leads: 'Leads by kind',
} as const;

export type Report = keyof typeof REPORTS;

// A member of staff of the looks' own.
export interface Member {
    id: string;
    email: string;
    name: string;
    password: string;
}

// Makes a member of staff with that role and a fresh password.
export function insertMember(name: string, role: string): Member {
    const email = `${name.toLowerCase().replaceAll(' ', '.')}.${Date.now()}${DOMAIN}`;
    const password = insertStaff(email, name, role);
    const [id] = sql(`SELECT id FROM users WHERE email = ${literal(email)}`);

    return { id, email, name, password };
}

// Signs the member in, in that page.
export async function signInMember(page: Page, member: Member): Promise<void> {
    await signInAs(page, member.email, member.password);
}

// A customer of the looks' own, by id.
function insertCustomer(name: string): string {
    const email = `${name.toLowerCase()}.${Date.now()}${DOMAIN}`;
    const [id] = sql(
        'INSERT INTO customers (name, email, phone, address, notes, created_at, updated_at) '
            + `VALUES (${literal(name)}, ${literal(email)}, '(503) 555-0100', '1 Look Lane', '', now(), now()) RETURNING id`,
    );

    return id;
}

// A vehicle of the looks' own, by id: its make, the day it came onto the lot and its status.
function insertVehicle(serial: number, make: string, addedOn: string, status: string): string {
    const vin = `0LTPWR${String(serial).padStart(3, '0')}SR99930${serial % 10}`;
    const [id] = sql(
        'INSERT INTO vehicles (vin, year, make, model, trim, body_style, mileage, exterior_colour, list_price, cost, status, '
            + `added_on, created_at, updated_at) VALUES (${literal(vin)}, 2000, ${literal(make)}, 'Look', 'SE', 'Sedan', 9000, 'Slate', `
            + `21000, 17000, ${literal(status)}, ${literal(addedOn)}, now(), now()) RETURNING id`,
    );

    return id;
}

// A sale of one of the looks' vehicles.
function insertSale(vehicle: string, customer: string, seller: Member, price: number, soldOn: string): void {
    sql(
        'INSERT INTO sales (vehicle_id, customer_id, salesperson_id, price, sold_on, created_at) '
            + `VALUES (${vehicle}, ${customer}, ${seller.id}, ${price}, ${literal(soldOn)}, now())`,
    );
}

// A lead about one of the looks' vehicles, sent by the customer whose id it names, on that day.
function insertLead(vehicle: string, kind: string, customer: string, cameOn: string): void {
    sql(
        'INSERT INTO leads (vehicle_id, kind, name, email, phone, message, status, created_at) '
            + `SELECT ${vehicle}, ${literal(kind)}, name, upper(email), '', 'A look''s lead.', 'open', `
            + `${literal(`${cameOn} 15:00:00+00`)} FROM customers WHERE id = ${customer}`,
    );
}

// The looks' records: two salespeople and their sales in and around the range, vehicles of three makes on the lot in
// it and two outside it, and leads, some of whose cars were sold to the customer who sent them. Every figure the looks
// expect comes from the base tables, never from the views the app reads.
export function insertReportData(): { ann: Member; bob: Member } {
    const ann = insertMember('Ann Look', 'salesperson');
    const bob = insertMember('Bob Look', 'salesperson');
    const cara = insertCustomer('Cara');
    const dev = insertCustomer('Dev');
    const alpha1 = insertVehicle(1, 'Alphalook', '2001-02-01', 'sold');
    const alpha2 = insertVehicle(2, 'Alphalook', '2001-03-05', 'sold');
    const beta1 = insertVehicle(3, 'Betalook', '2001-04-01', 'sold');
    const beta2 = insertVehicle(4, 'Betalook', '2001-02-15', 'sold');
    const gamma1 = insertVehicle(5, 'Gammalook', '2001-05-10', 'in_stock');
    insertVehicle(6, 'Gammalook', '2001-04-25', 'in_stock');
    insertSale(alpha1, cara, ann, 20000, '2001-03-10');
    insertSale(alpha2, dev, bob, 25000, '2001-04-20');
    insertSale(beta1, dev, ann, 18000, '2001-04-02');
    insertSale(beta2, cara, bob, 15000, '2001-02-20');
    insertLead(alpha1, 'inquiry', cara, '2001-03-02');
    insertLead(alpha2, 'test_drive', cara, '2001-04-01');
    insertLead(alpha2, 'test_drive', dev, '2001-04-05');
    insertLead(gamma1, 'inquiry', dev, '2001-05-05');

    return { ann, bob };
}

// One line of a report as the looks expect it: its label and its two numbers.
export interface Line {
    label: string;
    count: number;
    amount: number;
}

// The range as SQL literals.
function bounds(): [string, string] {
    return [literal(RANGE.from), literal(RANGE.to)];
}

// What each report's lines are for the range, worked out from the base tables.
export function expectedLines(report: Report): Line[] {
    const [from, to] = bounds();
    const queries: Record<Report, string> = {
        sales: "SELECT to_char(m, 'FMMonth YYYY') || '|' || count(s.id) || '|' || coalesce(sum(s.price), 0) "
            + `FROM generate_series(${from}::date, ${to}::date, interval '1 month') AS m LEFT JOIN sales s `
            + `ON date_trunc('month', s.sold_on) = m AND s.sold_on BETWEEN ${from} AND ${to} GROUP BY m ORDER BY m`,
        salespeople: "SELECT u.name || '|' || count(*) || '|' || sum(s.price) FROM sales s JOIN users u ON u.id = s.salesperson_id "
            + `WHERE s.sold_on BETWEEN ${from} AND ${to} GROUP BY u.email, u.name ORDER BY sum(s.price) DESC, u.email`,
        inventory: "SELECT v.make || '|' || count(*) || '|' || round(avg(coalesce(s.sold_on, current_date) - v.added_on)) "
            + `FROM vehicles v LEFT JOIN sales s ON s.vehicle_id = v.id WHERE v.added_on <= ${to} `
            + `AND coalesce(s.sold_on, current_date) >= ${from} GROUP BY v.make ORDER BY count(*) DESC, v.make`,
        leads: "SELECT k.label || '|' || count(l.id) || '|' || count(l.id) FILTER (WHERE EXISTS (SELECT 1 FROM sales s "
            + 'JOIN customers c ON c.id = s.customer_id WHERE s.vehicle_id = l.vehicle_id AND lower(c.email) = lower(l.email))) '
            + "FROM (VALUES (1, 'inquiry', 'Questions'), (2, 'test_drive', 'Test drives')) AS k(position, kind, label) "
            + `LEFT JOIN leads l ON l.kind = k.kind AND l.created_at::date BETWEEN ${from} AND ${to} GROUP BY k.position, k.label `
            + 'ORDER BY k.position',
    };

    return sql(queries[report]).map((row) => {
        const [label, count, amount] = row.split('|');

        return { label, count: Number(count), amount: Number(amount) };
    });
}

// A line as the page's table writes it: money with a dollar sign for the two sales reports.
export function shownLine(report: Report, line: Line): string[] {
    const money = report === 'sales' || report === 'salespeople';
    const amount = money ? `$${line.amount.toLocaleString('en-US')}` : line.amount.toLocaleString('en-US');

    return [line.label, line.count.toLocaleString('en-US'), amount];
}

// The cells of each line a report's table shows.
export async function tableLines(page: Page, report: Report): Promise<string[][]> {
    return await page.locator(`#report-${report}-table tbody tr`).evaluateAll((rows) => rows.map(
        (row) => Array.from(row.querySelectorAll('td')).map((cell) => (cell.textContent ?? '').trim()),
    ));
}

// The reports page over the looks' range.
export function rangePath(): string {
    return `/app/reports?from=${RANGE.from}&to=${RANGE.to}`;
}

// One export as it is stored.
export interface StoredExport {
    id: number;
    report: string;
    from: string;
    to: string;
    status: string;
}

// The member's exports, oldest first.
export function storedExports(member: Member): StoredExport[] {
    return sql(
        "SELECT id || '|' || report || '|' || from_day || '|' || to_day || '|' || status FROM report_exports "
            + `WHERE user_id = ${member.id} ORDER BY id`,
    ).map((row) => {
        const [id, report, from, to, status] = row.split('|');

        return { id: Number(id), report, from, to, status };
    });
}

// Where the export's file is, in this checkout, as the worker in DDEV writes it.
export function exportFile(id: number): string {
    return resolve('var/exports', `${id}.csv`);
}

// Whether the export's file is there.
export function exportFileExists(id: number): boolean {
    return existsSync(exportFile(id));
}

// The member's kept notifications, newest first: subject and link.
export function notificationsOf(member: Member): { subject: string; link: string }[] {
    return sql(`SELECT subject || '|' || coalesce(link, '') FROM aic_notifications WHERE user_id = ${literal(member.id)} ORDER BY id DESC`)
        .map((row) => {
            const [subject, link] = row.split('|');

            return { subject, link };
        });
}

// Asks for a report's export through its button, and lands on the page that names it.
export async function exportReport(page: Page, report: Report): Promise<void> {
    await page.locator(`#report-export-${report}-button`).click();
    await expect(page).toHaveURL(new RegExp(`/app/reports\\?from=${RANGE.from}&to=${RANGE.to}&export=\\d+$`));
}

// The lines of a CSV, CRLF ended, each split on its commas (the looks' fields hold none).
export function csvLines(text: string): string[][] {
    expect(text.endsWith('\r\n')).toBe(true);

    return text.slice(0, -2).split('\r\n').map((line) => line.split(','));
}

// Deletes every record, export file, notification and mail the looks made.
export async function removeReportLooks(): Promise<void> {
    for (const id of sql(`SELECT e.id FROM report_exports e JOIN users u ON u.id = e.user_id WHERE u.email LIKE '%${DOMAIN}'`)) {
        rmSync(exportFile(Number(id)), { force: true });
    }

    sql(`DELETE FROM aic_notifications WHERE user_id IN (SELECT id::text FROM users WHERE email LIKE '%${DOMAIN}')`);
    sql("DELETE FROM leads WHERE vehicle_id IN (SELECT id FROM vehicles WHERE vin LIKE '0LTPWR%')");
    sql("DELETE FROM sales WHERE vehicle_id IN (SELECT id FROM vehicles WHERE vin LIKE '0LTPWR%')");
    sql("DELETE FROM vehicles WHERE vin LIKE '0LTPWR%'");
    sql(`DELETE FROM customers WHERE email LIKE '%${DOMAIN}'`);
    sql(`DELETE FROM users WHERE email LIKE '%${DOMAIN}'`);
    await removeMail(await mailFor(DOMAIN));
}
