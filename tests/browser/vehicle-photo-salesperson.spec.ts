import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { collectFormErrors } from './support/forms';
import {
    FIXTURES, SALESPERSON, TITLE, VINS, addPhoto, csrfToken, fetchInPage, insertPhotoVehicle, insertSalesperson, naturalWidth,
    photoCount, removePhotoLooks, signInAs, vehiclePath,
} from './support/photos';

test.beforeEach(() => {
    removePhotoLooks();
    insertPhotoVehicle(VINS.salesperson);
});
test.afterEach(() => removePhotoLooks());

test('a salesperson sees a vehicle photo but no upload form or delete button, and their posts answer 403', async ({ browser, page }) => {
    const path = vehiclePath(VINS.salesperson);

    // The gate's user, an admin, adds the photo.
    await signIn(page);
    await page.goto(path);
    await addPhoto(page, FIXTURES.jpeg);
    await expect(page.locator('#vehicle-notice')).toHaveText(`Added a photo of the ${TITLE}.`);

    // The salesperson, in a browser of their own.
    const password = insertSalesperson();
    const context = await browser.newContext();
    const seller = await context.newPage();
    const errors = collectFormErrors(seller);
    await signInAs(seller, SALESPERSON, password);
    await seller.goto(path);
    await expect(seller.locator('#vehicle-photo-1')).toHaveAttribute('alt', `${TITLE}, photo 1 of 1`);
    await expect.poll(() => naturalWidth(seller, '#vehicle-photo-1')).toBe(640);
    await expect(seller.locator('#vehicle-photos li').first()).toContainText('Main photo');
    await expect(seller.locator('#vehicle-photo-form')).toHaveCount(0);
    await expect(seller.locator('#vehicle-photo-file')).toHaveCount(0);
    await expect(seller.locator('#vehicle-photo-1-delete-form')).toHaveCount(0);
    await expect(seller.locator('#vehicle-photos button')).toHaveCount(0);

    expect(errors).toEqual([]);

    // Posting anyway, with their own session's token, from their own page: refused before anything is read or kept.
    const token = await csrfToken(seller);
    const file = { name: 'car.jpg', type: 'image/jpeg', bytes: [...readFileSync(FIXTURES.jpeg)] };
    const upload = await fetchInPage(seller, `${path}/photos`, { method: 'POST', fields: { aic_csrf: token }, file });
    expect(upload.status).toBe(403);
    const refusal = upload.body.toString('utf8');
    expect(refusal).toContain('<title>Not allowed · Lotline</title>');
    expect(refusal).toContain('Only a sales manager or an admin can change a vehicle');
    const remove = await fetchInPage(seller, `${path}/photos/1/delete`, { method: 'POST', fields: { aic_csrf: token } });
    expect(remove.status).toBe(403);
    expect(photoCount(VINS.salesperson)).toBe(1);

    // Chromium logs each refused post as a failed load; nothing else may be logged.
    expect(errors.filter((error) => !error.includes('status of 403'))).toEqual([]);
    await context.close();
});
