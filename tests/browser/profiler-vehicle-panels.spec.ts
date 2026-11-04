// The vehicle page's panels under AIC's profiler (#31): the visit from the list lists no statement on any panel's
// tables, and each panel's refresh reads its own and none of the photos, the sale, finance's rates or another panel's.
// gate:23 runs it in its profiled pass only; it skips on a site without the profiler.

import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { expectPanelsArrived, insertPanelVehicle, insertPriceChange, removePanelLooks, vin } from './support/panels';
import { collectProfilerErrors, exchangeLine, expectExchange, expectProfile, skipUnlessProfiled, toolbar } from './support/profiler';
import { ready } from './support/tessera';

const VIN = vin('0LTPNPRF2SR31801');
const PATH = `/app/inventory/${VIN}`;

// What each read looks like in a profile's Database tab: the table it reads from, or, for the similar vehicles, the
// condition on the body style that every other read of `vehicles` lacks.
const READS = {
    prices: 'FROM vehicle_prices',
    similar: 'body_style = ',
    leads: 'FROM leads',
    holds: 'FROM holds|FROM hold_states',
    photos: 'FROM vehicle_photos',
    sale: 'FROM sales',
    finance: 'dealership_profile',
};

// The SQL a profile must not list, as one pattern.
function none(...reads: string[]): RegExp {
    return new RegExp(reads.join('|'));
}

test.beforeEach(async ({ request }) => {
    await skipUnlessProfiled(request);
    removePanelLooks();
    insertPanelVehicle({ vin: VIN, listPrice: 30000 });
    insertPanelVehicle({ vin: vin('0LTPNPRF2SR31802'), listPrice: 29000 });
    insertPriceChange(VIN, 31000, 30000, '2026-10-05');
});
test.afterEach(() => removePanelLooks());

test('the visit reads no panel, and each refresh reads only its own', async ({ page }) => {
    test.setTimeout(90_000);
    const errors = collectProfilerErrors(page);
    await signIn(page);
    await page.goto(`/app/inventory?q=${VIN}`);
    await ready(page);
    await page.locator('#vehicles-table').getByRole('link', { name: VIN, exact: true }).click();
    await expectPanelsArrived(page, VIN);

    await toolbar(page).locator('summary').click();
    await expect(toolbar(page).locator('details')).toHaveAttribute('open', '');

    const visit = await expectExchange(page, exchangeLine('visit', 'GET', PATH, 'content', '200'), 1);
    await expectProfile(page, visit, `GET ${PATH}`, '200', { absent: none(READS.prices, READS.similar, READS.leads, READS.holds) });

    const price = await expectExchange(page, exchangeLine('refresh', 'GET', `${PATH}/price-history`, '@vehicle-price-history', '200'), 1);
    await expectProfile(page, price, `GET ${PATH}/price-history`, '200', {
        absent: none(READS.similar, READS.leads, READS.holds, READS.photos, READS.sale, READS.finance),
    });

    const similar = await expectExchange(page, exchangeLine('refresh', 'GET', `${PATH}/similar`, '@vehicle-similar', '200'), 1);
    await expectProfile(page, similar, `GET ${PATH}/similar`, '200', {
        absent: none(READS.prices, READS.leads, READS.holds, READS.photos, READS.sale, READS.finance),
    });

    const activity = await expectExchange(page, exchangeLine('refresh', 'GET', `${PATH}/activity`, '@vehicle-activity', '200'), 1);
    await expectProfile(page, activity, `GET ${PATH}/activity`, '200', {
        absent: none(READS.prices, READS.similar, READS.photos, READS.sale, READS.finance),
    });

    expect(errors).toEqual([]);
});
