import { expect, test, type Page } from '@playwright/test';
import { signIn } from './support/dashboard';
import { BUYER_NAME, VINS, expectFeed, expectRefreshes, insertLiveLooks, insertLiveSale, login, recordPolls, removeLiveLooks, shownFeed } from './support/live';
import { today } from './support/sales';
import { collectErrors, ready } from './support/tessera';

test.beforeEach(() => {
    removeLiveLooks();
    insertLiveLooks();
});
test.afterEach(() => removeLiveLooks());

// Headless Chromium never hides a page: neither another page brought to the front nor a minimized window changes its
// `visibilityState`. So each document reports itself as this script says, and fires `visibilitychange` when told, as a
// tab sent to the background and back would; Tessera reads `visibilityState` at every tick.
function emulateVisibility(page: Page): Promise<void> {
    return page.addInitScript(() => {
        let hidden = false;
        Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => (hidden ? 'hidden' : 'visible') });
        Object.defineProperty(document, 'hidden', { configurable: true, get: () => hidden });
        Object.assign(window, {
            lotlineShown(shown: boolean): void {
                hidden = !shown;
                document.dispatchEvent(new Event('visibilitychange'));
            },
        });
    });
}

// Reports the page hidden or visible.
async function setShown(page: Page, shown: boolean): Promise<void> {
    await page.evaluate((value) => {
        (window as Window & { lotlineShown?: (next: boolean) => void }).lotlineShown?.(value);
    }, shown);
    expect(await page.evaluate(() => document.visibilityState)).toBe(shown ? 'visible' : 'hidden');
}

test('while the dashboard is hidden it sends no refresh, and once shown again the poll brings the sale', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectErrors(page);
    await emulateVisibility(page);

    await signIn(page);
    await ready(page);
    await expectFeed(page);
    const requests = await recordPolls(page);

    // Shown, the dashboard polls: a refresh within one tick and its request.
    await expect.poll(() => requests.length, { timeout: 8_000 }).toBeGreaterThan(0);
    await expectRefreshes(requests);

    // Hidden, it sends nothing through two ticks, and a sale put in Postgres meanwhile does not show.
    await setShown(page, false);
    await expectRefreshes(requests);
    const before = requests.length;
    const shown = await shownFeed(page);
    insertLiveSale(VINS[0], 19800);
    await page.waitForTimeout(11_000);
    expect(requests.length).toBe(before);
    expect(await shownFeed(page)).toEqual(shown);

    // Shown again, the next tick refreshes the tiles and the feed, and the sale appears without a reload.
    await setShown(page, true);
    const sale = [today(), '2023 Probe Hatch SE', BUYER_NAME, login, '$19,800'];
    await expect.poll(async () => (await shownFeed(page))[0], { timeout: 8_000 }).toEqual(sale);
    await expectFeed(page);
    expect(requests.length).toBeGreaterThan(before);
    await expectRefreshes(requests);
    expect(errors).toEqual([]);
});
