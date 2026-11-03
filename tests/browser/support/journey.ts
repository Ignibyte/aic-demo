import { randomBytes } from 'node:crypto';
import { rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, type Page } from '@playwright/test';
import { aic } from './console';
import { checkDigitHolds, literal, sql } from './inventory';
import { mailFor, removeMail } from './leads';
import { photoFile } from './photos';
import { slugOf } from './storefront';
import { type TestVehicle } from './vehicles';

// The journeys' own people end here: their staff, their visitor (who becomes a customer) and their mail, so all of it
// goes afterwards. Their car's VIN starts `0LTPWJRN` (fictional, as the other looks' are) and its make is one no seed
// and no other look uses, so a filter by it finds that car alone.
export const DOMAIN = '@journey-look.test';

// The VIN stem, and the journey's car: `0LTPWJRN{check digit}SR9997{nn}`.
const STEM = '0LTPWJRN';

// The `n`th of the journeys' VINs, its check digit found by trying each one `checkDigitHolds` accepts.
function journeyVin(n: number): string {
    const serial = `SR9997${String(n).padStart(2, '0')}`;
    const digit = [...'0123456789X'].find((candidate) => checkDigitHolds(`${STEM}${candidate}${serial}`)) ?? '';

    return `${STEM}${digit}${serial}`;
}

// The car a sales manager adds, one per journey, on the lot from the database's today.
export function journeyCar(n: number): TestVehicle {
    return {
        vin: journeyVin(n),
        year: 2024,
        make: 'Wayfarer',
        model: 'Journey',
        trim: n === 1 ? 'LX' : 'EX',
        bodyStyle: 'Hatchback',
        mileage: 1200,
        colour: 'Teal',
        listPrice: 24500,
        cost: 21000,
        status: 'in_stock',
        addedOn: today(),
    };
}

// What staff and the public list call the car.
export function carTitle(car: TestVehicle): string {
    return `${car.year} ${car.make} ${car.model} ${car.trim}`;
}

// The car's public page, `/cars/{slug}`, as the app slugs it.
export function carPath(car: TestVehicle): string {
    return `/cars/${slugOf(car.vin)}`;
}

// The database's today, `YYYY-MM-DD`.
export function today(): string {
    return sql('SELECT current_date')[0];
}

// The first day of the database's month.
export function monthStart(): string {
    return sql("SELECT date_trunc('month', current_date)::date")[0];
}

// A member of staff of the journeys' own, with a password made for them.
export interface Member {
    id: string;
    email: string;
    name: string;
    password: string;
}

// Makes a member of staff with that role and a fresh password, hashed as the app hashes one.
export function insertMember(name: string, role: string): Member {
    const email = `${name.toLowerCase().replaceAll(' ', '.')}${DOMAIN}`;
    const password = randomBytes(12).toString('hex');
    const [id] = sql(
        'INSERT INTO users (email, name, role, password_hash, created_at) '
            + `VALUES (${literal(email)}, ${literal(name)}, ${literal(role)}, crypt(${literal(password)}, gen_salt('bf', 4)), now()) `
            + 'RETURNING id',
    );

    return { id, email, name, password };
}

// Signs the member in through the form and lands on the dashboard.
export async function signInMember(page: Page, member: Member): Promise<void> {
    await page.goto('/sign-in?next=%2Fapp');
    await page.locator('#sign-in-email').fill(member.email);
    await page.locator('#sign-in-password').fill(member.password);
    await page.locator('#sign-in-submit').click();
    await expect(page).toHaveURL(/\/app$/);
}

// The visitor who asks about the car, and whom the salesperson then adds as a customer.
export const VISITOR = {
    name: 'Vera Journey',
    email: `vera${DOMAIN}`,
    phone: '(503) 555-0177',
    message: 'Is the Wayfarer still for sale? I could come on Saturday.',
};

// Sets aside every active salesperson but the journey's own, so the rotation gives the visitor's lead to theirs, and
// gives the ids to put back. The looks run one at a time, so nothing else sends a lead meanwhile.
export function setAsideSalespeople(): string[] {
    return sql(
        "UPDATE users SET deactivated_on = current_date WHERE role = 'salesperson' AND deactivated_on IS NULL "
            + `AND email NOT LIKE '%${DOMAIN}' RETURNING id`,
    );
}

// Puts back the salespeople set aside.
export function restoreSalespeople(ids: string[]): void {
    if (ids.length > 0) {
        sql(`UPDATE users SET deactivated_on = NULL WHERE id IN (${ids.join(', ')})`);
    }
}

// Runs `bin/aic queue:work` once on this host (AIC #225), against the database the site under test uses, mailing into
// DDEV's Mailpit (`./console`), and gives its line.
export function work(): string {
    return aic(['queue:work', '1000']).trim();
}

// The journey's lead, as stored: its id and its salesperson's email.
export function storedLead(): { id: number; seller: string } {
    const [row] = sql(
        "SELECT l.id || '|' || coalesce(u.email, '') FROM leads l LEFT JOIN users u ON u.id = l.salesperson_id "
            + `WHERE l.email = ${literal(VISITOR.email)} ORDER BY l.id DESC LIMIT 1`,
    );
    const [id, seller] = (row ?? '|').split('|');

    return { id: Number(id), seller };
}

// The vehicle's status as stored.
export function carStatus(car: TestVehicle): string {
    return sql(`SELECT status FROM vehicles WHERE vin = ${literal(car.vin)}`)[0] ?? '';
}

// The lines of a CSV, CRLF ended, each split on its commas (the journeys' fields hold none).
export function csvLines(text: string): string[][] {
    expect(text.endsWith('\r\n')).toBe(true);

    return text.slice(0, -2).split('\r\n').map((line) => line.split(','));
}

// Whole dollars as the pages write them.
export function money(dollars: number): string {
    return `$${dollars.toLocaleString('en-US')}`;
}

// Deletes everything the journeys made: the sales, holds and leads of their cars, the cars (and every photo file no
// other photo holds), the visitor as a customer, their staff's exports (and the files) and notifications, the staff,
// and their mail in Mailpit.
export async function removeJourneyLooks(): Promise<void> {
    const cars = `(SELECT id FROM vehicles WHERE vin LIKE '${STEM}%')`;
    const staff = `(SELECT id FROM users WHERE email LIKE '%${DOMAIN}')`;
    const key = "'photos/' || p.sha256 || '.' || CASE p.media_type WHEN 'image/png' THEN 'png' WHEN 'image/webp' THEN 'webp' ELSE 'jpg' END";
    const photoKeys = sql(`SELECT DISTINCT ${key} FROM vehicle_photos p WHERE p.vehicle_id IN ${cars}`);
    const exports = sql(`SELECT id FROM report_exports WHERE user_id IN ${staff}`);
    sql(`DELETE FROM sales WHERE vehicle_id IN ${cars}`);
    sql(`DELETE FROM holds WHERE vehicle_id IN ${cars}`);
    sql(`DELETE FROM leads WHERE vehicle_id IN ${cars} OR email LIKE '%${DOMAIN}'`);
    sql(`DELETE FROM vehicles WHERE vin LIKE '${STEM}%'`);
    sql(`DELETE FROM customers WHERE email LIKE '%${DOMAIN}'`);
    sql(`DELETE FROM report_exports WHERE user_id IN ${staff}`);
    sql(`DELETE FROM aic_notifications WHERE user_id IN (SELECT id::text FROM users WHERE email LIKE '%${DOMAIN}')`);
    sql(`DELETE FROM users WHERE email LIKE '%${DOMAIN}'`);
    const held = new Set(sql(`SELECT DISTINCT ${key} FROM vehicle_photos p`));

    for (const photoKey of photoKeys.filter((stored) => !held.has(stored))) {
        rmSync(photoFile(photoKey), { force: true });
    }

    for (const id of exports) {
        rmSync(resolve('var/storage/private/exports', `${id}.csv`), { force: true });
    }

    await removeMail(await mailFor(DOMAIN));
}
