import { rmSync } from 'node:fs';
import { expect, type Page, type Request, type Response } from '@playwright/test';
import { literal, sql } from './inventory';
import { photoFile } from './photos';

// The panel looks' own make, so no seeded vehicle and no other look's lands among their similar vehicles.
export const MAKE = 'Panelcar';

// The looks' staff and leads carry this domain, so the clean-up finds them.
export const DOMAIN = '@panels-look.test';

// ISO 3779's check digit, worked out here as `checkDigitHolds` does, and written into position 9.
const values: Record<string, number> = {
    A: 1, B: 2, C: 3, D: 4, E: 5, F: 6, G: 7, H: 8, J: 1, K: 2, L: 3, M: 4, N: 5,
    P: 7, R: 9, S: 2, T: 3, U: 4, V: 5, W: 6, X: 7, Y: 8, Z: 9,
};
const weights = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];

// A VIN of the looks' own from its 16 other characters (`0LTPN` and a serial), its check digit put in.
export function vin(stem: string): string {
    const draft = stem.slice(0, 8) + '0' + stem.slice(8);
    const sum = [...draft].reduce((total, character, position) => total + (values[character] ?? Number(character)) * weights[position], 0);
    const remainder = sum % 11;

    return stem.slice(0, 8) + (remainder === 10 ? 'X' : String(remainder)) + stem.slice(8);
}

// The panels a vehicle's page places, in order: key, anchor and heading.
export const PANELS = [
    { key: 'price-history', anchor: 'vehicle-price-history', heading: 'Price history' },
    { key: 'similar', anchor: 'vehicle-similar', heading: 'Similar vehicles' },
    { key: 'activity', anchor: 'vehicle-activity', heading: 'Leads and holds' },
] as const;

// A vehicle of the looks' own.
export interface PanelVehicle {
    vin: string;
    listPrice: number;
    bodyStyle?: string;
    status?: 'in_stock' | 'on_hold' | 'sold';
    model?: string;
    addedOn?: string;
}

// Puts a vehicle of the looks' own on the lot and gives its id.
export function insertPanelVehicle(vehicle: PanelVehicle): number {
    const [id] = sql(
        'INSERT INTO vehicles (vin, year, make, model, trim, body_style, mileage, exterior_colour, list_price, cost, status, '
            + `added_on, created_at, updated_at) VALUES (${literal(vehicle.vin)}, 2024, ${literal(MAKE)}, `
            + `${literal(vehicle.model ?? 'Coupe')}, 'GT', ${literal(vehicle.bodyStyle ?? 'Coupe')}, 12000, 'Teal', `
            + `${vehicle.listPrice}, 1000, ${literal(vehicle.status ?? 'in_stock')}, ${literal(vehicle.addedOn ?? '2026-09-01')}, now(), now()) RETURNING id`,
    );

    return Number(id);
}

// Records a change of a vehicle's list price, as a save would.
export function insertPriceChange(vehicleVin: string, from: number, to: number, changedOn: string): void {
    sql(
        'INSERT INTO vehicle_prices (vehicle_id, from_price, to_price, changed_on, created_at) '
            + `SELECT id, ${from}, ${to}, ${literal(changedOn)}, now() FROM vehicles WHERE vin = ${literal(vehicleVin)}`,
    );
}

// A vehicle's recorded price changes, oldest first, as `from>to@day`.
export function priceChanges(vehicleVin: string): string[] {
    return sql(
        "SELECT p.from_price || '>' || p.to_price || '@' || to_char(p.changed_on, 'YYYY-MM-DD') FROM vehicle_prices p "
            + `JOIN vehicles v ON v.id = p.vehicle_id WHERE v.vin = ${literal(vehicleVin)} ORDER BY p.id`,
    );
}

// Puts a lead on a vehicle, at that stage, `minutesAgo` before now, and gives its id.
export function insertLead(vehicleVin: string, name: string, stage: string, minutesAgo: number, sellerId = ''): string {
    const status = stage === 'won' || stage === 'lost' ? 'closed' : 'open';
    const seller = sellerId === '' ? 'NULL' : sellerId;
    const email = `${name.toLowerCase().replaceAll(' ', '.')}${DOMAIN}`;
    const [id] = sql(
        'INSERT INTO leads (vehicle_id, kind, name, email, phone, message, salesperson_id, status, stage, created_at) '
            + `SELECT id, 'inquiry', ${literal(name)}, ${literal(email)}, '555-0100', 'Is it still there?', ${seller}, `
            + `${literal(status)}, ${literal(stage)}, now() - interval '${minutesAgo} minutes' FROM vehicles WHERE vin = ${literal(vehicleVin)} RETURNING id`,
    );

    return id;
}

// Puts a hold on a vehicle with that status, placed `hoursAgo` before now by that member (none when empty).
export function insertHold(vehicleVin: string, status: string, hoursAgo: number, placedBy = ''): void {
    const placer = placedBy === '' ? 'NULL' : placedBy;
    sql(
        'INSERT INTO holds (vehicle_id, customer_id, placed_by, note, status, created_at, updated_at) '
            + `SELECT id, NULL, ${placer}, 'Panels look', ${literal(status)}, now() - interval '${hoursAgo} hours', now() `
            + `FROM vehicles WHERE vin = ${literal(vehicleVin)}`,
    );
}

// The SQL for a photo's key in AIC's public files, as `support/photos.ts` writes it.
const KEY = "'photos/' || p.sha256 || '.' || CASE p.media_type WHEN 'image/png' THEN 'png' WHEN 'image/webp' THEN 'webp' ELSE 'jpg' END";

// Deletes the looks' vehicles, whose price changes, leads, holds and photo rows go with them, and their staff; a photo
// file an upload left that no other row holds is removed from this checkout's public files.
export function removePanelLooks(): void {
    const keys = sql(`SELECT DISTINCT ${KEY} FROM vehicle_photos p JOIN vehicles v ON v.id = p.vehicle_id WHERE v.make = ${literal(MAKE)}`);
    sql(`DELETE FROM vehicles WHERE make = ${literal(MAKE)}`);
    const held = new Set(sql(`SELECT DISTINCT ${KEY} FROM vehicle_photos p`));

    for (const key of keys.filter((stored) => !held.has(stored) && !stored.startsWith('photos/0000000000'))) {
        rmSync(photoFile(key), { force: true });
    }

    sql(`DELETE FROM users WHERE email LIKE '%${DOMAIN}'`);
}

// A panel's section on the page, by its key and the vehicle's VIN: arrived, or its skeleton.
export function arrived(page: Page, key: string, vehicleVin: string): ReturnType<Page['locator']> {
    return page.locator(`section#${key}-${vehicleVin}`);
}

export function skeleton(page: Page, key: string, vehicleVin: string): ReturnType<Page['locator']> {
    return page.locator(`section#${key}-${vehicleVin}-loading`);
}

// One Tessera exchange the page made: the request's kind, method, path and targets, and the answer's status, media
// type and the targets of its templates.
export interface Exchange {
    kind: string;
    method: string;
    path: string;
    targets: string;
    status: number;
    contentType: string;
    cacheControl: string;
    templates: string[];
}

// Records every Tessera exchange the page makes from now on (any request that carries `Tessera-Request`).
export function recordExchanges(page: Page): Exchange[] {
    const exchanges: Exchange[] = [];
    page.on('response', async (response: Response) => {
        const request: Request = response.request();
        const headers = await request.allHeaders();
        const kind = headers['tessera-request'] ?? '';

        if (kind === '') {
            return;
        }

        const contentType = response.headers()['content-type'] ?? '';
        let body = '';

        if (contentType.startsWith('text/vnd.tessera+html')) {
            // A body Chromium has let go of (a prefetch no visit took, say) counts as none.
            body = await response.text().catch(() => '');
        }

        const url = new URL(request.url());
        exchanges.push({
            kind,
            method: request.method(),
            path: url.pathname + url.search,
            targets: headers['tessera-targets'] ?? '',
            status: response.status(),
            contentType,
            cacheControl: response.headers()['cache-control'] ?? '',
            templates: [...body.matchAll(/<template data-target="([^"]+)"/g)].map((match) => match[1]),
        });
    });

    return exchanges;
}

// The exchanges for one path, in order.
export function exchangesTo(exchanges: Exchange[], path: string): Exchange[] {
    return exchanges.filter((exchange) => exchange.path === path);
}

// Marks an element so a later look can tell it is still the same live node.
export async function markNode(page: Page, selector: string, mark: string): Promise<void> {
    await page.locator(selector).evaluate((element, value) => {
        Object.assign(element, { panelsMark: value });
    }, mark);
}

// The mark the element carries now; empty when it is a new node.
export async function nodeMark(page: Page, selector: string): Promise<string> {
    return page.locator(selector).evaluate((element) => (element as Element & { panelsMark?: string }).panelsMark ?? '');
}

// Waits until every panel of the vehicle has arrived.
export async function expectPanelsArrived(page: Page, vehicleVin: string): Promise<void> {
    for (const panel of PANELS) {
        await expect(arrived(page, panel.key, vehicleVin)).toBeVisible();
        await expect(skeleton(page, panel.key, vehicleVin)).toHaveCount(0);
    }
}
