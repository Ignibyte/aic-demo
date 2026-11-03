import { expect, type Locator, type Page } from '@playwright/test';
import { checkDigitHolds, literal, sql } from './inventory';
import { type SentRequest } from './forms';

// The stem of the inline-edit looks' VINs, `0LTNLEDT{check digit}SR9997{nn}`: fictional, and no other look's.
const STEM = '0LTNLEDT';

// The `n`th of the looks' VINs, its check digit found by trying each one `checkDigitHolds` accepts.
function inlineVin(n: number): string {
    const serial = `SR9997${String(n).padStart(2, '0')}`;
    const digit = [...'0123456789X'].find((candidate) => checkDigitHolds(`${STEM}${candidate}${serial}`)) ?? '';

    return `${STEM}${digit}${serial}`;
}

// The looks' own VINs: one vehicle a look edits, and one beside it that must not change.
export const VINS = {
    edited: inlineVin(1),
    beside: inlineVin(2),
};

// The list searched for the looks' vehicles alone: the search matches part of a VIN.
export const VEHICLES = `/app/inventory?q=${STEM}`;

// The looks' own emails: `inlinelook.` and `@example.test`, so no seeded customer, no native step and no other look's
// cleanup touches them.
export const EMAILS = {
    edited: 'inlinelook.edited@example.test',
    beside: 'inlinelook.beside@example.test',
};

// The list searched for the looks' customers alone.
export const CUSTOMERS = '/app/customers?q=inlinelook.';

// Deletes the looks' vehicles and customers, so a look starts and ends with none of its own.
export function removeInlineLooks(): void {
    sql(`DELETE FROM vehicles WHERE vin LIKE '${STEM}%'`);
    sql("DELETE FROM customers WHERE email LIKE 'inlinelook.%@example.test'");
}

// A vehicle's row in the list, by its anchor.
export function vehicleRow(page: Page, vin: string): Locator {
    return page.locator(`[data-t-anchor="vehicle-${vin}"]`);
}

// A customer's row in the list, by its anchor.
export function customerRow(page: Page, id: number): Locator {
    return page.locator(`[data-t-anchor="customer-${id}"]`);
}

// The time Postgres last wrote a vehicle, to the microsecond.
export function vehicleWritten(vin: string): string {
    return sql(`SELECT updated_at::text FROM vehicles WHERE vin = ${literal(vin)}`)[0] ?? '';
}

// The time Postgres last wrote a customer, to the microsecond.
export function customerWritten(id: number): string {
    return sql(`SELECT updated_at::text FROM customers WHERE id = ${id}`)[0] ?? '';
}

// Marks each row of the table as the page has it now, on the node itself rather than an attribute a morph would bring
// in step, so a look can tell a row kept (morphed or untouched) from a row put in its place.
export async function markRows(page: Page): Promise<void> {
    await page.evaluate(() => {
        for (const row of document.querySelectorAll('tbody tr')) {
            Object.assign(row, { lookKept: true });
        }
    });
}

// Holds each of these rows to the node the page had when the look marked it.
export async function expectRowsKept(rows: Locator[]): Promise<void> {
    for (const row of rows) {
        expect(await row.evaluate((element) => 'lookKept' in element)).toBe(true);
    }
}

// Waits for the one request a row's control makes and holds it to a Tessera action of that method and path, naming
// the row alone (its path, its props and its anchor) and answered with one fragment, for that row, at that status.
export async function expectRowAction(
    requests: SentRequest[],
    method: string,
    path: string,
    anchor: string,
    status: number,
): Promise<SentRequest> {
    await expect.poll(() => requests.length).toBe(1);
    const [request] = requests;
    expect(request.method).toBe(method);
    expect(request.resourceType).toBe('fetch');
    expect(request.path).toBe(path);
    expect(request.tesseraRequest).toBe('action');
    const entries = request.targets.split(',');
    expect(entries).toHaveLength(1);
    const [target, props, named] = entries[0].trim().split(' ');
    expect(props).toMatch(/^[A-Za-z0-9_.~+/=-]+$/);
    expect(named).toBe(`@${anchor}`);
    expect(request.status).toBe(status);
    expect(request.contentType).toMatch(/^text\/vnd\.tessera\+html/);
    expect(request.templates).toEqual([target]);
    requests.length = 0;

    return request;
}

// A write id as Tessera makes one, `crypto.randomUUID()`.
const WRITE_ID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;

// Holds a row's save to a write: a fresh write id, AIC's CSRF header, and the fields as the row's form sent them.
export function expectRowWrite(request: SentRequest, fields: Record<string, string>): void {
    expect(request.writeId).toMatch(WRITE_ID);
    expect(request.headers['x-aic-csrf']).toMatch(/^[0-9a-f]{64}$/);
    expect(Object.fromEntries(new URLSearchParams(request.body))).toEqual(fields);
}

// Whether the field has focus with all its text selected, as the row's form starts.
export async function expectFocusedAndSelected(field: Locator): Promise<void> {
    await expect(field).toBeFocused();
    const selection = await field.evaluate((input) => {
        const box = input as HTMLInputElement;

        return [box.selectionStart, box.selectionEnd, box.value.length];
    });
    expect(selection).toEqual([0, selection[2], selection[2]]);
}

// Holds back the answers to the page's PUTs until the look lets them go: each request still reaches the server when
// released, after the recording route the look set up first.
export async function holdWrites(page: Page): Promise<() => void> {
    let release: () => void = () => undefined;
    const released = new Promise<void>((resolve) => {
        release = resolve;
    });

    await page.route((url) => url.pathname.endsWith('/row'), async (route) => {
        if (route.request().method() === 'PUT') {
            await released;
        }

        await route.fallback();
    });

    return release;
}

// Counts the PUTs the page sends, as they leave it.
export function countWrites(page: Page): { sent: number } {
    const count = { sent: 0 };
    page.on('request', (request) => {
        if (request.method() === 'PUT') {
            count.sent += 1;
        }
    });

    return count;
}

// The headers a write is sent again with: Tessera's, the CSRF header, the body's type and the cookie the page sent.
const REPEATED = [
    'tessera-request', 'tessera-protocol', 'tessera-layout', 'tessera-targets', 'tessera-write-id', 'x-aic-csrf', 'content-type', 'cookie',
];

// Sends a recorded row save again, its write id and its body unchanged, and gives the status and body of the answer.
export async function sendAgain(page: Page, write: SentRequest): Promise<{ status: number; body: string }> {
    const headers = Object.fromEntries(REPEATED.filter((name) => name in write.headers).map((name) => [name, write.headers[name]]));
    expect(headers['tessera-write-id']).toBe(write.writeId);
    const answer = await page.request.fetch(write.path, { method: 'PUT', headers, data: write.body, maxRedirects: 0 });

    return { status: answer.status(), body: await answer.text() };
}
