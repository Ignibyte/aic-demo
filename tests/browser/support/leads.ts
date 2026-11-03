import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { expect, type Page } from '@playwright/test';
import { aic } from './console';
import { literal, sql } from './inventory';
import { SLUG } from './storefront';

// Every address the leads looks send from or sign in with ends here, so their leads, their people and their mail are
// theirs alone and go afterwards.
export const DOMAIN = '@leads-look.test';

// An address of the looks' own, made unique for the run.
export function lookEmail(name: string): string {
    return `${name}.${Date.now()}${DOMAIN}`;
}

// A car in stock: its row, its public page, its title as staff pages and mails write it (with its trim) and its VIN.
export interface Car {
    id: number;
    path: string;
    title: string;
    vin: string;
}

// The newest car in stock, the first the public list shows.
export function firstCar(): Car {
    const [row] = sql(
        `SELECT id || '|' || ${SLUG} || '|' || trim(year || ' ' || make || ' ' || model || ' ' || trim) || '|' || vin FROM vehicles `
            + "WHERE status = 'in_stock' ORDER BY added_on DESC, vin ASC LIMIT 1",
    );
    const [id, slug, title, vin] = row.split('|');

    return { id: Number(id), path: `/cars/${slug}`, title, vin };
}

// The salesperson the next lead goes to, worked out here as the app works it out: the first salesperson by id after
// the newest lead's, wrapping round; empty when there is none.
export function nextSeller(): string {
    return sql(
        "WITH s AS (SELECT id, email FROM users WHERE role = 'salesperson'), "
            + 'n AS (SELECT coalesce((SELECT salesperson_id FROM leads ORDER BY id DESC LIMIT 1), 0) AS last) '
            + 'SELECT email FROM s, n ORDER BY (s.id <= n.last), s.id LIMIT 1',
    )[0] ?? '';
}

// One stored lead, as the looks check it.
export interface StoredLead {
    id: number;
    kind: string;
    vehicleId: number;
    message: string;
    driveOn: string;
    driveAt: string;
    seller: string;
    status: string;
    customerId: number;
}

// The leads sent from an address, oldest first.
export function storedLeads(email: string): StoredLead[] {
    return sql(
        "SELECT l.id || '|' || l.kind || '|' || l.vehicle_id || '|' || l.message || '|' || coalesce(l.drive_on::text, '') || '|' "
            + "|| coalesce(l.drive_at, '') || '|' || coalesce(u.email, '') || '|' || l.status || '|' || coalesce(l.customer_id, 0) "
            + `FROM leads l LEFT JOIN users u ON u.id = l.salesperson_id WHERE l.email = ${literal(email)} ORDER BY l.id`,
    ).map((row) => {
        const [id, kind, vehicleId, message, driveOn, driveAt, seller, status, customerId] = row.split('|');

        return { id: Number(id), kind, vehicleId: Number(vehicleId), message, driveOn, driveAt, seller, status, customerId: Number(customerId) };
    });
}

// The database's today, which a test drive must come after.
export function today(): string {
    return sql('SELECT current_date::text')[0];
}

// The first day after the database's today that the dealership is open.
export function openDay(): string {
    return sql("SELECT d::date::text FROM generate_series(current_date + 1, current_date + 7, '1 day') d WHERE extract(dow FROM d) <> 0 LIMIT 1")[0];
}

// The first Sunday after the database's today.
export function sunday(): string {
    return sql("SELECT d::date::text FROM generate_series(current_date + 1, current_date + 7, '1 day') d WHERE extract(dow FROM d) = 0 LIMIT 1")[0];
}

// Makes a member of staff of the looks' own with a fresh password, hashed as the app hashes one, and gives the
// password.
export function insertStaff(email: string, name: string, role: string): string {
    const password = randomBytes(12).toString('hex');
    sql(
        'INSERT INTO users (email, name, role, password_hash, created_at) '
            + `VALUES (${literal(email)}, ${literal(name)}, ${literal(role)}, crypt(${literal(password)}, gen_salt('bf', 4)), now())`,
    );

    return password;
}

// Deletes the looks' leads and staff, and their mail in Mailpit.
export async function removeLeadLooks(): Promise<void> {
    sql(`DELETE FROM leads WHERE email LIKE '%${DOMAIN}'`);
    sql(`DELETE FROM users WHERE email LIKE '%${DOMAIN}'`);
    await removeMail(await mailFor(DOMAIN));
}

// What a visitor types into a lead form.
export interface Visitor {
    name: string;
    email: string;
    phone: string;
    message: string;
}

// Fills "Ask about this car".
export async function fillInquiry(page: Page, visitor: Visitor): Promise<void> {
    await page.locator('#inquiry-name').fill(visitor.name);
    await page.locator('#inquiry-email').fill(visitor.email);
    await page.locator('#inquiry-phone').fill(visitor.phone);
    await page.locator('#inquiry-message').fill(visitor.message);
}

// Fills "Book a test drive".
export async function fillTestDrive(page: Page, visitor: Visitor, day: string, time: string): Promise<void> {
    await page.locator('#test-drive-name').fill(visitor.name);
    await page.locator('#test-drive-email').fill(visitor.email);
    await page.locator('#test-drive-phone').fill(visitor.phone);
    await page.locator('#test-drive-day').fill(day);
    await page.locator('#test-drive-time').fill(time);
    await page.locator('#test-drive-message').fill(visitor.message);
}

// The thank-you a sent question shows in its panel.
export function inquiryThanks(visitor: Visitor): string {
    return `Thanks, ${visitor.name}. We have your question and will reply to ${visitor.email} soon.`;
}

// Runs `bin/aic queue:work` once on this host (AIC #225), against the database the site under test uses, mailing into
// DDEV's Mailpit (`./console`).
export function runWorker(): string {
    return aic(['queue:work']);
}

// Mailpit's HTTP address, as DDEV gives it.
function mailpit(): string {
    const described = JSON.parse(execFileSync('ddev', ['describe', '-j'], { encoding: 'utf8' })) as { raw: { mailpit_url: string } };

    return described.raw.mailpit_url;
}

// One message Mailpit holds.
export interface Mail {
    id: string;
    to: string[];
    subject: string;
}

// The messages Mailpit finds for some text: an address it was sent to, or words in it.
export async function mailFor(text: string): Promise<Mail[]> {
    const answer = await fetch(`${mailpit()}/api/v1/search?query=${encodeURIComponent(`"${text}"`)}&limit=200`);
    const found = await answer.json() as { messages: { ID: string; To: { Address: string }[]; Subject: string }[] };

    return found.messages.map((message) => ({ id: message.ID, to: message.To.map((to) => to.Address), subject: message.Subject }));
}

// The text of one message.
export async function mailText(mail: Mail): Promise<string> {
    const answer = await fetch(`${mailpit()}/api/v1/message/${mail.id}`);

    return (await answer.json() as { Text: string }).Text;
}

// Deletes those messages from Mailpit.
export async function removeMail(mails: Mail[]): Promise<void> {
    if (mails.length === 0) {
        return;
    }

    const answer = await fetch(`${mailpit()}/api/v1/messages`, {
        method: 'DELETE',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ IDs: mails.map((mail) => mail.id) }),
    });
    expect(answer.ok).toBe(true);
}
