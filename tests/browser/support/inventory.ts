import { execFileSync } from 'node:child_process';
import { expect, type Locator, type Page } from '@playwright/test';

// The gate's user, made by `bin/aic user:add` for each run: an admin whose name is its login.
const login = process.env.AIC_GATE_LOGIN ?? '';
const password = process.env.AIC_GATE_PASSWORD ?? '';

// The vehicles a page holds.
export const PAGE = 15;

// Fills the sign-in form the page shows and sends it; the form comes back to the inventory.
export async function fillSignIn(page: Page): Promise<void> {
    await page.locator('#sign-in-email').fill(login);
    await page.locator('#sign-in-password').fill(password);
    await page.locator('#sign-in-submit').click();
    await expect(page).toHaveURL(/\/app\/inventory$/);
}

// Signs the gate's user in and lands on the inventory.
export async function signIn(page: Page): Promise<void> {
    await page.goto('/sign-in?next=%2Fapp%2Finventory');
    await fillSignIn(page);
}

// The VIN cell of each row the table shows.
export function shownVins(page: Page): Locator {
    return page.locator('#vehicles-table tbody tr td:first-child');
}

// What the table's summary says for that many vehicles at that page.
export function summary(count: number, page = 1): string {
    const pages = Math.ceil(count / PAGE);
    const vehicles = `${count} ${count === 1 ? 'vehicle' : 'vehicles'}`;

    return pages > 1 ? `${vehicles}, page ${page} of ${pages}` : vehicles;
}

// Runs SQL against the app's database with psql. gate:23 and `aic-gate browser` hand the tests the PGHOST and PGPORT
// the sites use (DDEV's published Postgres); the user, password and database are DDEV's `db` unless the environment
// says otherwise.
export function sql(statement: string): string[] {
    const env = {
        ...process.env,
        PGDATABASE: process.env.PGDATABASE ?? 'db',
        PGPASSWORD: process.env.PGPASSWORD ?? 'db',
        PGUSER: process.env.PGUSER ?? 'db',
    };
    const output = execFileSync('psql', ['-X', '-A', '-t', '-q', '-v', 'ON_ERROR_STOP=1', '-c', statement], { encoding: 'utf8', env });

    return output.split('\n').filter((line) => line !== '');
}

// A string as an SQL literal.
export function literal(text: string): string {
    return `'${text.replaceAll("'", "''")}'`;
}

// The VINs of one page of the vehicles the condition keeps, in that order and then by VIN.
export function pageVins(where: string, order: string, offset = 0): string[] {
    return sql(`SELECT vin FROM vehicles ${where} ORDER BY ${order}, vin ASC LIMIT ${PAGE} OFFSET ${offset}`);
}

// How many vehicles the condition keeps.
export function countWhere(where: string): number {
    return Number(sql(`SELECT count(*) FROM vehicles ${where}`)[0]);
}

// ISO 3779's check digit, worked out here and not taken from the app: each character's value times its position's
// weight, modulo 11, with 10 written X.
const values: Record<string, number> = {
    A: 1, B: 2, C: 3, D: 4, E: 5, F: 6, G: 7, H: 8, J: 1, K: 2, L: 3, M: 4, N: 5,
    P: 7, R: 9, S: 2, T: 3, U: 4, V: 5, W: 6, X: 7, Y: 8, Z: 9,
};
const weights = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];

// Whether a VIN is well formed with a valid check digit.
export function checkDigitHolds(vin: string): boolean {
    if (!/^[0-9A-HJ-NPR-Z]{17}$/.test(vin)) {
        return false;
    }

    const sum = [...vin].reduce((total, character, position) => total + (values[character] ?? Number(character)) * weights[position], 0);
    const remainder = sum % 11;

    return vin[8] === (remainder === 10 ? 'X' : String(remainder));
}
