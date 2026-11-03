// Lotline's one script as a page script (AIC #239): `themes/lotline/assets/helpers.page.js` loads on every page through
// the layout's tracked assets, after the pinned Tessera script, deferred, by its fingerprinted URL with `data-track`,
// and no layout writes a script of its own. Both layouts, the staff one and the store's, load it once, and it starts
// Tessera with `money`, which the payment calculator writes its figures with.

import { expect, test, type Page } from '@playwright/test';
import { calculator } from './support/finance';
import { signIn } from './support/inventory';
import { firstCar } from './support/leads';

// Holds the page's scripts to Tessera's, then the page script once, each deferred and tracked, and no other.
async function expectPageScript(page: Page): Promise<void> {
    const scripts = await page.locator('script').evaluateAll((elements) => elements.map((element) => ({
        src: element.getAttribute('src') ?? '',
        defer: element.hasAttribute('defer'),
        tracked: element.hasAttribute('data-track'),
        inHead: element.parentElement === document.head,
    })));

    expect(scripts).toHaveLength(2);
    const [tessera, helpers] = scripts;
    expect(tessera.src).toMatch(/\/tessera\.csp\.iife\.js$/);
    expect(helpers.src).toMatch(/^\/assets\/[^/]+\/themes\/lotline\/helpers\.page\.js$/);

    for (const script of scripts) {
        expect(script).toMatchObject({ defer: true, tracked: true, inHead: true });
    }

    const answer = await page.request.get(helpers.src);
    expect(answer.status()).toBe(200);
    expect(await answer.text()).toContain('window.Tessera.start(');
}

test('the store layout loads the page script after Tessera, and Tessera starts with money', async ({ page }) => {
    await page.goto(firstCar().path);
    await expectPageScript(page);

    // The calculator shows only once Tessera has started, and its figures follow the down payment through `money`.
    const shown = calculator(page);
    await expect(shown.body).toBeVisible();
    const before = await shown.payment.textContent();
    await shown.down.fill('0');
    await expect(shown.payment).not.toHaveText(before ?? '');
    await expect(shown.payment).toHaveText(/^\$[\d,]+\.\d{2}$/);
});

test('the staff layout loads the same page script after Tessera', async ({ page }) => {
    await signIn(page);
    await expectPageScript(page);
    await page.goto('/app');
    await expectPageScript(page);
});
