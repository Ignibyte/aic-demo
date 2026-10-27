import { expect, test, type Page } from '@playwright/test';
import { sql } from './support/inventory';
import { VINS, fillVehicleForm, removeVins, signInAt, storedVehicle, testVehicle } from './support/vehicles';

const typed = { ...testVehicle(VINS.refused), make: 'Probe', model: 'Coupe', trim: '', mileage: 42, listPrice: 9900, cost: 8100 };

// Every field's value as the form shows it, but the VIN.
const kept = [
    ['#vehicle-year', '2023'],
    ['#vehicle-make', 'Probe'],
    ['#vehicle-model', 'Coupe'],
    ['#vehicle-trim', ''],
    ['#vehicle-body_style', 'Hatchback'],
    ['#vehicle-exterior_colour', 'Slate'],
    ['#vehicle-mileage', '42'],
    ['#vehicle-list_price', '9900'],
    ['#vehicle-cost', '8100'],
    ['#vehicle-status', 'in_stock'],
    ['#vehicle-added_on', '2026-09-15'],
];

// Saves the form and waits for the post's answer, which must refuse it.
async function saveRefused(page: Page): Promise<void> {
    const answer = page.waitForResponse((response) => response.request().method() === 'POST');
    await page.locator('#vehicle-save').click();
    expect((await answer).status()).toBe(422);
}

// The VIN field is the only one marked, with this message beside it, and every other value is as typed.
async function expectVinRefused(page: Page, vin: string, message: string): Promise<void> {
    await expect(page.locator('#vehicle-error')).toHaveText('Fix the fields marked below.');
    await expect(page.locator('#vehicle-vin')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#vehicle-vin-error')).toHaveText(message);
    await expect(page.locator('[aria-invalid="true"]')).toHaveCount(1);
    await expect(page.locator('#vehicle-vin')).toHaveValue(vin);

    for (const [field, value] of kept) {
        await expect(page.locator(field)).toHaveValue(value);
    }
}

test.beforeEach(() => removeVins(VINS.refused));
test.afterEach(() => removeVins(VINS.refused));

test('a bad VIN is refused beside its field, the form keeps what was typed, and nothing is saved', async ({ page }) => {
    await signInAt(page, '/app/inventory/new');
    await fillVehicleForm(page, typed);

    // A wrong check digit, typed in small letters: the message names the right digit and the field keeps the typing.
    const wrongDigit = VINS.refusedWrongDigit.toLowerCase();
    await page.locator('#vehicle-vin').fill(wrongDigit);
    await saveRefused(page);
    await expectVinRefused(page, wrongDigit, 'This VIN does not add up: its ninth character, the check digit, would be X.');

    await page.locator('#vehicle-vin').fill('0LTPWBAD');
    await saveRefused(page);
    await expectVinRefused(page, '0LTPWBAD', 'A VIN is 17 letters and digits, without I, O or Q.');

    const taken = sql("SELECT vin FROM vehicles WHERE vin NOT LIKE '0LTPW%' ORDER BY vin LIMIT 1")[0];
    await page.locator('#vehicle-vin').fill(taken);
    await saveRefused(page);
    await expectVinRefused(page, taken, 'Another vehicle on the lot has this VIN.');

    expect(storedVehicle(VINS.refused)).toBeNull();
    expect(storedVehicle(VINS.refusedWrongDigit)).toBeNull();
});
