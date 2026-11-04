// A row's link to its vehicle prefetches the vehicle's page on a press (#31), as Tessera does for every enhanced link:
// the visit goes at mousedown and the click takes that answer, so the page paints from one request with its panels as
// skeletons. The link carries no `data-prefetch` of its own; prefetch on hover waits for Lotline #43. The row's edit
// cells keep prefetch off. The press is dispatched rather than made by moving the mouse, so the look shows the press
// alone.

import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { exchangesTo, expectPanelsArrived, insertPanelVehicle, recordExchanges, removePanelLooks, skeleton, vin } from './support/panels';
import { collectErrors, ready } from './support/tessera';

const VIN = vin('0LTPNPFT2SR31601');
const PATH = `/app/inventory/${VIN}`;
const LIST = `/app/inventory?q=${VIN}`;

test.beforeEach(() => {
    removePanelLooks();
    insertPanelVehicle({ vin: VIN, listPrice: 30000 });
});
test.afterEach(() => removePanelLooks());

test('pressing the VIN link prefetches the vehicle page, and the click paints from that one request', async ({ page }) => {
    const errors = collectErrors(page);
    await signIn(page);
    await page.goto(LIST);
    await ready(page);

    const row = page.locator(`[data-t-anchor="vehicle-${VIN}"]`);
    const link = row.getByRole('link', { name: VIN, exact: true });
    await expect(link).not.toHaveAttribute('data-prefetch');
    await expect(row.getByRole('link', { name: '$30,000' })).toHaveAttribute('data-prefetch', 'off');
    await expect(row.getByRole('link', { name: 'In stock' })).toHaveAttribute('data-prefetch', 'off');

    // The press's answer is read only once the click takes it, so the request is counted as it goes.
    const exchanges = recordExchanges(page);
    const sent: string[] = [];
    page.on('request', (request) => {
        if (new URL(request.url()).pathname === PATH && request.headers()['tessera-request'] !== undefined) {
            sent.push(request.headers()['tessera-request']);
        }
    });

    await link.dispatchEvent('mousedown', { button: 0 });
    await expect.poll(() => sent).toEqual(['visit']);
    await link.dispatchEvent('click', { button: 0 });
    await expect(page.locator('#vehicle-title')).toBeVisible();
    await expect(skeleton(page, 'price-history', VIN).or(page.locator(`section#price-history-${VIN}`))).toHaveCount(1);
    await expectPanelsArrived(page, VIN);

    const [press] = exchangesTo(exchanges, PATH);
    expect(press.kind).toBe('visit');
    expect(press.status).toBe(200);
    expect(exchangesTo(exchanges, PATH)).toHaveLength(1);
    expect(sent).toEqual(['visit']);
    expect(errors).toEqual([]);
});
