// The vehicle page's panels load after it (#31): from the inventory list a click visits the page, whose answer holds a
// skeleton for each panel, and Tessera then sends one refresh per panel to its own route, naming that panel alone, each
// answered with its fragment; the rest of the page stays the same nodes.

import { expect, test, type Route } from '@playwright/test';
import { signIn } from './support/inventory';
import {
    arrived, exchangesTo, expectPanelsArrived, insertPanelVehicle, insertPriceChange, markNode, nodeMark, PANELS,
    recordExchanges, removePanelLooks, skeleton, vin,
} from './support/panels';
import { collectErrors, ready } from './support/tessera';

const VIN = vin('0LTPNLZY2SR31001');
const PATH = `/app/inventory/${VIN}`;

test.beforeEach(() => {
    removePanelLooks();
    insertPanelVehicle({ vin: VIN, listPrice: 30000 });
    insertPanelVehicle({ vin: vin('0LTPNLZY2SR31002'), listPrice: 29500 });
    insertPriceChange(VIN, 31000, 30000, '2026-10-01');
});
test.afterEach(() => removePanelLooks());

test('a click from the list paints skeletons, then each panel arrives by its own refresh', async ({ page }) => {
    const errors = collectErrors(page);
    await signIn(page);
    await page.goto(`/app/inventory?q=${VIN}`);
    await ready(page);
    await page.evaluate(() => Object.assign(window, { panelsDocument: 'kept' }));

    // Hold the three refreshes, so the skeletons can be read before they go.
    const held: Route[] = [];
    await page.route((url) => PANELS.some((panel) => url.pathname === `${PATH}/${panel.key}`), (route) => {
        held.push(route);
    });
    const exchanges = recordExchanges(page);

    await page.locator('#vehicles-table').getByRole('link', { name: VIN, exact: true }).click();
    await expect(page.locator('#vehicle-title')).toBeVisible();
    expect(page.url()).toContain(PATH);
    expect(await page.evaluate(() => (window as Window & { panelsDocument?: string }).panelsDocument)).toBe('kept');

    const [visit] = exchangesTo(exchanges, PATH);
    expect(visit.kind).toBe('visit');
    expect(visit.status).toBe(200);

    for (const panel of PANELS) {
        const section = skeleton(page, panel.key, VIN);
        await expect(section).toHaveAttribute('aria-busy', 'true');
        await expect(section).toHaveAttribute('data-lazy', `${PATH}/${panel.key}`);
        await expect(section).toHaveAttribute('data-t-anchor', panel.anchor);
        await expect(section).not.toHaveAttribute('data-t-props');
        await expect(section).not.toHaveAttribute('data-poll__30s');
        await expect(section.getByRole('heading', { level: 3 })).toHaveText(panel.heading);
        await expect(section.locator('.skeleton')).toHaveCount(3);
    }

    await expect(skeleton(page, 'price-history', VIN)).not.toContainText('$31,000');
    await markNode(page, '#vehicle-title', 'card');
    await markNode(page, '#vehicle-photos', 'photos');
    await expect.poll(() => held.length).toBe(3);

    // Each held refresh goes on to the site; its answer's templates are read on the way back.
    const templates = new Map<string, string[]>();

    for (const route of held) {
        const response = await route.fetch();
        const body = await response.text();
        templates.set(new URL(route.request().url()).pathname, [...body.matchAll(/<template data-target="([^"]+)"/g)].map((match) => match[1]));
        await route.fulfill({ response, body });
    }

    await expectPanelsArrived(page, VIN);

    for (const panel of PANELS) {
        const [refresh, ...more] = exchangesTo(exchanges, `${PATH}/${panel.key}`);
        expect(more).toHaveLength(0);
        expect(refresh.kind).toBe('refresh');
        expect(refresh.method).toBe('GET');
        expect(refresh.targets).toMatch(new RegExp(`^\\S+ @${panel.anchor}$`));
        expect(refresh.status).toBe(200);
        expect(refresh.contentType).toMatch(/^text\/vnd\.tessera\+html/);
        const answered = templates.get(`${PATH}/${panel.key}`) ?? [];
        expect(answered).toHaveLength(1);
        expect(answered[0]).toMatch(new RegExp(`${panel.key}$`));

        const section = arrived(page, panel.key, VIN);
        await expect(section).not.toHaveAttribute('aria-busy');
        await expect(section).not.toHaveAttribute('data-lazy');
        await expect(section).toHaveAttribute('data-t-props', /\S/);
        await expect(section).toHaveAttribute('data-poll__30s', `${PATH}/${panel.key}`);
    }

    await expect(arrived(page, 'price-history', VIN)).toContainText('$31,000');
    await expect(arrived(page, 'similar', VIN)).toContainText(vin('0LTPNLZY2SR31002'));
    expect(await nodeMark(page, '#vehicle-title')).toBe('card');
    expect(await nodeMark(page, '#vehicle-photos')).toBe('photos');
    expect(errors).toEqual([]);
});
