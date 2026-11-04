// The style guide's Resilience panel (#34) links to each case page, each case page has the guide's frame and a way
// back, and nothing outside the guide links to a case page.

import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { CASES } from './support/resilience';
import { collectErrors } from './support/tessera';

test('the guide links to each case page, and each case page links back to the guide', async ({ page }) => {
    const errors = collectErrors(page);
    await page.goto('/theme');
    const panel = page.locator('section[aria-labelledby="resilience-heading"]');
    await expect(panel.locator('#resilience-heading')).toHaveText('Resilience');
    const links = panel.locator('a');
    await expect(links).toHaveCount(6);
    expect(await links.evaluateAll((anchors) => anchors.map((anchor) => anchor.getAttribute('href')))).toEqual(Object.values(CASES));

    for (const path of Object.values(CASES)) {
        await page.goto(path);
        await expect(page).toHaveTitle('Resilience · Lotline');
        await expect(page.locator('header h1')).toHaveText('Resilience');
        await expect(page.locator('#resilience-intro')).not.toBeEmpty();
        await expect(page.locator('main a', { hasText: 'Back to the style guide' })).toHaveAttribute('href', '/theme');
    }

    expect(errors).toEqual([]);
});

test('no product page links to a case page', async ({ page }) => {
    for (const path of ['/', '/cars']) {
        await page.goto(path);
        await expect(page.locator('a[href^="/theme/resilience/"]')).toHaveCount(0);
    }

    await signIn(page);
    await expect(page.locator('a[href^="/theme/resilience/"]')).toHaveCount(0);
    await page.goto('/app');
    await expect(page.locator('a[href^="/theme/resilience/"]')).toHaveCount(0);
});
