// A protocol mismatch (#34): every Tessera request to the protocol page is answered 400 with `Tessera-Protocol: 1`, as
// a server that speaks only protocol 1 answers, and Tessera loads the page whole instead: a new document.

import { expect, test, type Page } from '@playwright/test';
import { CASES, collectCaseErrors, markWindow, windowKept } from './support/resilience';
import { ready } from './support/tessera';

// One Tessera request to the protocol page and what it was answered with.
interface Refusal {
    status: number;
    protocol: string;
}

// Records every Tessera request to the protocol page with its answer.
function recordRefusals(page: Page): Refusal[] {
    const refusals: Refusal[] = [];
    page.on('response', (response) => {
        if (new URL(response.url()).pathname === CASES.protocol && response.request().headers()['tessera-request'] !== undefined) {
            refusals.push({ status: response.status(), protocol: response.headers()['tessera-protocol'] ?? '' });
        }
    });

    return refusals;
}

test('a 400 naming protocol 1 ends in a full load of the page', async ({ page }) => {
    const errors = collectCaseErrors(page, [400]);
    await page.goto(CASES.protocol);
    await ready(page);
    await markWindow(page);
    const refusals = recordRefusals(page);

    await Promise.all([page.waitForEvent('load'), page.locator('main a', { hasText: 'Visit this page again' }).click()]);
    expect(refusals.length).toBeGreaterThan(0);
    expect(refusals.every((refusal) => refusal.status === 400 && refusal.protocol === '1')).toBe(true);
    expect(await windowKept(page)).toBe(false);
    expect(new URL(page.url()).pathname).toBe(CASES.protocol);
    await expect(page.locator('#protocol-heading')).toHaveText('A protocol mismatch');

    // From the guide, its link does the same.
    await page.goto('/theme');
    await ready(page);
    await markWindow(page);
    refusals.length = 0;
    await Promise.all([page.waitForEvent('load'), page.locator('a', { hasText: 'A protocol mismatch (400)' }).click()]);
    expect(refusals.length).toBeGreaterThan(0);
    expect(await windowKept(page)).toBe(false);
    expect(new URL(page.url()).pathname).toBe(CASES.protocol);
    await expect(page.locator('#protocol-heading')).toHaveText('A protocol mismatch');

    expect(errors).toEqual([]);
});
