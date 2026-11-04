import { expect, type Locator, type Page } from '@playwright/test';
import { checkDigitHolds, literal, sql } from './inventory';
import { slugOf } from './storefront';
import { insertVehicle, testVehicle, type TestVehicle } from './vehicles';

// The comparison looks' cars (#33): fictional VINs, `0LTPWCMP{check digit}SR9933{nn}`, so no seeded vehicle, no native
// step and no other look shares them, all of the make `Comparo`, so `/cars?make=Comparo` lists them alone.
const STEM = '0LTPWCMP';
export const MAKE = 'Comparo';

// The drawer's anchor, which the bar's Compare link targets.
export const DRAWER = 'compare-drawer';

// The `n`th of the looks' VINs, its check digit found by trying each one `checkDigitHolds` accepts.
export function compareVin(n: number): string {
    const serial = `SR9933${String(n).padStart(2, '0')}`;
    const digit = [...'0123456789X'].find((candidate) => checkDigitHolds(`${STEM}${candidate}${serial}`)) ?? '';

    return `${STEM}${digit}${serial}`;
}

// The `n`th look car: model `C{n}`, priced $10,000 more $1,000 a car, the first the newest on the lot, so the list's
// first order puts them 1, 2, 3 and on.
export function compareCar(n: number, status: TestVehicle['status'] = 'in_stock'): TestVehicle {
    return {
        ...testVehicle(compareVin(n), status),
        make: MAKE,
        model: `C${n}`,
        trim: `Trim ${n}`,
        mileage: 1000 * n,
        listPrice: 10_000 + 1_000 * n,
        addedOn: `2026-08-${String(29 - n).padStart(2, '0')}`,
    };
}

// Puts look cars 1 to `count` in the database, and gives back their slugs in the list's order.
export function insertCompareCars(count: number): string[] {
    const slugs: string[] = [];

    for (let n = 1; n <= count; n += 1) {
        insertVehicle(compareCar(n));
        slugs.push(slugOf(compareVin(n)));
    }

    return slugs;
}

// Deletes the looks' cars.
export function removeCompareCars(): void {
    sql(`DELETE FROM vehicles WHERE vin LIKE ${literal(`${STEM}%`)}`);
}

// The bar's and the drawer's parts, by their ids.
export function dock(page: Page): Record<'bar' | 'count' | 'full' | 'go' | 'clear' | 'submit' | 'drawer' | 'close' | 'table', Locator> {
    return {
        bar: page.locator('#compare-form'),
        count: page.locator('#compare-count'),
        full: page.locator('#compare-full'),
        go: page.locator('#compare-go'),
        clear: page.locator('#compare-clear'),
        submit: page.locator('#compare-submit'),
        drawer: page.locator('#compare-drawer'),
        close: page.locator('#compare-close'),
        table: page.locator('#compare-table'),
    };
}

// A car's Compare tick, on a tile or on its page.
export function tick(page: Page, slug: string): Locator {
    return page.locator(`#compare-${slug}`);
}

// The link the bar's Compare writes for these slugs, in order.
export function compareHref(slugs: string[]): string {
    return `/compare?car=${slugs.join('&car=')}`;
}

// The column heads of the table on the page or in the drawer: each car's title, in order.
export async function comparedTitles(table: Locator): Promise<string[]> {
    return table.locator('thead th a').allTextContents();
}

// One request the page made to `/compare`, with what its answer held.
export interface CompareRequest {
    url: string;
    tesseraRequest: string;
    targets: string;
    status: number;
    contentType: string;
    templates: string[];
}

// Records each request to `/compare` and, once it is answered, its status, its media type and its templates' targets.
// The answer is fetched through the route and handed to the page unchanged: Chromium may drop a fetched answer's body
// before `response.text()` reads it ("No data found for resource"), and the route's own fetch keeps it.
export async function recordCompareRequests(page: Page): Promise<CompareRequest[]> {
    const requests: CompareRequest[] = [];
    await page.route((url) => url.pathname === '/compare', async (route) => {
        const request = route.request();
        const response = await route.fetch();
        const body = await response.text();
        const headers = await request.allHeaders();
        requests.push({
            url: request.url(),
            tesseraRequest: headers['tessera-request'] ?? '',
            targets: headers['tessera-targets'] ?? '',
            status: response.status(),
            contentType: response.headers()['content-type'] ?? '',
            templates: [...body.matchAll(/<template data-target="([^"]+)"/g)].map((match) => match[1]),
        });
        await route.fulfill({ response, body });
    });

    return requests;
}

// Waits until the bar shows that selection: its count and its link.
export async function expectChosen(page: Page, slugs: string[]): Promise<void> {
    const parts = dock(page);
    await expect(parts.bar).toBeVisible();
    await expect(parts.count).toHaveText(`${slugs.length} of 3 chosen`);
    await expect(parts.go).toHaveAttribute('href', compareHref(slugs));
}
