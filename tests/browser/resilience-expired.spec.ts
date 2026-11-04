// An expired page (#34): "Expire the token" renews the session's form token and answers 204, so the page keeps a token
// the session no longer holds. "Send" is then refused by the form guard with 419, and Tessera raises `tessera:expired`
// on the document and offers to reload. Dismissed, the page stays; accepted, it reloads with a fresh token.

import { expect, test } from '@playwright/test';
import { CASES, collectCaseErrors, heard, listenFor, markWindow, nextPost, windowKept } from './support/resilience';
import { ready } from './support/tessera';

const PROMPT = 'This page has expired. Reload it to continue?';

test('a write sent with a token the session no longer holds is answered 419, and Tessera offers to reload', async ({ page }) => {
    const errors = collectCaseErrors(page, [419]);
    await page.goto(CASES.expired);
    await ready(page);
    await listenFor(page, 'tessera:expired');
    await markWindow(page);
    const token = await page.locator('meta[name="tessera-csrf-token"]').getAttribute('content');

    // The lapse: one Tessera submit, answered 204, which leaves the page and its token as they were.
    const [lapse] = await Promise.all([nextPost(page, CASES.expired), page.locator('#expired-lapse-go').click()]);
    expect(lapse.status()).toBe(204);
    expect(lapse.request().headers()['tessera-request']).toBe('submit');
    await expect(page.locator('meta[name="tessera-csrf-token"]')).toHaveAttribute('content', token ?? '');
    expect(await windowKept(page)).toBe(true);

    // Send: 419, the event once, the prompt; dismissed, the same document.
    const prompts: string[] = [];
    page.once('dialog', async (dialog) => {
        prompts.push(dialog.message());
        await dialog.dismiss();
    });
    const [refused] = await Promise.all([nextPost(page, CASES.expired), page.locator('#expired-send-go').click()]);
    expect(refused.status()).toBe(419);
    await expect.poll(() => prompts).toEqual([PROMPT]);
    await expect(page.locator('#expired-watch-count')).toHaveText('1');
    expect(await heard(page, 'tessera:expired')).toBe(1);
    expect(await windowKept(page)).toBe(true);
    expect(new URL(page.url()).pathname).toBe(CASES.expired);

    // Send again and accept: the page reloads, a new document with a fresh token, and Send then goes through.
    page.once('dialog', async (dialog) => {
        prompts.push(dialog.message());
        await dialog.accept();
    });
    await Promise.all([page.waitForEvent('load'), page.locator('#expired-send-go').click()]);
    expect(prompts).toEqual([PROMPT, PROMPT]);
    expect(await windowKept(page)).toBe(false);
    await expect(page.locator('#expired-watch-count')).toHaveText('0');
    await expect(page.locator('meta[name="tessera-csrf-token"]')).not.toHaveAttribute('content', token ?? '');
    const [sent] = await Promise.all([nextPost(page, CASES.expired), page.locator('#expired-send-go').click()]);
    expect(sent.status()).toBe(200);
    expect(sent.headers()['tessera-location']).toBe(CASES.expired);
    await expect(page.locator('#expired-send-go')).toBeVisible();

    expect(errors).toEqual([]);
});
