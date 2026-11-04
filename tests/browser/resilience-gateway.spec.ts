// A write whose answer never comes (#34): the gateway in front of the app gives up and answers 504 in plain text, at
// once. Tessera cannot use that answer to a write, so it raises `tessera:indeterminate` on the form and puts its alert
// before it, in the same document.

import { expect, test } from '@playwright/test';
import { CASES, collectCaseErrors, heard, listenFor, markWindow, nextPost, windowKept } from './support/resilience';
import { ready } from './support/tessera';

const ALERT = 'Your submission was sent, but no answer came back. It may have been saved, so check before sending it again.';

test("a write the gateway answers 504 in plain text raises tessera:indeterminate and Tessera's alert", async ({ page }) => {
    const errors = collectCaseErrors(page, [504]);
    await page.goto(CASES.gateway);
    await ready(page);
    await listenFor(page, 'tessera:indeterminate');
    await markWindow(page);
    await page.locator('#gateway-note').fill('Call the buyer back');

    const [answer] = await Promise.all([nextPost(page, CASES.gateway), page.locator('#gateway-save').click()]);
    expect(answer.status()).toBe(504);
    expect(answer.request().headers()['tessera-request']).toBe('submit');
    expect(answer.headers()['content-type']).toBe('text/plain; charset=utf-8');

    const alert = page.locator('[data-tessera-indeterminate]');
    await expect(alert).toHaveText(ALERT);
    await expect(alert).toHaveAttribute('role', 'alert');
    expect(await page.locator('#gateway-form').evaluate((form) => form.previousElementSibling?.hasAttribute('data-tessera-indeterminate'))).toBe(true);
    await expect(page.locator('#gateway-watch-count')).toHaveText('1');
    expect(await heard(page, 'tessera:indeterminate')).toBe(1);
    expect(await windowKept(page)).toBe(true);
    expect(new URL(page.url()).pathname).toBe(CASES.gateway);

    expect(errors).toEqual([]);
});
