import { expect, type Page } from '@playwright/test';
import { checkDigitHolds, literal, sql } from './inventory';

// The stem of the form looks' VINs, `0LTPWFRM{check digit}SR9995{nn}`: fictional, and no other look's.
const STEM = '0LTPWFRM';

// The `n`th of the form looks' VINs, its check digit found by trying each one `checkDigitHolds` accepts.
function formVin(n: number): string {
    const serial = `SR9995${String(n).padStart(2, '0')}`;
    const digit = [...'0123456789X'].find((candidate) => checkDigitHolds(`${STEM}${candidate}${serial}`)) ?? '';

    return `${STEM}${digit}${serial}`;
}

// The form looks' own VINs, one per vehicle a look adds, edits or sells.
export const VINS = {
    added: formVin(1),
    edited: formVin(2),
    sold: formVin(3),
    twice: formVin(4),
    plain: formVin(5),
    plainSold: formVin(6),
};

// The form looks' own emails: `pw.form.` and `@example.test`, so no seeded customer, no native step and no other look
// shares them.
export const EMAILS = {
    added: 'pw.form.added@example.test',
    edited: 'pw.form.edited@example.test',
    buyer: 'pw.form.buyer@example.test',
    twice: 'pw.form.twice@example.test',
    plain: 'pw.form.plain@example.test',
};

// Deletes the form looks' sales, then their vehicles and their customers, so a look starts and ends with none of its
// own; the sales go first, since their foreign keys hold the rows they name.
export function removeFormLooks(): void {
    sql(`DELETE FROM sales WHERE vehicle_id IN (SELECT id FROM vehicles WHERE vin LIKE '${STEM}%')`
        + " OR customer_id IN (SELECT id FROM customers WHERE email LIKE 'pw.form.%@example.test')");
    sql(`DELETE FROM vehicles WHERE vin LIKE '${STEM}%'`);
    sql("DELETE FROM customers WHERE email LIKE 'pw.form.%@example.test'");
}

// How many vehicles the form looks own.
export function ownVehicles(): number {
    return Number(sql(`SELECT count(*) FROM vehicles WHERE vin LIKE '${STEM}%'`)[0]);
}

// How many customers the form looks own.
export function ownCustomers(): number {
    return Number(sql("SELECT count(*) FROM customers WHERE email LIKE 'pw.form.%@example.test'")[0]);
}

// How many sales the form looks own.
export function ownSales(): number {
    return Number(sql(`SELECT count(*) FROM sales s JOIN vehicles v ON v.id = s.vehicle_id WHERE v.vin LIKE '${STEM}%'`)[0]);
}

// The id of the customer with this email; 0 when there is none.
export function customerId(email: string): number {
    return Number(sql(`SELECT id FROM customers WHERE email = ${literal(email)}`)[0] ?? 0);
}

// One request the page made, with what its answer held.
export interface SentRequest {
    method: string;
    resourceType: string;
    path: string;
    tesseraRequest: string;
    writeId: string;
    targets: string;
    status: number;
    contentType: string;
    location: string;
    templates: string[];
    headers: Record<string, string>;
    body: string;
}

// A request's path and query, as the page writes its links.
export function pathOf(url: string): string {
    const parsed = new URL(url);

    return parsed.pathname + parsed.search;
}

// Records every request the page makes but its assets and the dashboard's refreshes, in order, with its answer. The
// answer is taken through a route, with no redirect followed, so a 303 shows as the 303 the server sent. A refresh,
// which the dashboard's poll sends every five seconds while it is open, goes on to the network unrecorded.
export async function recordRequests(page: Page): Promise<SentRequest[]> {
    const requests: SentRequest[] = [];

    await page.route((url) => !url.pathname.startsWith('/assets/'), async (route) => {
        const request = route.request();
        const headers = await request.allHeaders();

        if (headers['tessera-request'] === 'refresh') {
            await route.fallback();

            return;
        }

        const response = await route.fetch({ maxRedirects: 0 });
        const body = await response.text();
        const answer = response.headers();
        const contentType = answer['content-type'] ?? '';

        requests.push({
            method: request.method(),
            resourceType: request.resourceType(),
            path: pathOf(request.url()),
            tesseraRequest: headers['tessera-request'] ?? '',
            writeId: headers['tessera-write-id'] ?? '',
            targets: headers['tessera-targets'] ?? '',
            status: response.status(),
            contentType,
            location: answer['tessera-location'] ?? answer['location'] ?? '',
            templates: contentType.startsWith('text/vnd.tessera+html')
                ? [...body.matchAll(/<template data-target="([^"]*)">/g)].map((match) => match[1])
                : [],
            headers,
            body: request.postData() ?? '',
        });
        await route.fulfill({ response, body });
    });

    return requests;
}

// A write id as Tessera makes one, `crypto.randomUUID()`.
const WRITE_ID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;

// Holds a request to a Tessera submit of the form's region, posted to the form's action with a fresh write id.
export function expectSubmit(request: SentRequest, action: string): void {
    expect(request.method).toBe('POST');
    expect(request.resourceType).toBe('fetch');
    expect(request.path).toBe(action);
    expect(request.tesseraRequest).toBe('submit');
    expect(request.writeId).toMatch(WRITE_ID);
    // The target alone: protocol 2 ends an anchored target's entry with `@NAME` (AIC #194), and a region carries none
    // today.
    expect(request.targets.split(' ')[0]).toBe('content');
}

// Waits for the one request a refused submit makes, and holds it to a 422 answered as a fragment of `content`.
export async function expectRefusedInPlace(requests: SentRequest[], action: string): Promise<void> {
    await expect.poll(() => requests.length).toBe(1);
    const [request] = requests;
    expectSubmit(request, action);
    expect(request.status).toBe(422);
    expect(request.contentType).toMatch(/^text\/vnd\.tessera\+html/);
    expect(request.templates).toEqual(['content']);
    requests.length = 0;
}

// Waits for the two requests a saved submit makes, the submit answered with `Tessera-Location` and the visit of that
// page answered as a fragment, and gives the location.
export async function expectSavedVisit(requests: SentRequest[], action: string, location: RegExp): Promise<string> {
    await expect.poll(() => requests.length).toBe(2);
    const [submit, visit] = requests;
    expectSubmit(submit, action);
    expect(submit.status).toBe(200);
    expect(submit.location).toMatch(location);
    expect(visit.method).toBe('GET');
    expect(visit.resourceType).toBe('fetch');
    expect(visit.path).toBe(submit.location);
    expect(visit.tesseraRequest).toBe('visit');
    expect(visit.status).toBe(200);
    expect(visit.contentType).toMatch(/^text\/vnd\.tessera\+html/);
    expect(visit.templates).toContain('content');
    requests.length = 0;

    return submit.location;
}

// The line Chrome itself logs for every fetch answered 422, which the protocol does on purpose for a refused form.
const REFUSAL = 'Failed to load resource: the server responded with a status of 422';

// Collects every console error and page error but Chrome's own line for a 422, a refusal under the page's
// Content-Security-Policy among them.
export function collectFormErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('console', (message) => {
        if (message.type() === 'error' && !message.text().startsWith(REFUSAL)) {
            errors.push(message.text());
        }
    });
    page.on('pageerror', (error) => {
        errors.push(error.message);
    });

    return errors;
}
