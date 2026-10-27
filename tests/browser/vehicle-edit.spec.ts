import { expect, test } from '@playwright/test';
import { VINS, expectFacts, insertVehicle, removeVins, signInAt, storedVehicle, testVehicle } from './support/vehicles';

const edited = testVehicle(VINS.edited);
const other = { ...testVehicle(VINS.other), model: 'Other' };

test.beforeEach(() => {
    removeVins(VINS.edited, VINS.other);
    insertVehicle(edited);
    insertVehicle(other);
});
test.afterEach(() => removeVins(VINS.edited, VINS.other));

test('editing a vehicle changes its detail page', async ({ page }) => {
    await signInAt(page, `/app/inventory/${edited.vin}`);
    await page.locator('#vehicle-edit').click();
    await expect(page).toHaveURL(new RegExp(`/app/inventory/${edited.vin}/edit$`));
    await expect(page.locator('#vehicle-vin')).toHaveValue(edited.vin);
    await expect(page.locator('#vehicle-mileage')).toHaveValue('5000');
    await expect(page.locator('#vehicle-cost')).toHaveValue('15100');

    await page.locator('#vehicle-mileage').fill('6500');
    await page.locator('#vehicle-list_price').fill('18750');
    await page.locator('#vehicle-exterior_colour').fill('Sky Blue');
    await page.locator('#vehicle-save').click();

    await expect(page).toHaveURL(new RegExp(`/app/inventory/${edited.vin}$`));
    await expect(page.locator('#vehicle-notice')).toHaveText('Saved your changes.');
    await expectFacts(page, [edited.vin, '2023', 'Probe', 'Hatch', 'SE', 'Hatchback', 'Sky Blue', '6,500 mi', '$18,750', '2026-09-15']);
    expect(storedVehicle(edited.vin)).toEqual({ ...edited, mileage: 6500, listPrice: 18750, colour: 'Sky Blue' });
});

test.describe('with the form sent as a plain post', () => {
    test.use({ javaScriptEnabled: false });

    test('an edit form pointed at another vehicle is refused and changes neither', async ({ page }) => {
        await signInAt(page, `/app/inventory/${edited.vin}/edit`);
        await page.locator('#vehicle-mileage').fill('99');
        await page.locator('#vehicle-form').evaluate((form, action) => form.setAttribute('action', action), `/app/inventory/${other.vin}`);

        const answer = page.waitForResponse((response) => response.request().method() === 'POST');
        await page.locator('#vehicle-save').click();
        expect((await answer).status()).toBe(400);
        await expect(page.locator('#vehicle-refused')).toContainText('This form was issued for another vehicle');

        expect(storedVehicle(edited.vin)).toEqual(edited);
        expect(storedVehicle(other.vin)).toEqual(other);
    });
});
