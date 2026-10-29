import { rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, type Page } from '@playwright/test';
import { checkDigitHolds, literal, sql } from './inventory';

// The cars a page of the public list holds.
export const PAGE = 12;

// A car's public slug, worked out by Postgres as the app works it out in PHP: `{year}-{make}-{model}-{key}`, lower case,
// each run of other characters than letters and digits one hyphen, and the key the first 6 hex digits of the SHA-256
// of the VIN.
export const SLUG = "year || '-' || trim(both '-' from regexp_replace(lower(make), '[^a-z0-9]+', '-', 'g')) || '-'"
    + " || trim(both '-' from regexp_replace(lower(model), '[^a-z0-9]+', '-', 'g')) || '-'"
    + " || left(encode(sha256(convert_to(vin, 'UTF8')), 'hex'), 6)";

// The public list's orders, each ending with the VIN as the app's do.
export const NEWEST = 'added_on DESC, vin ASC';
export const PRICE = 'list_price ASC, vin ASC';
export const PRICE_DESCENDING = 'list_price DESC, vin ASC';

// The cars in stock that the further condition keeps (`AND …`, or empty), in that order: their slugs.
export function slugs(condition: string, order: string, limit = 1000, offset = 0): string[] {
    return sql(`SELECT ${SLUG} FROM vehicles WHERE status = 'in_stock' ${condition} ORDER BY ${order} LIMIT ${limit} OFFSET ${offset}`);
}

// How many cars in stock the further condition keeps.
export function countInStock(condition = ''): number {
    return Number(sql(`SELECT count(*) FROM vehicles WHERE status = 'in_stock' ${condition}`)[0]);
}

// The slug of a vehicle in the database, whatever its status.
export function slugOf(vin: string): string {
    return sql(`SELECT ${SLUG} FROM vehicles WHERE vin = ${literal(vin)}`)[0] ?? '';
}

// What the list's summary says for that many cars at that page.
export function summary(count: number, page = 1): string {
    const pages = Math.ceil(count / PAGE);
    const cars = `${count} ${count === 1 ? 'car' : 'cars'} for sale`;

    return pages > 1 ? `${cars}, page ${page} of ${pages}` : cars;
}

// The slugs of the cards the list shows, in order: each card's link has the id `car-{slug}`.
export async function shownSlugs(page: Page): Promise<string[]> {
    return page.locator('#cars > li > a').evaluateAll((links) => links.map((link) => link.id.replace(/^car-/, '')));
}

// Waits until the list shows those cards, in that order, and its summary says so.
export async function expectCars(page: Page, expected: string[], count: number, pageNumber = 1): Promise<void> {
    await expect(page.locator('#cars-summary')).toHaveText(summary(count, pageNumber));
    await expect.poll(() => shownSlugs(page)).toEqual(expected);
}

// Marks the page's document, so a look can tell a Tessera swap in it from a new document.
export async function markDocument(page: Page): Promise<void> {
    await page.evaluate(() => {
        document.documentElement.dataset.storefrontLook = 'kept';
    });
}

// Whether the document is still the one `markDocument()` marked.
export async function documentKept(page: Page): Promise<boolean> {
    return page.evaluate(() => document.documentElement.dataset.storefrontLook === 'kept');
}

// A price as the public pages write it, `$28,995`.
export function dollars(price: number): string {
    return `$${price.toLocaleString('en-US')}`;
}

// The make with the most cars in stock, so a filter by it keeps some.
export function busiestMake(): string {
    return sql("SELECT make FROM vehicles WHERE status = 'in_stock' GROUP BY make ORDER BY count(*) DESC, make LIMIT 1")[0];
}

// The stem of the storefront looks' VINs, `0LTPWSTF{check digit}SR9997{nn}`: fictional, and no other look's.
const STEM = '0LTPWSTF';

// The `n`th of the storefront looks' VINs, its check digit found by trying each one `checkDigitHolds` accepts.
export function storefrontVin(n: number): string {
    const serial = `SR9997${String(n).padStart(2, '0')}`;
    const digit = [...'0123456789X'].find((candidate) => checkDigitHolds(`${STEM}${candidate}${serial}`)) ?? '';

    return `${STEM}${digit}${serial}`;
}

// Deletes the storefront looks' vehicles, their photos' rows going with them, and their photos' files when the site
// keeps them in this checkout's `var/photos/`, as DDEV's does.
export function removeStorefrontVehicles(): void {
    for (const id of sql(`SELECT id FROM vehicles WHERE vin LIKE '${STEM}%'`)) {
        rmSync(resolve('var/photos', id), { recursive: true, force: true });
    }

    sql(`DELETE FROM vehicles WHERE vin LIKE '${STEM}%'`);
}
