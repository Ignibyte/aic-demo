import { existsSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { collectFormErrors } from './support/forms';
import { expectMarksKept, markPage, ready } from './support/tessera';
import {
    FIXTURES, TITLE, VINS, addPhoto, expectChangedInPlace, fetchInPage, insertPhotoVehicle, naturalWidth, photoCount,
    photoFiles, recordAnswers, removePhotoLooks, vehiclePath,
} from './support/photos';

test.beforeEach(() => {
    removePhotoLooks();
    insertPhotoVehicle(VINS.added);
});
test.afterEach(() => removePhotoLooks());

test('a photo added on a vehicle page appears as its main photo in the same document; deleting it promotes the next', async ({ page }) => {
    const errors = collectFormErrors(page);
    const path = vehiclePath(VINS.added);
    await signIn(page);
    await page.goto(path);
    await ready(page);
    await markPage(page);
    await expect(page.locator('#vehicle-photos-empty')).toHaveText('No photos of this vehicle yet.');
    const answers = recordAnswers(page);

    // The JPEG: one Tessera submit, its 303 visited in place, and the photo shown as the main one, loaded.
    await addPhoto(page, FIXTURES.jpeg);
    await expect(page.locator('#vehicle-notice')).toHaveText(`Added a photo of the ${TITLE}.`);
    await expectChangedInPlace(answers, `${path}/photos`, path);
    await expect(page.locator('#vehicle-photos-empty')).toHaveCount(0);
    await expect(page.locator('#vehicle-photo-1')).toHaveAttribute('alt', `${TITLE}, photo 1 of 1`);
    await expect(page.locator('#vehicle-photo-1')).toHaveAttribute('src', /^\/files\/photos\/[0-9a-f]{64}\.jpg$/);
    await expect.poll(() => naturalWidth(page, '#vehicle-photo-1')).toBe(640);
    await expect(page.locator('#vehicle-photos li').first()).toContainText('Main photo');
    expect(new URL(page.url()).pathname).toBe(path);
    await expectMarksKept(page);
    expect(photoCount(VINS.added)).toBe(1);

    // The PNG comes second, after the JPEG.
    await addPhoto(page, FIXTURES.png);
    await expect(page.locator('#vehicle-photo-2')).toHaveAttribute('alt', `${TITLE}, photo 2 of 2`);
    await expectChangedInPlace(answers, `${path}/photos`, path);
    await expect(page.locator('#vehicle-photo-1')).toHaveAttribute('alt', `${TITLE}, photo 1 of 2`);
    await expect.poll(() => naturalWidth(page, '#vehicle-photo-2')).toBe(320);
    await expect(page.locator('#vehicle-photos li').nth(1)).not.toContainText('Main photo');
    await expectMarksKept(page);

    // Deleting the JPEG makes the PNG the main photo, its image the PNG's and not the deleted one's.
    await page.locator('#vehicle-photo-1-delete').click();
    await expect(page.locator('#vehicle-notice')).toHaveText(`Deleted photo 1 of the ${TITLE}.`);
    await expectChangedInPlace(answers, `${path}/photos/1/delete`, path);
    await expect(page.locator('#vehicle-photo-2')).toHaveCount(0);
    await expect(page.locator('#vehicle-photo-1')).toHaveAttribute('alt', `${TITLE}, photo 1 of 1`);
    await expect.poll(() => naturalWidth(page, '#vehicle-photo-1')).toBe(320);
    await expect(page.locator('#vehicle-photos li').first()).toContainText('Main photo');
    await expectMarksKept(page);
    expect(photoCount(VINS.added)).toBe(1);

    // Deleting the vehicle deletes its photo: the row by its foreign key, then the file, which no other photo holds.
    const [file] = photoFiles(VINS.added);
    await page.locator('#vehicle-delete').click();
    await page.locator('#vehicle-delete-confirm').click();
    await expect(page).toHaveURL(/\/app\/inventory$/);
    expect(photoCount(VINS.added)).toBe(0);
    expect(file.held).toBe(1);
    expect(existsSync(file.path)).toBe(false);
    expect((await fetchInPage(page, `${path}/photos/1`)).status).toBe(404);

    expect(errors.filter((error) => !error.includes('status of 404'))).toEqual([]);
});
