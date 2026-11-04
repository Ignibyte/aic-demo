import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { work } from './support/schedules';
import { FIXTURES, TITLE, VINS, addPhoto, insertPhotoVehicle, removePhotoLooks, vehiclePath, zipFiles } from './support/photos';

// The photo bundle's link is a plain link with `download`, so it works with JavaScript off (#36).
test.use({ javaScriptEnabled: false });

test.beforeEach(() => {
    removePhotoLooks();
    insertPhotoVehicle(VINS.noScript);
});
test.afterEach(() => removePhotoLooks());

test('with JavaScript off, the written bundle is linked from the vehicle page and downloads as the VIN-named ZIP', async ({ page }) => {
    const path = vehiclePath(VINS.noScript);
    await signIn(page);
    await page.goto(path);
    await addPhoto(page, FIXTURES.png);
    await expect(page.locator('#vehicle-notice')).toHaveText(`Added a photo of the ${TITLE}.`);
    await expect(page.locator('#vehicle-photo-bundle')).toHaveCount(0);

    work();
    await page.goto(path);
    const link = page.locator('#vehicle-photo-bundle');
    await expect(link).toHaveText('Download all photos (ZIP)');

    const [download] = await Promise.all([page.waitForEvent('download'), link.click()]);
    expect(download.suggestedFilename()).toBe(`${VINS.noScript}-photos.zip`);
    const files = zipFiles(readFileSync(await download.path()));
    expect(files.map((file) => file.name)).toEqual(['photo-1.png']);
    expect(Buffer.compare(files[0].bytes, readFileSync(FIXTURES.png))).toBe(0);
});
