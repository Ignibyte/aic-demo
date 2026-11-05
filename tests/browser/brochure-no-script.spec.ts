import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { brochureWritten, expectBrochureLinks, insertBrochureCar, plainGet, removeBrochureLooks, VINS } from './support/brochures';
import { signIn } from './support/inventory';
import { addPhoto, FIXTURES, vehiclePath } from './support/photos';
import { work } from './support/schedules';

// The brochure's links are plain links, so they work with JavaScript off (#44).
test.use({ javaScriptEnabled: false });

const VIN = VINS.noScript;

test.beforeEach(() => removeBrochureLooks());
test.afterEach(() => removeBrochureLooks());

test('with JavaScript off, the kept brochure opens from the car page and downloads as the slug-named file', async ({ page }) => {
    const path = insertBrochureCar(VIN);
    const slug = path.slice('/cars/'.length);
    await signIn(page);
    await page.goto(vehiclePath(VIN));
    await addPhoto(page, FIXTURES.png);
    await expect(page.locator('#vehicle-notice')).toHaveText('Added a photo of the 2023 Probe Hatch SE.');
    await page.goto(path);
    await expect(page.locator('#car-brochure-view')).toHaveCount(0);

    work();
    expect(brochureWritten(VIN)).toBe(true);
    await page.goto(path);
    await expectBrochureLinks(page, path);
    const kept = await (await plainGet(page, `${path}/brochure`)).body();

    // "View the brochure" opens it in the page, in the theme's styles.
    await page.locator('#car-brochure-view').click();
    await expect(page).toHaveURL(new RegExp(`${path}/brochure$`));
    await expect(page).toHaveTitle('2023 Probe Hatch brochure · Lotline');
    await expect(page.locator('#brochure-price')).toHaveText('$17,900');
    await expect(page.locator('.car-brochure')).toHaveCSS('max-width', /px$/);

    // "Download the brochure" saves the same file, named from the car's slug.
    await page.goBack();
    const [download] = await Promise.all([page.waitForEvent('download'), page.locator('#car-brochure-download').click()]);
    expect(download.suggestedFilename()).toBe(`${slug}-brochure.html`);
    expect(Buffer.compare(readFileSync(await download.path()), kept)).toBe(0);
});
