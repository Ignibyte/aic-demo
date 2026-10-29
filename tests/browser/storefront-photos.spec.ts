import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { literal, signIn, sql } from './support/inventory';
import { addPhoto, FIXTURES, vehiclePath } from './support/photos';
import { removeStorefrontVehicles, slugOf, storefrontVin } from './support/storefront';
import { insertVehicle, testVehicle } from './support/vehicles';

const VIN = storefrontVin(1);

test.beforeEach(() => {
    removeStorefrontVehicles();
    insertVehicle(testVehicle(VIN));
});

test.afterEach(() => {
    removeStorefrontVehicles();
});

test("a car's photos show on its public page and as its card's main photo, and the feed's paths answer their bytes", async ({ browser, page }) => {
    // Staff add two photos on the vehicle's page.
    await signIn(page);
    await page.goto(vehiclePath(VIN));
    await addPhoto(page, FIXTURES.jpeg);
    await expect(page.locator('#vehicle-photo-1')).toBeVisible();
    await addPhoto(page, FIXTURES.png);
    await expect(page.locator('#vehicle-photo-2')).toBeVisible();

    // A visitor who is not signed in sees them.
    const slug = slugOf(VIN);
    const context = await browser.newContext();
    const visitor = await context.newPage();
    await visitor.goto(`/cars/${slug}`);
    const main = visitor.locator('#car-photo-1');
    await expect(main).toHaveAttribute('alt', '2023 Probe Hatch, photo 1 of 2');
    await expect(main).toHaveAttribute('src', new RegExp(`^/cars/${slug}/photos/1\\?v=[0-9a-f]{16}$`));
    await expect(visitor.locator('#car-photo-2')).toHaveAttribute('alt', '2023 Probe Hatch, photo 2 of 2');
    await expect.poll(() => main.evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);
    await expect(visitor.locator('#car-photos-empty')).toHaveCount(0);

    await visitor.goto('/cars?make=Probe');
    const card = visitor.locator(`#car-${slug} img`);
    await expect(card).toHaveAttribute('src', new RegExp(`^/cars/${slug}/photos/1\\?v=[0-9a-f]{16}$`));
    await expect(card).toHaveAttribute('alt', '2023 Probe Hatch, main photo');
    await expect.poll(() => card.evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);

    // The feed names both photos, and the first answers the JPEG's bytes.
    const item = (await (await context.request.get(`/api/vehicles/${slug}`)).json()) as { photos: string[] };
    expect(item.photos).toHaveLength(2);
    expect(item.photos[0]).toMatch(new RegExp(`^/cars/${slug}/photos/1\\?v=`));
    const photo = await context.request.get(item.photos[0]);
    expect(photo.status()).toBe(200);
    expect(photo.headers()['content-type']).toBe('image/jpeg');
    expect(Buffer.compare(await photo.body(), readFileSync(FIXTURES.jpeg))).toBe(0);

    // Once the car is sold, its page and its photos answer 404.
    sql(`UPDATE vehicles SET status = 'sold' WHERE vin = ${literal(VIN)}`);
    expect((await context.request.get(item.photos[0])).status()).toBe(404);
    expect((await context.request.get(`/cars/${slug}`)).status()).toBe(404);
    await context.close();
});
