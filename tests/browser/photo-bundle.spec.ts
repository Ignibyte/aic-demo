import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { work } from './support/schedules';
import {
    FIXTURES, SALESPERSON, TITLE, VINS, addPhoto, bundleWritten, fetchInPage, insertPhotoVehicle, insertSalesperson,
    removePhotoLooks, signInAs, vehiclePath, zipFiles,
} from './support/photos';

// The photo bundle (#36): the `photo-bundle` job, which a photo's add or delete queues, writes the vehicle's photos as
// one stored ZIP in AIC's private files; its route serves it to staff through AIC's `FileDownload`. These looks run the
// worker on this host (`./support/schedules`).

test.beforeEach(() => {
    removePhotoLooks();
    insertPhotoVehicle(VINS.bundle);
});
test.afterEach(() => removePhotoLooks());

test('the worker bundles the photos as a stored ZIP, served with its ETag, ranges and If-Range, and follows each change', async ({ page }) => {
    const path = vehiclePath(VINS.bundle);
    const zip = `${path}/photos.zip`;
    const jpeg = readFileSync(FIXTURES.jpeg);
    const webp = readFileSync(FIXTURES.webp);
    await signIn(page);
    await page.goto(path);
    await addPhoto(page, FIXTURES.jpeg);
    await expect(page.locator('#vehicle-notice')).toHaveText(`Added a photo of the ${TITLE}.`);
    await addPhoto(page, FIXTURES.webp);
    await expect(page.locator('#vehicle-photo-2')).toHaveAttribute('alt', `${TITLE}, photo 2 of 2`);

    // Queued, not written: no link, and the ZIP answers 404.
    await page.goto(path);
    await expect(page.locator('#vehicle-photo-bundle')).toHaveCount(0);
    expect((await fetchInPage(page, zip)).status).toBe(404);

    // The worker writes it; the page offers it, before the upload form.
    work();
    expect(bundleWritten(VINS.bundle)).toBe(true);
    await page.goto(path);
    const link = page.locator('#vehicle-photo-bundle');
    await expect(link).toHaveText('Download all photos (ZIP)');
    await expect(link).toHaveAttribute('href', zip);
    await expect(link).toHaveAttribute('download', '');
    expect(await link.evaluate((anchor) => {
        const form = document.querySelector('#vehicle-photo-form');

        return form !== null && (anchor.compareDocumentPosition(form) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0;
    })).toBe(true);

    // The whole ZIP: an attachment named by the VIN, ranges offered, a strong ETag, revalidated on every use.
    const whole = await fetchInPage(page, zip);
    expect(whole.status).toBe(200);
    expect(whole.headers['content-type']).toBe('application/zip');
    expect(whole.headers['content-disposition']).toMatch(new RegExp(`^attachment; filename="${VINS.bundle}-photos\\.zip"`));
    expect(whole.headers['accept-ranges']).toBe('bytes');
    expect(whole.headers['cache-control']).toBe('private, no-cache');
    const etag = whole.headers['etag'] ?? '';
    expect(etag).toMatch(/^"[0-9a-f]+-[0-9a-f]+"$/);

    // Stored entries in position order, each with its photo's bytes, size and CRC-32.
    const files = zipFiles(whole.body);
    expect(files.map((file) => file.name)).toEqual(['photo-1.jpg', 'photo-2.webp']);
    expect(files.map((file) => file.method)).toEqual([0, 0]);
    expect(Buffer.compare(files[0].bytes, jpeg)).toBe(0);
    expect(Buffer.compare(files[1].bytes, webp)).toBe(0);
    expect(files.map((file) => file.size)).toEqual([jpeg.length, webp.length]);

    // A byte range: 206 with exactly those bytes; one at its size: 416.
    const part = await fetchInPage(page, zip, { headers: { Range: 'bytes=0-29' } });
    expect(part.status).toBe(206);
    expect(part.headers['content-range']).toBe(`bytes 0-29/${whole.body.length}`);
    expect(Buffer.compare(part.body, whole.body.subarray(0, 30))).toBe(0);
    const past = await fetchInPage(page, zip, { headers: { Range: `bytes=${whole.body.length}-` } });
    expect(past.status).toBe(416);
    expect(past.headers['content-range']).toBe(`bytes */${whole.body.length}`);

    // Its ETag back: 304. With If-Range: its ETag keeps the range, a stale one sends the whole ZIP.
    expect((await fetchInPage(page, zip, { headers: { 'If-None-Match': etag } })).status).toBe(304);
    const resumed = await fetchInPage(page, zip, { headers: { Range: 'bytes=30-', 'If-Range': etag } });
    expect(resumed.status).toBe(206);
    expect(Buffer.compare(resumed.body, whole.body.subarray(30))).toBe(0);
    const stale = await fetchInPage(page, zip, { headers: { Range: 'bytes=30-', 'If-Range': '"0-0"' } });
    expect(stale.status).toBe(200);
    expect(Buffer.compare(stale.body, whole.body)).toBe(0);

    // Deleting the JPEG: once the worker runs, the ZIP holds the WebP alone, now photo 1.
    await page.locator('#vehicle-photo-1-delete').click();
    await expect(page.locator('#vehicle-notice')).toHaveText(`Deleted photo 1 of the ${TITLE}.`);
    work();
    const after = await fetchInPage(page, zip);
    expect(after.status).toBe(200);
    expect(after.headers['etag']).not.toBe(etag);
    const left = zipFiles(after.body);
    expect(left.map((file) => file.name)).toEqual(['photo-1.webp']);
    expect(Buffer.compare(left[0].bytes, webp)).toBe(0);

    // Deleting the last: the bundle goes, and so do the link and the ZIP.
    await page.goto(path);
    await page.locator('#vehicle-photo-1-delete').click();
    await expect(page.locator('#vehicle-notice')).toHaveText(`Deleted photo 1 of the ${TITLE}.`);
    work();
    expect(bundleWritten(VINS.bundle)).toBe(false);
    await page.goto(path);
    await expect(page.locator('#vehicle-photo-bundle')).toHaveCount(0);
    expect((await fetchInPage(page, zip)).status).toBe(404);
});

test('a salesperson downloads the ZIP; an unknown VIN answers 404; signed out, the ZIP asks for sign-in', async ({ browser, page }) => {
    const path = vehiclePath(VINS.bundle);
    const zip = `${path}/photos.zip`;
    await signIn(page);
    await page.goto(path);
    await addPhoto(page, FIXTURES.jpeg);
    await expect(page.locator('#vehicle-notice')).toHaveText(`Added a photo of the ${TITLE}.`);
    work();

    // A salesperson may not change the photos, but may download them.
    const password = insertSalesperson();
    const sellerContext = await browser.newContext();
    const seller = await sellerContext.newPage();
    await signInAs(seller, SALESPERSON, password);
    await seller.goto(path);
    await expect(seller.locator('#vehicle-photo-form')).toHaveCount(0);
    await expect(seller.locator('#vehicle-photo-bundle')).toHaveAttribute('href', zip);
    const sold = await fetchInPage(seller, zip);
    expect(sold.status).toBe(200);
    expect(zipFiles(sold.body).map((file) => file.name)).toEqual(['photo-1.jpg']);
    await sellerContext.close();

    // No vehicle has this VIN.
    expect((await fetchInPage(page, `${vehiclePath(VINS.unknown)}/photos.zip`)).status).toBe(404);

    // Signed out, the ZIP's URL lands on the sign-in page.
    const visitorContext = await browser.newContext();
    const visitor = await visitorContext.newPage();
    await visitor.goto(zip);
    await expect(visitor).toHaveURL(/\/sign-in(\?|$)/);
    await expect(visitor.locator('#sign-in-email')).toBeVisible();
    await visitorContext.close();
});
