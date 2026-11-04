// A deploy mid-visit (#33): every store page's stylesheet carries `data-track`, as AIC's tracked assets write it, so
// when a visit's answer names another stylesheet Tessera loads the page whole instead of swapping a fragment into a
// page styled by the old one.

import { expect, test } from '@playwright/test';
import { MAKE, insertCompareCars, removeCompareCars } from './support/compare';
import { documentKept, markDocument } from './support/storefront';
import { collectErrors, ready } from './support/tessera';

let slugs: string[] = [];

test.beforeEach(() => {
    removeCompareCars();
    slugs = insertCompareCars(2);
});
test.afterEach(() => removeCompareCars());

test('a visit whose answer names another stylesheet loads the page whole', async ({ page }) => {
    const errors = collectErrors(page);
    await page.goto(`/cars?make=${MAKE}`);
    await ready(page);
    const stylesheet = page.locator('head link[rel="stylesheet"]');
    await expect(stylesheet).toHaveAttribute('data-track', '');
    const href = await stylesheet.getAttribute('href');
    expect(href).toMatch(/\/aic\.css$/);
    await markDocument(page);

    // The next Tessera answer names the stylesheet a new deploy would: the same file at another fingerprint.
    let rewritten = 0;
    await page.route(/\/cars\//, async (route) => {
        if (route.request().headers()['tessera-request'] === undefined) {
            await route.continue();

            return;
        }

        const response = await route.fetch();
        const body = (await response.text()).replace(`href="${href}"`, `href="${href}?deploy=next"`);
        rewritten += body.includes('?deploy=next') ? 1 : 0;
        await route.fulfill({ response, body });
    });

    await page.locator(`#car-${slugs[0]}`).click();
    await expect(page).toHaveURL(new RegExp(`/cars/${slugs[0]}$`));
    await expect.poll(() => documentKept(page)).toBe(false);
    expect(rewritten).toBe(1);

    // The whole load is the real page, styled by the stylesheet it names.
    await ready(page);
    await expect(page.locator('head link[rel="stylesheet"]')).toHaveAttribute('href', href ?? '');
    await expect(page.locator('#car')).toBeVisible();
    expect(await page.locator('#compare-form').evaluate((form) => getComputedStyle(form).position)).toBe('fixed');
    expect(errors).toEqual([]);
});
