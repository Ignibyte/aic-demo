// A car's one-page brochure (#44): queued when staff change the car, rendered and kept by the `car-brochure` job in
// the theme's brochure layout, linked from the car's storefront page once it is kept, and served there inline and as
// an attachment through AIC's `FileDownload`, with its ranges and its ETag. Printed, the theme's `@media print` rules
// take the sheet out of its box and hide the line that says how to print.

import { expect, test } from '@playwright/test';
import {
    brochureWritten, contentParts, expectBrochureLinks, insertBrochureCar, plainGet, removeBrochureLooks, VEHICLES,
    VINS,
} from './support/brochures';
import { vehicleRow } from './support/inline-edit';
import { signIn } from './support/inventory';
import { addPhoto, FIXTURES, vehiclePath } from './support/photos';
import { work } from './support/schedules';
import { ready } from './support/tessera';
import { testVehicle } from './support/vehicles';

const VIN = VINS.brochure;

// What the public calls the look's car, and what its brochure is titled.
const TITLE = '2023 Probe Hatch';

test.beforeEach(() => removeBrochureLooks());
test.afterEach(() => removeBrochureLooks());

test('a changed car gets its brochure from the worker: linked from its page, served inline and attached, ranged, printed', async ({ page }) => {
    test.setTimeout(120_000);
    const path = insertBrochureCar(VIN);
    const slug = path.slice('/cars/'.length);
    const brochure = `${path}/brochure`;
    const vehicle = testVehicle(VIN);

    // A photo added queues the brochure; until the worker runs there is none, and no link.
    await signIn(page);
    await page.goto(vehiclePath(VIN));
    await addPhoto(page, FIXTURES.jpeg);
    await expect(page.locator('#vehicle-notice')).toHaveText(`Added a photo of the ${TITLE} SE.`);
    await page.goto(path);
    await expect(page.locator('#car-brochure-view')).toHaveCount(0);
    expect((await plainGet(page, brochure)).status()).toBe(404);

    // The worker keeps it, and the car's page links it between the profile and the lead forms.
    work();
    expect(brochureWritten(VIN)).toBe(true);
    await page.goto(path);
    const dealership = (await page.locator('#store-header a').innerText()).trim();
    await expectBrochureLinks(page, path);
    expect(await contentParts(page)).toEqual(['car', 'brochure', 'leads']);

    // Signed out, it answers inline as HTML, validated and ranged by AIC, with no VIN, no cost and no form in it.
    await page.context().clearCookies();
    const inline = await plainGet(page, brochure);
    expect(inline.status()).toBe(200);
    const headers = inline.headers();
    expect(headers['content-type']).toBe('text/html; charset=utf-8');
    expect(headers['content-disposition'] ?? '').not.toContain('attachment');
    expect(headers['accept-ranges']).toBe('bytes');
    expect(headers.etag).toMatch(/^"[^"]+"$/);
    expect(headers['cache-control']).toBe('private, no-cache');
    const body = await inline.body();
    const html = body.toString('utf8');
    expect(html).not.toContain(VIN);
    expect(html).not.toContain('15,100');
    expect(html).not.toContain('15100');
    expect(html).not.toContain('<form');
    expect(html).not.toContain('compare-drawer');

    // Opened, it is the car's sheet in the theme's styles.
    await page.goto(brochure);
    await expect(page).toHaveTitle(`${TITLE} brochure · Lotline`);
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await expect(page.locator('#brochure-dealership')).toHaveText(dealership);
    await expect(page.locator('#brochure')).toHaveText(TITLE);
    await expect(page.locator('#brochure-subtitle')).toHaveText(vehicle.trim);
    await expect(page.locator('#brochure-price')).toHaveText('$17,900');
    await expect(page.locator('#brochure-facts dt')).toHaveText(['Year', 'Make', 'Model', 'Trim', 'Body style', 'Mileage', 'Colour']);
    await expect(page.locator('#brochure-facts dd')).toHaveText(['2023', 'Probe', 'Hatch', 'SE', 'Hatchback', '5,000 miles', 'Slate']);
    const photo = page.locator('#car-photo-1');
    await expect(photo).toHaveAttribute('alt', `${TITLE}, photo 1 of 1`);
    await expect.poll(() => photo.evaluate((image: HTMLImageElement) => image.naturalWidth)).toBeGreaterThan(0);
    await expect(page.locator('#brochure-contact')).toContainText(`${dealership}.`);
    const sheet = page.locator('.car-brochure');
    const shadow = () => sheet.evaluate((element) => getComputedStyle(element).boxShadow);
    const maxWidth = () => sheet.evaluate((element) => getComputedStyle(element).maxWidth);
    await expect(page.locator('#brochure-print')).toBeVisible();
    expect(await shadow()).not.toBe('none');
    expect(await maxWidth()).not.toBe('none');

    // Printed, the sheet leaves its box and the line on how to print goes.
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('#brochure-print')).toBeHidden();
    expect(await shadow()).toBe('none');
    expect(await maxWidth()).toBe('none');
    await page.emulateMedia({ media: 'screen' });
    await expect(page.locator('#brochure-print')).toBeVisible();

    // The second link sends the same bytes to be saved, named from the car's slug.
    const attached = await plainGet(page, `${brochure}/download`);
    expect(attached.status()).toBe(200);
    expect(attached.headers()['content-disposition']).toBe(`attachment; filename="${slug}-brochure.html"`);
    expect(Buffer.compare(await attached.body(), body)).toBe(0);

    // AIC answers its ranges and its ETag.
    const ranged = await plainGet(page, brochure, { Range: 'bytes=0-29' });
    expect(ranged.status()).toBe(206);
    expect(ranged.headers()['content-range']).toBe(`bytes 0-29/${body.length}`);
    expect(Buffer.compare(await ranged.body(), body.subarray(0, 30))).toBe(0);
    expect((await plainGet(page, brochure, { Range: `bytes=${body.length}-` })).status()).toBe(416);
    expect((await plainGet(page, brochure, { 'If-None-Match': headers.etag })).status()).toBe(304);
    expect((await plainGet(page, '/cars/2023-probe-hatch-000000/brochure')).status()).toBe(404);

    // A price saved in the list's row queues it again: the worker writes the new price.
    await signIn(page);
    await page.goto(VEHICLES);
    await ready(page);
    const row = vehicleRow(page, VIN);
    await row.locator('a', { hasText: '$17,900' }).click();
    await expect(page.locator(`#list_price-vehicle-${VIN}`)).toBeFocused();
    await page.keyboard.type('18250');
    await page.keyboard.press('Enter');
    await expect(row).toContainText('$18,250');
    work();
    await page.goto(brochure);
    await expect(page.locator('#brochure-price')).toHaveText('$18,250');

    // Put on hold from its form, the car leaves the storefront, and the worker deletes its brochure.
    await page.goto(`${vehiclePath(VIN)}/edit`);
    await page.locator('#vehicle-status').selectOption('on_hold');
    await page.locator('#vehicle-save').click();
    await expect(page.locator('#vehicle-notice')).toHaveText('Saved your changes.');
    work();
    expect(brochureWritten(VIN)).toBe(false);
    expect((await plainGet(page, brochure)).status()).toBe(404);
    expect((await plainGet(page, `${brochure}/download`)).status()).toBe(404);
});
