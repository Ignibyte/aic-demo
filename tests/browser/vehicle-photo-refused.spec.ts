import { expect, test, type Page } from '@playwright/test';
import { signIn } from './support/inventory';
import { collectFormErrors } from './support/forms';
import { expectMarksKept, markPage, ready } from './support/tessera';
import {
    FIXTURES, VINS, addPhoto, expectRefusedInPlace, insertPhotoRows, insertPhotoVehicle, photoCount, recordAnswers,
    removePhotoLooks, vehiclePath, type Answer,
} from './support/photos';

test.beforeEach(() => {
    removePhotoLooks();
    insertPhotoVehicle(VINS.refused);
});
test.afterEach(() => removePhotoLooks());

// The refusal landed in the same document: one 422 fragment, its reason in the focused alert, the URL the vehicle's,
// the marks kept, and the vehicle's photos as they were.
async function expectRefused(page: Page, answers: Answer[], reason: string, photos: number): Promise<void> {
    const path = vehiclePath(VINS.refused);
    await expect(page.locator('#vehicle-photo-error')).toHaveText(reason);
    await expect(page.locator('#vehicle-photo-error')).toBeFocused();
    await expectRefusedInPlace(answers, path);
    expect(new URL(page.url()).pathname).toBe(path);
    await expectMarksKept(page);
    expect(photoCount(VINS.refused)).toBe(photos);
}

test('a renamed text file, a file over 8 MB and a thirteenth photo are each refused in place with the reason', async ({ page }) => {
    const errors = collectFormErrors(page);
    await signIn(page);
    await page.goto(vehiclePath(VINS.refused));
    await ready(page);
    await markPage(page);
    const answers = recordAnswers(page);

    // A text file named `.jpg`: judged by its content, which is no image.
    await addPhoto(page, FIXTURES.text);
    await expectRefused(page, answers, 'The file must be one of: JPEG image, PNG image, WebP image.', 0);

    // A JPEG over the limit, under the request's cap, so it reaches the check.
    await addPhoto(page, FIXTURES.large);
    await expectRefused(page, answers, 'The file is larger than 8000000 bytes.', 0);

    expect(errors).toEqual([]);

    // With twelve photos already, a thirteenth real JPEG. The twelve are rows alone, so their images answer 404.
    insertPhotoRows(VINS.refused, 12);
    await addPhoto(page, FIXTURES.jpeg);
    await expectRefused(page, answers, 'A vehicle holds at most 12 photos. Delete one to add another.', 12);
    await expect(page.locator('#vehicle-photos img')).toHaveCount(12);

    expect(errors.filter((error) => !error.includes('status of 404'))).toEqual([]);
});
