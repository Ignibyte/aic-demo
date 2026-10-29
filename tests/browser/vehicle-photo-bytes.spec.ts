import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { FIXTURES, TITLE, VINS, addPhoto, fetchInPage, insertPhotoVehicle, removePhotoLooks, vehiclePath } from './support/photos';

test.beforeEach(() => {
    removePhotoLooks();
    insertPhotoVehicle(VINS.bytes);
});
test.afterEach(() => removePhotoLooks());

test('a photo URL answers its bytes inline with its type and an ETag; a range 206, past the end 416, an unknown photo 404', async ({ page }) => {
    const path = vehiclePath(VINS.bytes);
    const jpeg = readFileSync(FIXTURES.jpeg);
    const webp = readFileSync(FIXTURES.webp);
    await signIn(page);
    await page.goto(path);
    await addPhoto(page, FIXTURES.jpeg);
    await expect(page.locator('#vehicle-photo-1')).toHaveAttribute('alt', `${TITLE}, photo 1 of 1`);
    await addPhoto(page, FIXTURES.webp);
    await expect(page.locator('#vehicle-photo-2')).toHaveAttribute('alt', `${TITLE}, photo 2 of 2`);

    // The whole JPEG, inline: its bytes, its media type, an ETag, ranges offered, revalidated on every use.
    const whole = await fetchInPage(page, `${path}/photos/1`);
    expect(whole.status).toBe(200);
    expect(whole.headers['content-type']).toBe('image/jpeg');
    expect(whole.headers['content-disposition']).toBeUndefined();
    expect(whole.headers['accept-ranges']).toBe('bytes');
    expect(whole.headers['cache-control']).toBe('private, no-cache');
    const etag = whole.headers['etag'] ?? '';
    expect(etag).toMatch(/^"[0-9a-f]+-[0-9a-f]+"$/);
    expect(Buffer.compare(whole.body, jpeg)).toBe(0);

    // The page's own source for it answers the same bytes.
    const source = await page.locator('#vehicle-photo-1').getAttribute('src') ?? '';
    expect(Buffer.compare((await fetchInPage(page, source)).body, jpeg)).toBe(0);

    // A byte range: 206 with exactly those bytes.
    const part = await fetchInPage(page, `${path}/photos/1`, { headers: { Range: 'bytes=100-199' } });
    expect(part.status).toBe(206);
    expect(part.headers['content-range']).toBe(`bytes 100-199/${jpeg.length}`);
    expect(Buffer.compare(part.body, jpeg.subarray(100, 200))).toBe(0);

    // A range that starts at the file's size: 416, naming the size.
    const past = await fetchInPage(page, `${path}/photos/1`, { headers: { Range: `bytes=${jpeg.length}-` } });
    expect(past.status).toBe(416);
    expect(past.headers['content-range']).toBe(`bytes */${jpeg.length}`);

    // Its ETag back: 304, no body.
    const unchanged = await fetchInPage(page, `${path}/photos/1`, { headers: { 'If-None-Match': etag } });
    expect(unchanged.status).toBe(304);

    // The WebP at the second position, with its own media type.
    const second = await fetchInPage(page, `${path}/photos/2`);
    expect(second.status).toBe(200);
    expect(second.headers['content-type']).toBe('image/webp');
    expect(Buffer.compare(second.body, webp)).toBe(0);

    // No third photo, and no photo for a VIN no vehicle has.
    expect((await fetchInPage(page, `${path}/photos/3`)).status).toBe(404);
    expect((await fetchInPage(page, `${vehiclePath(VINS.added)}/photos/1`)).status).toBe(404);
});
