import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { expect, test, type APIRequestContext } from '@playwright/test';
import { literal, sql } from './support/inventory';
import { NEWEST, removeStorefrontVehicles, SLUG, storefrontVin } from './support/storefront';
import { insertVehicle, testVehicle } from './support/vehicles';

// The XML feed for listing sites (#37). This look opens no page: it reads the feed with Playwright's request alone and
// validates it against the repo's schema with libxml2's xmllint, which gate:23's host has. No xmllint fails the look.

const FEED = '/feeds/vehicles.xml';
const SCHEMA = resolve('tests/browser/fixtures/vehicle-feed.xsd');
const LISTED = storefrontVin(1);
const HELD = storefrontVin(2);
const SOLD = storefrontVin(3);
const SHA_ONE = 'a'.repeat(64);
const SHA_TWO = 'b'.repeat(64);

// DDEV's nginx gzips for a client that accepts it and weakens the tag to W/, so the look asks for the body as sent.
const IDENTITY = { 'Accept-Encoding': 'identity' };

// One `vehicle` element's fields, read from the feed's text.
interface XmlVehicle {
    vin: string;
    year: string;
    make: string;
    model: string;
    trim: string;
    mileage: string;
    price: string;
    url: string;
    photos: string[];
}

// The text of each `name` element in the fragment, in order.
function texts(fragment: string, name: string): string[] {
    return [...fragment.matchAll(new RegExp(`<${name}>([^<]*)</${name}>`, 'g'))].map((match) => match[1]);
}

// The feed's vehicles, each one's fields by name. The feed holds no text that needs unescaping in the fields read here.
function vehicles(xml: string): XmlVehicle[] {
    return [...xml.matchAll(/<vehicle>(.*?)<\/vehicle>/gs)].map((match) => {
        const field = (name: string): string => texts(match[1], name)[0] ?? '';

        return {
            vin: field('vin'), year: field('year'), make: field('make'), model: field('model'), trim: field('trim'),
            mileage: field('mileage'), price: field('price'), url: field('url'), photos: texts(match[1], 'photo'),
        };
    });
}

async function feed(request: APIRequestContext, headers: Record<string, string> = {}) {
    return request.get(FEED, { headers: { ...IDENTITY, ...headers } });
}

test.beforeEach(() => {
    removeStorefrontVehicles();
    insertVehicle(testVehicle(LISTED));
    insertVehicle(testVehicle(HELD, 'on_hold'));
    insertVehicle(testVehicle(SOLD, 'sold'));
    sql(
        'INSERT INTO vehicle_photos (vehicle_id, media_type, bytes, sha256, created_at) '
            + `SELECT id, 'image/jpeg', 100, '${SHA_ONE}', now() FROM vehicles WHERE vin = ${literal(LISTED)}`,
    );
    sql(
        'INSERT INTO vehicle_photos (vehicle_id, media_type, bytes, sha256, created_at) '
            + `SELECT id, 'image/png', 100, '${SHA_TWO}', now() FROM vehicles WHERE vin = ${literal(LISTED)}`,
    );
});

test.afterEach(() => {
    removeStorefrontVehicles();
});

test('the XML feed lists every car in stock with its VIN, validates against its schema, and answers its ETag with 304', async ({ request }) => {
    const answer = await feed(request);
    expect(answer.status()).toBe(200);
    expect(answer.headers()['content-type']).toBe('application/xml; charset=utf-8');
    const xml = await answer.text();
    expect(xml.startsWith('<?xml version="1.0" encoding="UTF-8"?>')).toBe(true);

    // The schema holds the feed, through xmllint, which exits non-zero on an invalid document.
    execFileSync('xmllint', ['--noout', '--schema', SCHEMA, '-'], { input: xml, stdio: ['pipe', 'pipe', 'pipe'] });

    // No staff-only field, and no car on hold or sold.
    expect(xml).not.toMatch(/<(cost|status|location|added_on|id)>/);
    expect(xml).not.toContain(HELD);
    expect(xml).not.toContain(SOLD);

    // The cars are those in stock, in the public list's first order, each with the database's values.
    const listed = vehicles(xml);
    const rows = sql(
        `SELECT vin, year, make, model, trim, mileage, list_price, ${SLUG} FROM vehicles WHERE status = 'in_stock' ORDER BY ${NEWEST}`,
    );
    expect(listed.map((vehicle) => vehicle.vin)).toEqual(rows.map((row) => row.split('|')[0]));
    const origin = new URL(listed[0].url).origin;
    expect(origin).toMatch(/^https?:\/\/[^/]+$/);
    expect(listed.map((vehicle) => [
        vehicle.vin, vehicle.year, vehicle.make, vehicle.model, vehicle.trim, vehicle.mileage, vehicle.price, vehicle.url,
    ].join('|'))).toEqual(rows.map((row) => {
        const [vin, year, make, model, trim, mileage, price, slug] = row.split('|');

        return [vin, year, make, model, trim, mileage, `${price}.00`, `${origin}/cars/${slug}`].join('|');
    }));

    // Each car's photos are the JSON feed's paths at the same address, the main one first.
    const json = (await (await request.get('/api/vehicles')).json()) as { vehicles: { photos: string[] }[] };
    expect(listed.map((vehicle) => vehicle.photos)).toEqual(json.vehicles.map((vehicle) => vehicle.photos.map((path) => `${origin}${path}`)));
    expect(listed.find((vehicle) => vehicle.vin === LISTED)?.photos).toEqual([
        `${origin}/files/photos/${SHA_ONE}.jpg`,
        `${origin}/files/photos/${SHA_TWO}.png`,
    ]);

    // The VIN is the feed's alone: the JSON feed and the public list still give none.
    expect(JSON.stringify(json)).not.toContain(LISTED);
    expect(await (await request.get('/cars?make=Probe')).text()).not.toContain(LISTED);

    // A strong tag, the SHA-256 of the body; that tag answers 304 with no body, another the feed again.
    const tag = answer.headers().etag;
    expect(tag).toMatch(/^"[0-9a-f]{64}"$/);
    expect(answer.headers()['cache-control']).toBe('private, no-cache');
    const unchanged = await feed(request, { 'If-None-Match': tag });
    expect(unchanged.status()).toBe(304);
    expect((await unchanged.body()).length).toBe(0);
    const other = await feed(request, { 'If-None-Match': '"nope"' });
    expect(other.status()).toBe(200);
    expect(await other.text()).toBe(xml);

    // A changed price changes the feed and its tag, so the old tag no longer answers 304.
    sql(`UPDATE vehicles SET list_price = 18900 WHERE vin = ${literal(LISTED)}`);
    const changed = await feed(request, { 'If-None-Match': tag });
    expect(changed.status()).toBe(200);
    expect(changed.headers().etag).not.toBe(tag);
    expect(await changed.text()).toContain('<price>18900.00</price>');
});
