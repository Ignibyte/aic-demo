// Lotline walked with AIC's profiler toolbar open (#25): each exchange the ticket names shows in the toolbar's list with
// its targets, status, ms, statements and bytes, and each opens as its profile in /_profiler/{id}. gate:23 runs it in
// its profiled pass only; it skips on a site without the profiler.

import { expect, test } from '@playwright/test';
import { signIn } from './support/dashboard';
import { VINS, collectProfilerErrors, exchangeLine, expectExchange, expectProfile, skipUnlessProfiled, toolbar } from './support/profiler';
import { ready } from './support/tessera';
import { fillVehicleForm, removeVins, testVehicle } from './support/vehicles';

test.beforeEach(async ({ request }) => {
    await skipUnlessProfiled(request);
    removeVins(VINS.saved, VINS.refused);
});
test.afterEach(() => removeVins(VINS.saved, VINS.refused));

test('the toolbar lists a load, the bell, the poll, a page, a filter, a sort and a form refused and saved, each with its profile', async ({ page }) => {
    test.setTimeout(90_000);
    const errors = collectProfilerErrors(page);
    const results = (count: number) => expectExchange(page, exchangeLine('visit', 'GET', '/app/inventory', '@inventory-results', '200'), count);

    // The page load: the stream fills the summary with the page's own figures, and its link is the page's profile.
    await signIn(page);
    await ready(page);
    await toolbar(page).locator('summary').click();
    await expect(toolbar(page).locator('summary')).toHaveText(exchangeLine('AIC · load', 'GET', '/app', '', '200'));
    await expectProfile(page, toolbar(page).getByRole('link', { name: "This page's profile" }), 'GET /app', '200');

    // The bell's stream, opened by the page, and the dashboard's poll of its live part, 204 while nothing changed.
    const bell = await expectExchange(page, exchangeLine('stream', 'GET', '/app/inbox/events', '', '200'), 1);
    await expectProfile(page, bell, 'GET /app/inbox/events', '200', { query: 'after=0' });
    const poll = await expectExchange(page, exchangeLine('refresh', 'GET', '/app', '@dashboard-live', '204'), 1, 20_000);
    await expectProfile(page, poll, 'GET /app', '204');

    // To the inventory by the sidebar, then a page change, a filter and a sort, each a visit of the results alone,
    // which reads neither the bell's notifications nor the footer's dealership: they are not part of the answer.
    await page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Inventory' }).click();
    await expect(page).toHaveURL(/\/app\/inventory$/);
    await page.locator('#vehicles-pages a', { hasText: /^2$/ }).click();
    await expect(page).toHaveURL(/\/app\/inventory\?page=2$/);
    await expectProfile(page, await results(1), 'GET /app/inventory', '200', { query: 'page=2' });

    await page.locator('#filter-make').selectOption({ index: 1 });
    const make = await page.locator('#filter-make').inputValue();
    await page.locator('#inventory-filters').getByRole('button', { name: 'Filter' }).click();
    await expectProfile(page, await results(2), 'GET /app/inventory', '200', {
        query: `q=&make=${make}&status=&min_price=&max_price=&sort=`,
        absent: /aic_notifications|dealership_profile/,
    });

    await page.locator('#vehicles-table thead th', { hasText: 'Price' }).getByRole('link').click();
    await expect(page).toHaveURL(new RegExp(`/app/inventory\\?make=${make}&sort=price$`));
    await expectProfile(page, await results(3), 'GET /app/inventory', '200', { query: `make=${make}&sort=price` });

    // The vehicle form, refused in place (422) and then saved.
    await page.getByRole('link', { name: 'Add a vehicle' }).click();
    await expect(page).toHaveURL(/\/app\/inventory\/new$/);
    await fillVehicleForm(page, testVehicle(VINS.refused));
    await page.locator('#vehicle-save').click();
    await expect(page.locator('#vehicle-vin-error')).toBeVisible();
    const refused = await expectExchange(page, exchangeLine('submit', 'POST', '/app/inventory', 'content', '422'), 1);
    await expectProfile(page, refused, 'POST /app/inventory', '422');

    // Saved, Tessera's answer is the redirect itself: no body, and the car's page as its Tessera-Location, which Tessera
    // then visits.
    await page.locator('#vehicle-vin').fill(VINS.saved);
    await page.locator('#vehicle-save').click();
    await expect(page).toHaveURL(new RegExp(`/app/inventory/${VINS.saved}$`));
    const carPage = `/app/inventory/${VINS.saved}`;
    const saved = await expectExchange(page, exchangeLine('submit', 'POST', '/app/inventory', 'content', '200'), 1);
    await expect(saved).toHaveText(/ · 0 bytes$/);
    await expectProfile(page, saved, 'POST /app/inventory', '200', { location: new RegExp(`^${carPage}$`) });
    const car = await expectExchange(page, exchangeLine('visit', 'GET', carPage, 'content', '200'), 1);
    await expectProfile(page, car, `GET ${carPage}`, '200');

    expect(errors).toEqual([]);
});
