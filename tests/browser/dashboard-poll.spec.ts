import { expect, test } from '@playwright/test';
import { count, money, signIn, tiles } from './support/dashboard';
import { BUYER, BUYER_NAME, VINS, expectFeed, expectRefreshes, insertLiveLooks, login, recordPolls, removeLiveLooks, shownFeed } from './support/live';
import { fillSale, today } from './support/sales';
import { collectErrors, expectMarksKept, markPage, ready } from './support/tessera';

test.beforeEach(() => {
    removeLiveLooks();
    insertLiveLooks();
});
test.afterEach(() => removeLiveLooks());

test('a sale recorded in one page appears on the dashboard open in another within seconds, by poll', async ({ page }) => {
    const errors = collectErrors(page);

    // The tiles and the feed sit in one element that Tessera polls from the dashboard itself; there is no stream.
    await signIn(page);
    await ready(page);
    await expect(page.locator('[data-poll__5s]')).toHaveAttribute('data-poll__5s', '/app');
    await expect(page.locator('[data-poll__5s] #dashboard-tiles')).toHaveCount(1);
    await expect(page.locator('[data-poll__5s] #sales-feed')).toHaveCount(1);
    await expect(page.locator('[data-stream]')).toHaveCount(0);
    await expectFeed(page);
    await markPage(page);
    const requests = await recordPolls(page);

    // A second page of the same session records a sale through the form.
    const other = await page.context().newPage();
    await other.goto(`/app/sales/new?vehicle=${VINS[0]}`);
    await ready(other);
    await fillSale(other, { customer: BUYER, salesperson: login, price: 21500, soldOn: today() });
    await other.locator('#sale-save').click();
    await expect(other).toHaveURL(/\/app\/sales\/\d+$/);
    await expect(other.locator('#sale-notice')).toHaveText('Recorded the sale.');

    // Within one five-second tick and its request, the dashboard shows it first in the feed and counts it in the tiles.
    const sale = [today(), '2023 Probe Hatch SE', BUYER_NAME, login, '$21,500'];
    await expect.poll(async () => (await shownFeed(page))[0], { timeout: 8_000 }).toEqual(sale);
    await expectFeed(page);
    const figures = tiles();
    await expect(page.locator('#tile-sales')).toHaveText(count(figures.sales));
    await expect(page.locator('#tile-revenue')).toHaveText(money(figures.revenue));
    await expect(page.locator('#tile-in-stock')).toHaveText(count(figures.inStock));

    // No reload: the same document, and every request since load a finished refresh of the live part alone.
    await expectMarksKept(page);
    expect(requests.length).toBeGreaterThan(0);
    await expectRefreshes(requests);
    expect(requests.filter((request) => request.path.startsWith('/app/events'))).toEqual([]);

    await other.close();
    expect(errors).toEqual([]);
});
