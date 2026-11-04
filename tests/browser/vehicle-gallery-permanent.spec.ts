// The vehicle page's photo strip is permanent where its markup matches (#31): its id names the VIN, the count and the
// newest photo's bytes, so a redraw of the same vehicle with the same photos keeps it as the same live node, scrolled
// where it was; an upload, or another vehicle, gives a new strip with its own photos.

import { expect, test } from '@playwright/test';
import { sql } from './support/inventory';
import { insertPanelVehicle, markNode, nodeMark, removePanelLooks, vin } from './support/panels';
import { addPhoto, FIXTURES, insertPhotoRows } from './support/photos';
import { ready } from './support/tessera';
import { signInAt } from './support/vehicles';

const VIN = vin('0LTPNGLY2SR31701');
const OTHER = vin('0LTPNGLY2SR31702');
const PATH = `/app/inventory/${VIN}`;

// The strip on the page: the gallery's list of photos.
const STRIP = '#vehicle-photos ul[data-permanent]';

test.beforeEach(() => {
    removePanelLooks();
    insertPanelVehicle({ vin: VIN, listPrice: 30000 });
    insertPanelVehicle({ vin: OTHER, listPrice: 30500 });
    insertPhotoRows(VIN, 9);
});
test.afterEach(() => removePanelLooks());

test('the strip is kept with its scroll across a redraw, and new for another vehicle or other photos', async ({ page }) => {
    await signInAt(page, PATH);
    await ready(page);
    const strip = page.locator(STRIP);
    await expect(strip).toHaveAttribute('id', `vehicle-photos-${VIN}-9-000000000000`);

    // Scrolled, and marked, the strip stays the same node through a refused upload answered in place.
    await strip.evaluate((element) => {
        element.scrollTop = 120;
    });
    expect(await strip.evaluate((element) => element.scrollTop)).toBeGreaterThan(0);
    const scrolled = await strip.evaluate((element) => element.scrollTop);
    await markNode(page, STRIP, 'kept');
    await addPhoto(page, FIXTURES.text);
    await expect(page.locator('#vehicle-photos')).toContainText('The file must be one of: JPEG image, PNG image, WebP image.');
    await expect(page.locator('#vehicle-photo-form')).not.toHaveAttribute('data-loading');
    expect(await nodeMark(page, STRIP)).toBe('kept');
    expect(await strip.evaluate((element) => element.scrollTop)).toBe(scrolled);

    // An upload adds a photo: a new strip, its id naming ten photos and the new one's bytes.
    await addPhoto(page, FIXTURES.jpeg);
    await expect(strip).toHaveAttribute('id', new RegExp(`^vehicle-photos-${VIN}-10-[0-9a-f]{12}$`));
    const [tag] = sql(`SELECT substr(p.sha256, 1, 12) FROM vehicle_photos p JOIN vehicles v ON v.id = p.vehicle_id WHERE v.vin = '${VIN}' ORDER BY p.id DESC LIMIT 1`);
    await expect(strip).toHaveAttribute('id', `vehicle-photos-${VIN}-10-${tag}`);
    expect(await nodeMark(page, STRIP)).toBe('');
    await expect(strip.locator('img')).toHaveCount(10);

    // Another vehicle, reached from the similar vehicles: its own strip, with none of these photos.
    await markNode(page, STRIP, 'this one');
    await page.locator(`section#similar-${VIN}`).getByRole('link', { name: OTHER }).click();
    await expect(page.locator('#vehicle-title')).toHaveText('2024 Panelcar Coupe GT');
    await expect(page).toHaveURL(new RegExp(`/app/inventory/${OTHER}$`));
    await expect(strip).toHaveAttribute('id', `vehicle-photos-${OTHER}-0-none`);
    expect(await nodeMark(page, STRIP)).toBe('');
    await expect(strip.locator('img')).toHaveCount(0);
});
