import { expect, test } from '@playwright/test';
import { sql } from './support/inventory';
import { busiestMake, NEWEST, PAGE, shownSlugs, SLUG, slugs } from './support/storefront';

// One vehicle as the feed gives it.
interface FeedVehicle {
    slug: string;
    path: string;
    year: number;
    make: string;
    model: string;
    trim: string;
    body_style: string;
    mileage: number;
    exterior_colour: string;
    price: string;
    photos: string[];
}

test("the feed's vehicles are the list's, in its order, with decimal prices and no VIN or cost", async ({ page, request }) => {
    const answer = await request.get('/api/vehicles');
    expect(answer.status()).toBe(200);
    expect(answer.headers()['content-type']).toMatch(/^application\/json/);
    const text = await answer.text();
    expect(text).not.toMatch(/0LT[0-9A-HJ-NPR-Z]{14}/);
    expect(text).not.toMatch(/"(vin|cost)"/);
    const feed = (JSON.parse(text) as { vehicles: FeedVehicle[] }).vehicles;

    // Every page of the list, its cards in order, is the feed.
    const listed: string[] = [];
    const pages = Math.ceil(feed.length / PAGE);
    for (let number = 1; number <= pages; number += 1) {
        await page.goto(number === 1 ? '/cars' : `/cars?page=${number}`);
        listed.push(...await shownSlugs(page));
    }
    expect(feed.map((vehicle) => vehicle.slug)).toEqual(listed);
    expect(listed).toEqual(slugs('', NEWEST));

    // Each vehicle's fields are the database's, its price the list price as a decimal string.
    const rows = sql(`SELECT ${SLUG}, year, make, model, trim, body_style, mileage, exterior_colour, list_price FROM vehicles WHERE status = 'in_stock' ORDER BY ${NEWEST}`);
    expect(feed.map((vehicle) => [
        vehicle.slug, vehicle.year, vehicle.make, vehicle.model, vehicle.trim, vehicle.body_style, vehicle.mileage,
        vehicle.exterior_colour, vehicle.price,
    ].join('|'))).toEqual(rows.map((row) => {
        const fields = row.split('|');

        return [...fields.slice(0, 8), `${fields[8]}.00`].join('|');
    }));
    for (const vehicle of feed) {
        expect(vehicle.path).toBe(`/cars/${vehicle.slug}`);
        expect(vehicle.price).toMatch(/^[0-9]+\.00$/);
    }

    // A make, asked in capitals, keeps that make alone; an unknown one, or a list of makes, keeps none.
    const make = busiestMake();
    const byMake = (await (await request.get(`/api/vehicles?make=${make.toUpperCase()}`)).json()) as { vehicles: FeedVehicle[] };
    expect(byMake.vehicles.map((vehicle) => vehicle.slug)).toEqual(feed.filter((vehicle) => vehicle.make === make).map((vehicle) => vehicle.slug));
    expect(byMake.vehicles.length).toBeGreaterThan(0);
    expect(await (await request.get('/api/vehicles?make=Nope')).json()).toEqual({ vehicles: [] });
    expect(await (await request.get(`/api/vehicles?make%5B%5D=${make}`)).json()).toEqual({ vehicles: [] });

    // One vehicle answers what the list gave for it; an unknown slug answers 404 as JSON.
    const item = await request.get(`/api/vehicles/${feed[0].slug}`);
    expect(item.status()).toBe(200);
    expect(await item.json()).toEqual(feed[0]);
    const missing = await request.get('/api/vehicles/2023-volkswagen-tiguan-000000');
    expect(missing.status()).toBe(404);
    expect(missing.headers()['content-type']).toMatch(/^application\/json/);
    expect(await missing.json()).toEqual({ error: 'No vehicle for sale has that slug.' });
});
