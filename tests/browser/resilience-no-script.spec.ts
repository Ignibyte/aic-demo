// The resilience cases with JavaScript off (#34): each form and link is a plain one, and the browser shows what the
// server answers: the refused form's 422 page, the redirects back, the full visit's 303, the protocol page as a page,
// and the gateway's 504 text.

import { expect, test, type Page, type Response } from '@playwright/test';
import { CASES } from './support/resilience';

test.use({ javaScriptEnabled: false });

// Presses the button and gives the document answer to its POST, once the page it leads to has loaded.
async function post(page: Page, path: string, button: string): Promise<Response> {
    const [response] = await Promise.all([
        page.waitForResponse((answer) => new URL(answer.url()).pathname === path && answer.request().method() === 'POST'),
        page.locator(button).click(),
    ]);
    await page.waitForLoadState('load');

    return response;
}

test('the refused form answers the 422 page with its summary and three errors', async ({ page }) => {
    await page.goto(CASES.refused);
    const response = await post(page, CASES.refused, '#vehicle-save');
    expect(response.status()).toBe(422);
    expect(response.request().headers()['tessera-request']).toBeUndefined();
    await expect(page.locator('#vehicle-error')).toHaveText('Fix the fields marked below.');
    await expect(page.locator('[aria-invalid="true"]')).toHaveCount(3);
});

test('Send, Take the feed down and the full visit go back to their pages', async ({ page }) => {
    await page.goto(CASES.expired);
    expect((await post(page, CASES.expired, '#expired-send-go')).status()).toBe(303);
    expect(new URL(page.url()).pathname).toBe(CASES.expired);

    await page.goto(CASES.feed);
    expect((await post(page, CASES.feed, '#feed-down-go')).status()).toBe(303);
    expect(new URL(page.url()).pathname).toBe(CASES.feed);
    await expect(page.locator('#feed-status')).toHaveText('The feed is down for the next 3 polls.');

    await page.goto(CASES.fullVisit);
    expect((await post(page, CASES.fullVisit, '#full-visit-go')).status()).toBe(303);
    expect(new URL(page.url()).pathname).toBe(CASES.fullVisit);
    await expect(page.locator('#full-visit-heading')).toHaveText('A full visit');
});

test('the protocol link loads its page, and the gateway form shows the 504 text', async ({ page }) => {
    await page.goto(CASES.protocol);
    await Promise.all([page.waitForEvent('load'), page.locator('main a', { hasText: 'Visit this page again' }).click()]);
    expect(new URL(page.url()).pathname).toBe(CASES.protocol);
    await expect(page.locator('#protocol-heading')).toHaveText('A protocol mismatch');

    await page.goto(CASES.gateway);
    await page.locator('#gateway-note').fill('Call the buyer back');
    const response = await post(page, CASES.gateway, '#gateway-save');
    expect(response.status()).toBe(504);
    await expect(page.locator('body')).toHaveText('The gateway timed out waiting for the app.');
});
