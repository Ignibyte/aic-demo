import { expect, test, type Page } from '@playwright/test';
import { signIn } from './support/dashboard';
import { BUYER_NAME, LIVE, VINS, expectFeed, expectRefreshes, insertLiveLooks, insertLiveSale, login, recordPolls, removeLiveLooks, shownFeed } from './support/live';
import { today } from './support/sales';
import { collectErrors, expectMarksKept, markPage, ready } from './support/tessera';

test.beforeEach(() => {
    removeLiveLooks();
    insertLiveLooks();
});
test.afterEach(() => removeLiveLooks());

// The three charts' markup, each found by its anchor (AIC #188), in the page's order.
function chartsMarkup(page: Page): Promise<string[]> {
    return page.locator('[data-t-anchor="dashboard-sales"], [data-t-anchor="dashboard-makes"], [data-t-anchor="dashboard-salespeople"]')
        .evaluateAll((charts) => charts.map((chart) => chart.outerHTML));
}

test('an idle dashboard poll answers 204 and keeps the page; a sale\'s poll refreshes the live part alone and the charts stay', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectErrors(page);

    // The live part carries its sealed version, which Tessera sends back with every refresh.
    await signIn(page);
    await ready(page);
    await expectFeed(page);
    await expect(page.locator(`[data-t-anchor="${LIVE}"]`)).toHaveAttribute('data-t-props', /.+/);
    await expect(page.locator('[data-t-anchor^="dashboard-"]')).toHaveCount(4);
    await markPage(page);
    const feed = await shownFeed(page);
    const tiles = await page.locator('#dashboard-tiles').innerText();
    const charts = await chartsMarkup(page);
    expect(charts).toHaveLength(3);
    const requests = await recordPolls(page);

    // Idle, each poll answers 204 with no template, and the feed, the tiles and the document stay as they were.
    await expect.poll(() => requests.filter((request) => request.finished).length, { timeout: 15_000 }).toBeGreaterThanOrEqual(2);
    await expectRefreshes(page, requests);
    expect(requests.map((request) => request.status)).toEqual(requests.map(() => 204));
    expect(await shownFeed(page)).toEqual(feed);
    expect(await page.locator('#dashboard-tiles').innerText()).toBe(tiles);
    await expectMarksKept(page);

    // A sale lands in Postgres: the next poll answers 200 with the live part alone, and the feed shows the sale.
    const before = requests.length;
    insertLiveSale(VINS[0], 23400);
    const sale = [today(), '2023 Probe Hatch SE', BUYER_NAME, login, '$23,400'];
    await expect.poll(async () => (await shownFeed(page))[0], { timeout: 8_000 }).toEqual(sale);
    await expectFeed(page);
    await expectRefreshes(page, requests);
    expect(requests.slice(before).filter((request) => request.status === 200).length).toBeGreaterThan(0);

    // The charts were not part of it: their markup is what it was, though the sales chart would now count the sale.
    expect(await chartsMarkup(page)).toEqual(charts);

    // The refresh sealed the new version, so the poll after it is idle again.
    await expect.poll(() => {
        const since = requests.slice(before);
        const refreshed = since.findIndex((request) => request.status === 200);

        return refreshed >= 0 && since.slice(refreshed + 1).some((request) => request.finished && request.status === 204);
    }, { timeout: 15_000 }).toBe(true);
    await expectRefreshes(page, requests);
    await expectMarksKept(page);
    expect(errors).toEqual([]);
});
