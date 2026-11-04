// An arrived panel polls its own route every 30 seconds (#31): with nothing changed each poll answers 204 and the panel
// stays the same node; after a price change the next poll of the price history answers 200 with one point more.

import { expect, test } from '@playwright/test';
import {
    arrived, exchangesTo, expectPanelsArrived, insertPanelVehicle, insertPriceChange, markNode, nodeMark, PANELS,
    recordExchanges, removePanelLooks, vin,
} from './support/panels';
import { collectErrors, ready } from './support/tessera';
import { signInAt } from './support/vehicles';

const VIN = vin('0LTPNPLL2SR31201');
const PATH = `/app/inventory/${VIN}`;

test.beforeEach(() => {
    removePanelLooks();
    insertPanelVehicle({ vin: VIN, listPrice: 30000 });
});
test.afterEach(() => removePanelLooks());

test('a poll answers 204 while nothing changed, and the panel once its data did', async ({ page }) => {
    const errors = collectErrors(page);
    await page.clock.install();
    await signInAt(page, PATH);
    await ready(page);
    await expectPanelsArrived(page, VIN);

    for (const panel of PANELS) {
        await markNode(page, `section#${panel.key}-${VIN}`, panel.key);
    }

    const exchanges = recordExchanges(page);
    await page.clock.runFor(31_000);

    for (const panel of PANELS) {
        await expect.poll(() => exchangesTo(exchanges, `${PATH}/${panel.key}`).length).toBe(1);
        const [poll] = exchangesTo(exchanges, `${PATH}/${panel.key}`);
        expect(poll.kind).toBe('refresh');
        expect(poll.targets).toMatch(new RegExp(`^\\S+ \\S+ @${panel.anchor}$`));
        expect(poll.status).toBe(204);
        expect(await nodeMark(page, `section#${panel.key}-${VIN}`)).toBe(panel.key);
    }

    await expect(arrived(page, 'price-history', VIN).locator('circle')).toHaveCount(2);
    insertPriceChange(VIN, 30000, 28500, '2026-10-20');
    exchanges.length = 0;
    await page.clock.runFor(30_000);

    await expect.poll(() => exchangesTo(exchanges, `${PATH}/price-history`).map((exchange) => exchange.status)).toEqual([200]);
    await expect(arrived(page, 'price-history', VIN).locator('circle')).toHaveCount(3);
    await expect(arrived(page, 'price-history', VIN).locator('li').nth(1)).toHaveText('2026-10-20 $28,500');
    await expect.poll(() => exchangesTo(exchanges, `${PATH}/similar`).map((exchange) => exchange.status)).toEqual([204]);
    expect(await nodeMark(page, `section#price-history-${VIN}`)).toBe('price-history');
    expect(errors).toEqual([]);
});
