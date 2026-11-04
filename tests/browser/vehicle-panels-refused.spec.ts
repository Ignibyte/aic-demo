// What a panel's route refuses (#31): a visitor goes to sign in; a VIN on no vehicle, or one that is no VIN, answers
// the vehicle pages' 404; a refresh whose props were altered answers 400 and builds nothing. Asked plainly, a route
// answers the vehicle pages' frame holding that panel alone. A signed-in request goes from the page (`fetchInPage()`):
// Playwright's own request context does not send the `Secure` session cookie to the compiled site gate:23 serves over
// plain HTTP.

import { expect, test } from '@playwright/test';
import { insertPanelVehicle, PANELS, removePanelLooks, vin } from './support/panels';
import { fetchInPage } from './support/photos';
import { anchorPath, ready } from './support/tessera';
import { signInAt } from './support/vehicles';

const VIN = vin('0LTPNRFS2SR31301');
const NOWHERE = vin('0LTPNRFS2SR31399');
const PATH = `/app/inventory/${VIN}`;

test.beforeEach(() => {
    removePanelLooks();
    insertPanelVehicle({ vin: VIN, listPrice: 30000 });
});
test.afterEach(() => removePanelLooks());

test('a visitor is sent to sign in from each panel route', async ({ playwright, baseURL }) => {
    const visitor = await playwright.request.newContext({ baseURL, ignoreHTTPSErrors: true });

    for (const panel of PANELS) {
        const response = await visitor.get(`${PATH}/${panel.key}`, { maxRedirects: 0 });
        expect(response.status()).toBe(303);
        expect(response.headers().location).toMatch(/^\/sign-in\?next=/);
    }

    await visitor.dispose();
});

test('a VIN on no vehicle and a malformed one answer 404; altered props 400; a plain request the panel alone', async ({ page }) => {
    await signInAt(page, PATH);
    await ready(page);

    for (const panel of PANELS) {
        for (const missing of [NOWHERE, 'nope']) {
            const response = await fetchInPage(page, `/app/inventory/${missing}/${panel.key}`);
            expect(response.status).toBe(404);
            expect(response.body.toString()).toContain('No vehicle on the lot has that VIN. It may have been deleted.');
        }

        // The panel's path on the page, and its props with one character changed.
        const section = page.locator(`section#${panel.key}-${VIN}`);
        const props = (await section.getAttribute('data-t-props')) ?? '';
        expect(props).not.toBe('');
        const altered = props.slice(0, -1) + (props.endsWith('0') ? '1' : '0');
        const path = await anchorPath(page, panel.anchor);
        const refused = await fetchInPage(page, `${PATH}/${panel.key}`, {
            headers: {
                'Tessera-Request': 'refresh',
                'Tessera-Protocol': '2',
                'Tessera-Layout': 'lotline',
                'Tessera-Targets': `${path} ${altered} @${panel.anchor}`,
            },
        });
        expect(refused.status).toBe(400);

        const plain = await fetchInPage(page, `${PATH}/${panel.key}`);
        expect(plain.status).toBe(200);
        const body = plain.body.toString();
        expect(body).toContain(`id="${panel.key}-${VIN}"`);
        expect(body).toContain('<title>2024 Panelcar Coupe GT · Lotline</title>');

        for (const other of PANELS.filter((each) => each.key !== panel.key)) {
            expect(body).not.toContain(`id="${other.key}-${VIN}`);
        }

        expect(body).not.toContain('id="vehicle-title"');
    }
});
