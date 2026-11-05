import { existsSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, type APIResponse, type Page } from '@playwright/test';
import { checkDigitHolds, literal, sql } from './inventory';
import { slugOf } from './storefront';
import { insertVehicle, testVehicle } from './vehicles';

// The stem of the brochure looks' VINs, `0LTPWBRC{check digit}SR9998{nn}`: fictional, and no other look's.
const STEM = '0LTPWBRC';

// The `n`th of the brochure looks' VINs, its check digit found by trying each one `checkDigitHolds` accepts.
function brochureVin(n: number): string {
    const serial = `SR9998${String(n).padStart(2, '0')}`;
    const digit = [...'0123456789X'].find((candidate) => checkDigitHolds(`${STEM}${candidate}${serial}`)) ?? '';

    return `${STEM}${digit}${serial}`;
}

// The brochure looks' own VINs, one per look.
export const VINS = {
    brochure: brochureVin(1),
    noScript: brochureVin(2),
};

// The staff list searched for the looks' vehicles alone: the search matches part of a VIN.
export const VEHICLES = `/app/inventory?q=${STEM}`;

// Puts a brochure look's car in the database, in stock, and gives its storefront page's path.
export function insertBrochureCar(vin: string): string {
    insertVehicle(testVehicle(vin));

    return carPath(vin);
}

// A car's storefront page, `/cars/{slug}`.
export function carPath(vin: string): string {
    return `/cars/${slugOf(vin)}`;
}

// Where this checkout keeps a car's brochure (#44), `brochures/{vehicle id}.html` in AIC's private files, as DDEV's
// site writes it; empty when no vehicle has the VIN.
function brochureFile(vin: string): string {
    const [vehicleId] = sql(`SELECT id FROM vehicles WHERE vin = ${literal(vin)}`);

    return vehicleId === undefined
        ? ''
        : resolve('var/storage/private/brochures', `${vehicleId}.html`);
}

// Whether the car with this VIN has its brochure kept.
export function brochureWritten(vin: string): boolean {
    const file = brochureFile(vin);

    return file !== '' && existsSync(file);
}

// Deletes the brochure looks' vehicles, and each one's brochure from this checkout's private files.
export function removeBrochureLooks(): void {
    for (const vin of sql(`SELECT vin FROM vehicles WHERE vin LIKE '${STEM}%'`)) {
        const file = brochureFile(vin);

        if (file !== '') {
            rmSync(file, { force: true });
        }
    }

    sql(`DELETE FROM vehicles WHERE vin LIKE '${STEM}%'`);
}

// Asks for a path with no session and no compression. DDEV's nginx gzips an HTML answer for a client that accepts it,
// which drops `Accept-Ranges` and weakens the app's ETag, and a page's own `fetch()` cannot refuse gzip; the brochure's
// routes ask for no session, so Playwright's request context, which can, serves on both of gate:23's sites.
export async function plainGet(page: Page, path: string, headers: Record<string, string> = {}): Promise<APIResponse> {
    return page.request.get(path, { headers: { 'Accept-Encoding': 'identity', ...headers }, maxRedirects: 0 });
}

// The keys of a car page's parts in the content region, in order.
export async function contentParts(page: Page): Promise<string[]> {
    return page.locator('[data-region="content"] > [data-t-c]').evaluateAll((parts) => parts.map((part) => part.getAttribute('data-t-c') ?? ''));
}

// Checks that a car page shows the brochure's two links, below the car's path.
export async function expectBrochureLinks(page: Page, path: string): Promise<void> {
    const view = page.locator('#car-brochure-view');
    const download = page.locator('#car-brochure-download');
    await expect(view).toHaveText('View the brochure');
    await expect(view).toHaveAttribute('href', `${path}/brochure`);
    await expect(view).not.toHaveAttribute('download');
    await expect(download).toHaveText('Download the brochure');
    await expect(download).toHaveAttribute('href', `${path}/brochure/download`);
    await expect(download).toHaveAttribute('download', '');
}
