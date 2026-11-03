// Staff's payment calculator on a vehicle's page (#26): it starts at the default APR the settings profile saves, a
// refused APR is answered in place on the settings page, and using the calculator makes no request.

import { expect, test } from '@playwright/test';
import { VINS, calculator, insertCar, recordRequests, walkCalculator } from './support/finance';
import { signIn } from './support/inventory';
import { fillProfile, restoreProfile, savedProfile } from './support/settings';
import { collectErrors, ready } from './support/tessera';
import { removeVins } from './support/vehicles';

let profile: string[] = [];

test.beforeEach(() => {
    profile = savedProfile();
    removeVins(VINS.staff);
    insertCar(VINS.staff);
});
test.afterEach(() => {
    restoreProfile(profile);
    removeVins(VINS.staff);
});

test("staff quote a payment on a vehicle's page at the APR the settings profile keeps", async ({ page }) => {
    const errors = collectErrors(page);
    await signIn(page);

    // The settings refuse an APR above 30%, in place, and keep what was typed.
    await page.goto('/app/settings');
    await ready(page);
    await expect(page.locator('#profile-default_apr')).toHaveValue(/^\d+\.\d\d$/);
    await fillProfile(page, { default_apr: '31' });
    await page.locator('#profile-save').click();
    await expect(page.locator('#profile-default_apr-error')).toHaveText('Give the APR as a percentage from 0 to 30, such as 6.9, or leave it blank for 6.90.');
    await expect(page.locator('#profile-default_apr')).toHaveValue('31');

    // 4.25 saves, and the form shows it with two decimals.
    await fillProfile(page, { default_apr: '4.25' });
    await page.locator('#profile-save').click();
    await expect(page.locator('#profile-default_apr')).toHaveValue('4.25');
    await expect(page.locator('#profile-default_apr-error')).toHaveCount(0);

    // The vehicle's page starts its calculator there, after the vehicle's card.
    await page.goto(`/app/inventory/${VINS.staff}`);
    await ready(page);
    await expect(calculator(page).root).toHaveAttribute('data-signals', '{price: 17900, down: 1700, trade: 0, term: 60, apr: 425 / 100, warn: 3}');
    await expect(page.locator('[data-t-c$=":card"] + [data-t-c$=":finance"] + [data-t-c$=":photos"]')).toHaveCount(1);

    const requests = recordRequests(page);
    await walkCalculator(page, 4.25);
    expect(requests).toEqual([]);
    expect(errors.filter((error) => !error.startsWith('Failed to load resource: the server responded with a status of 422'))).toEqual([]);
});
