import { randomBytes } from 'node:crypto';
import { rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, type Page } from '@playwright/test';
import { checkDigitHolds, literal, sql } from './inventory';
import { insertVehicle, testVehicle } from './vehicles';

// The stem of the photo looks' VINs, `0LTPWPHT{check digit}SR9996{nn}`: fictional, and no other look's.
const STEM = '0LTPWPHT';

// The `n`th of the photo looks' VINs, its check digit found by trying each one `checkDigitHolds` accepts.
function photoVin(n: number): string {
    const serial = `SR9996${String(n).padStart(2, '0')}`;
    const digit = [...'0123456789X'].find((candidate) => checkDigitHolds(`${STEM}${candidate}${serial}`)) ?? '';

    return `${STEM}${digit}${serial}`;
}

// The photo looks' own VINs, one per look.
export const VINS = {
    added: photoVin(1),
    refused: photoVin(2),
    salesperson: photoVin(3),
    bytes: photoVin(4),
};

// What staff call every photo look's vehicle: `testVehicle()`'s year, make, model and trim.
export const TITLE = '2023 Probe Hatch SE';

// The salesperson the salesperson look signs in as, made for it and removed after it.
export const SALESPERSON = 'pw.salesperson@example.test';

// The fixtures, as gate:23's native steps upload them too.
export const FIXTURES = {
    jpeg: resolve('tests/browser/fixtures/photos/car.jpg'),
    png: resolve('tests/browser/fixtures/photos/car.png'),
    webp: resolve('tests/browser/fixtures/photos/car.webp'),
    text: resolve('tests/browser/fixtures/photos/not-a-photo.jpg'),
    large: resolve('tests/browser/fixtures/photos/too-large.jpg'),
};

// A vehicle's page.
export function vehiclePath(vin: string): string {
    return `/app/inventory/${vin}`;
}

// Puts a photo look's vehicle in the database, in stock.
export function insertPhotoVehicle(vin: string): void {
    insertVehicle(testVehicle(vin));
}

// The SQL for a photo's key in AIC's public files, `photos/{sha256}.{extension}`, as the app names it.
const KEY = "'photos/' || p.sha256 || '.' || CASE p.media_type WHEN 'image/png' THEN 'png' WHEN 'image/webp' THEN 'webp' ELSE 'jpg' END";

// Where this checkout keeps a photo's file, as DDEV's site writes it into AIC's public files.
export function photoFile(key: string): string {
    return resolve('var/storage/public', key);
}

// Deletes the photo looks' vehicles, their photos' rows going with them, and the salesperson. A photo file no other
// row holds is removed too, from this checkout's public files, where DDEV's site keeps it.
export function removePhotoLooks(): void {
    const keys = sql(`SELECT DISTINCT ${KEY} FROM vehicle_photos p JOIN vehicles v ON v.id = p.vehicle_id WHERE v.vin LIKE '${STEM}%'`);
    sql(`DELETE FROM vehicles WHERE vin LIKE '${STEM}%'`);
    sql(`DELETE FROM users WHERE email = ${literal(SALESPERSON)}`);
    const held = new Set(sql(`SELECT DISTINCT ${KEY} FROM vehicle_photos p`));

    for (const key of keys.filter((stored) => !held.has(stored))) {
        rmSync(photoFile(key), { force: true });
    }
}

// The vehicle's photo files, first added first: where this checkout keeps each, and how many photo rows of any vehicle
// hold its bytes.
export function photoFiles(vin: string): { path: string; held: number }[] {
    return sql(
        `SELECT ${KEY} || '|' || (SELECT count(*) FROM vehicle_photos o WHERE o.sha256 = p.sha256) `
            + `FROM vehicle_photos p JOIN vehicles v ON v.id = p.vehicle_id WHERE v.vin = ${literal(vin)} ORDER BY p.id`,
    ).map((row) => {
        const [key, held] = row.split('|');

        return { path: photoFile(key), held: Number(held) };
    });
}

// How many photos the vehicle with this VIN has.
export function photoCount(vin: string): number {
    return Number(sql(`SELECT count(*) FROM vehicle_photos p JOIN vehicles v ON v.id = p.vehicle_id WHERE v.vin = ${literal(vin)}`)[0]);
}

// Gives the vehicle that many photo rows with no files, as if it had them already.
export function insertPhotoRows(vin: string, count: number): void {
    sql(
        'INSERT INTO vehicle_photos (vehicle_id, media_type, bytes, sha256, created_at) '
            + `SELECT v.id, 'image/jpeg', 1, repeat('0', 64), now() FROM vehicles v, generate_series(1, ${count}) WHERE v.vin = ${literal(vin)}`,
    );
}

// Makes the salesperson with a fresh password, hashed as the app hashes one, and gives the password.
export function insertSalesperson(): string {
    const password = randomBytes(12).toString('hex');
    sql(
        'INSERT INTO users (email, name, role, password_hash, created_at) '
            + `VALUES (${literal(SALESPERSON)}, 'Photo Salesperson', 'salesperson', crypt(${literal(password)}, gen_salt('bf', 4)), now())`,
    );

    return password;
}

// Signs in through the form and lands on /app.
export async function signInAs(page: Page, login: string, password: string): Promise<void> {
    await page.goto('/sign-in?next=%2Fapp');
    await page.locator('#sign-in-email').fill(login);
    await page.locator('#sign-in-password').fill(password);
    await page.locator('#sign-in-submit').click();
    await expect(page).toHaveURL(/\/app$/);
}

// Chooses a file in the vehicle page's upload form and sends it.
export async function addPhoto(page: Page, file: string): Promise<void> {
    await page.locator('#vehicle-photo-file').setInputFiles(file);
    await page.locator('#vehicle-photo-add').click();
}

// What an in-page request answered: its status, its headers by lower-case name, and its bytes.
export interface Fetched {
    status: number;
    headers: Record<string, string>;
    body: Buffer;
}

// Sends a request from the page itself, with the page's own cookies, never from the HTTP cache and following no
// redirect. Playwright's `page.request` does not send the `Secure` session cookie to the compiled site gate:23 serves
// over plain HTTP, so every such request there lands on the sign-in page; the page's own `fetch()` does send it.
export async function fetchInPage(
    page: Page,
    path: string,
    init: { method?: string; headers?: Record<string, string>; fields?: Record<string, string>; file?: { name: string; type: string; bytes: number[] } } = {},
): Promise<Fetched> {
    const answer = await page.evaluate(async ({ path, init }) => {
        let body: FormData | undefined;

        if (init.fields !== undefined) {
            body = new FormData();
            for (const [name, value] of Object.entries(init.fields)) {
                body.append(name, value);
            }
            if (init.file !== undefined) {
                body.append('photo', new Blob([new Uint8Array(init.file.bytes)], { type: init.file.type }), init.file.name);
            }
        }

        const response = await fetch(path, { method: init.method ?? 'GET', headers: init.headers ?? {}, body, cache: 'no-store', redirect: 'manual' });
        const headers: Record<string, string> = {};
        response.headers.forEach((value, name) => {
            headers[name] = value;
        });

        return { status: response.status, headers, body: Array.from(new Uint8Array(await response.arrayBuffer())) };
    }, { path, init });

    return { status: answer.status, headers: answer.headers, body: Buffer.from(answer.body) };
}

// The page's CSRF token, as its forms and Tessera send it.
export async function csrfToken(page: Page): Promise<string> {
    return await page.locator('meta[name="tessera-csrf-token"]').getAttribute('content') ?? '';
}

// A response the page got, as the photo looks check it.
export interface Answer {
    method: string;
    resourceType: string;
    path: string;
    tesseraRequest: string;
    status: number;
    contentType: string;
    location: string;
}

// Records the page's answers but its assets, its images, the bell's stream and Tessera's refreshes: the vehicle page's
// panels load and poll by refresh (#31), as `recordRequests()` lets them go by. It only listens: `recordRequests()`
// sends each request again through `route.fetch()`, which sends a multipart upload's file empty, so the photo looks
// cannot use it.
export function recordAnswers(page: Page): Answer[] {
    const answers: Answer[] = [];
    page.on('response', (response) => {
        const request = response.request();
        const url = new URL(request.url());

        const kind = request.headers()['tessera-request'];
        const stream = kind === 'stream' || kind === 'refresh';

        if (url.pathname.startsWith('/assets/') || request.resourceType() === 'image' || stream) {
            return;
        }

        const headers = response.headers();
        answers.push({
            method: request.method(),
            resourceType: request.resourceType(),
            path: url.pathname + url.search,
            tesseraRequest: request.headers()['tessera-request'] ?? '',
            status: response.status(),
            contentType: headers['content-type'] ?? '',
            location: headers['tessera-location'] ?? headers['location'] ?? '',
        });
    });

    return answers;
}

// Takes the answers recorded since the last call, once there are that many.
export async function takeAnswers(answers: Answer[], count: number): Promise<Answer[]> {
    await expect.poll(() => answers.length).toBe(count);

    return answers.splice(0, answers.length);
}

// One Tessera submit of a photo form to `action`, then its visit to the vehicle's page: no new document.
export async function expectChangedInPlace(answers: Answer[], action: string, path: string): Promise<void> {
    const [submit, visit] = await takeAnswers(answers, 2);
    expect([submit.method, submit.resourceType, submit.path, submit.tesseraRequest]).toEqual(['POST', 'fetch', action, 'submit']);
    expect([submit.status, submit.location]).toEqual([200, path]);
    expect([visit.method, visit.resourceType, visit.path, visit.tesseraRequest, visit.status]).toEqual(['GET', 'fetch', path, 'visit', 200]);
    expect(visit.contentType).toMatch(/^text\/vnd\.tessera\+html/);
}

// One Tessera submit of the upload form, answered 422 as a fragment: no new document.
export async function expectRefusedInPlace(answers: Answer[], path: string): Promise<void> {
    const [submit] = await takeAnswers(answers, 1);
    expect([submit.method, submit.resourceType, submit.path, submit.tesseraRequest]).toEqual(['POST', 'fetch', `${path}/photos`, 'submit']);
    expect(submit.status).toBe(422);
    expect(submit.contentType).toMatch(/^text\/vnd\.tessera\+html/);
}

// The width the browser decoded an image at: 0 until it has loaded.
export async function naturalWidth(page: Page, selector: string): Promise<number> {
    return page.locator(selector).evaluate((image) => (image as HTMLImageElement).naturalWidth);
}
