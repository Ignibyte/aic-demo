// A full visit (#34): the form's answer carries `Tessera-Full-Visit`, and Tessera hands the address to the browser,
// which loads the page whole: a new document.

import { expect, test } from '@playwright/test';
import { CASES, markWindow, nextPost, windowKept } from './support/resilience';
import { collectErrors, ready } from './support/tessera';

test('an answer carrying Tessera-Full-Visit loads the page whole', async ({ page }) => {
    const errors = collectErrors(page);
    await page.goto(CASES.fullVisit);
    await ready(page);
    await markWindow(page);

    const [answer] = await Promise.all([
        nextPost(page, CASES.fullVisit),
        page.waitForEvent('load'),
        page.locator('#full-visit-go').click(),
    ]);
    expect(answer.status()).toBe(200);
    expect(answer.request().headers()['tessera-request']).toBe('submit');
    expect(answer.headers()['tessera-full-visit']).toBe(CASES.fullVisit);
    expect(await windowKept(page)).toBe(false);
    expect(new URL(page.url()).pathname).toBe(CASES.fullVisit);
    await expect(page.locator('#full-visit-heading')).toHaveText('A full visit');

    expect(errors).toEqual([]);
});
