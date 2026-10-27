import { expect, type Page } from '@playwright/test';
import { literal, sql } from './inventory';

// The gate's user, made by `bin/aic user:add` for each run.
const login = process.env.AIC_GATE_LOGIN ?? '';
const password = process.env.AIC_GATE_PASSWORD ?? '';

// The customers a page of the list holds.
export const PAGE = 15;

// The customer looks' own emails: `pw.` and `@example.test`, so no seeded customer and no native step shares them.
export const EMAILS = {
    added: 'pw.added@example.test',
    holder: 'pw.holder@example.test',
    other: 'pw.other@example.test',
    edited: 'pw.edited@example.test',
    deleted: 'pw.deleted@example.test',
};

// A customer as the looks put it in the database and read it back.
export interface TestCustomer {
    name: string;
    email: string;
    phone: string;
    address: string;
    notes: string;
}

// A test customer with the given name and email; the rest is fixed.
export function testCustomer(name: string, email: string): TestCustomer {
    return { name, email, phone: '(503) 555-0177', address: '9 Probe Lane, Ridgeway', notes: 'A browser look made this.' };
}

// Deletes every customer the looks own, so a look starts and ends with none of its own.
export function removeTestCustomers(): void {
    sql("DELETE FROM customers WHERE email LIKE 'pw.%@example.test'");
}

// Puts a customer in the database, as the seed would, and gives its id.
export function insertCustomer(customer: TestCustomer): number {
    const values = [customer.name, customer.email, customer.phone, customer.address, customer.notes].map(literal).join(', ');

    return Number(sql(`INSERT INTO customers (name, email, phone, address, notes, created_at, updated_at) VALUES (${values}, now(), now()) RETURNING id`)[0]);
}

// The customer the database holds with this email, with its id; null when it holds none.
export function storedCustomer(email: string): (TestCustomer & { id: number }) | null {
    const rows = sql(`SELECT id, name, email, phone, address, notes FROM customers WHERE email = ${literal(email)}`);

    if (rows.length === 0) {
        return null;
    }

    const [id, name, storedEmail, phone, address, notes] = rows[0].split('|');

    return { id: Number(id), name, email: storedEmail, phone, address, notes };
}

// How many customers have this email in any letters.
export function emailCount(email: string): number {
    return Number(sql(`SELECT count(*) FROM customers WHERE lower(email) = lower(${literal(email)})`)[0]);
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

// Fills the customer form with a customer's details.
export async function fillCustomerForm(page: Page, customer: TestCustomer): Promise<void> {
    await page.locator('#customer-name').fill(customer.name);
    await page.locator('#customer-email').fill(customer.email);
    await page.locator('#customer-phone').fill(customer.phone);
    await page.locator('#customer-address').fill(customer.address);
    await page.locator('#customer-notes').fill(customer.notes);
}

// The names of one page of the customers the condition keeps, as the list orders them.
export function pageNames(where: string, offset = 0): string[] {
    return sql(`SELECT name FROM customers ${where} ORDER BY name ASC, id ASC LIMIT ${PAGE} OFFSET ${offset}`);
}

// How many customers the condition keeps.
export function countWhere(where: string): number {
    return Number(sql(`SELECT count(*) FROM customers ${where}`)[0]);
}

// The name cell of each row the list's table shows.
export function shownNames(page: Page) {
    return page.locator('#customers-table tbody tr td:first-child');
}
