import { expect, test } from '@playwright/test';
import { count, money, signIn, tiles } from './support/dashboard';
import { BUYER, BUYER_NAME, VINS, expectFeed, fingerprint, insertLiveLooks, login, recordStreams, removeLiveLooks, shownFeed } from './support/live';
import { fillSale, today } from './support/sales';
import { collectErrors, expectMarksKept, markPage, ready } from './support/tessera';

test.beforeEach(() => {
    removeLiveLooks();
    insertLiveLooks();
});
test.afterEach(() => removeLiveLooks());

test('a sale recorded in one page appears on the dashboard open in another, without a reload', async ({ page }) => {
    const errors = collectErrors(page);
    const { connections, documents } = recordStreams(page);

    // The dashboard names its stream with the sales' fingerprint, and its feed lists the sales Postgres gives.
    await signIn(page);
    await ready(page);
    const seen = fingerprint();
    await expect(page.locator('#feed-heading')).toHaveText('Latest sales');
    await expect(page.locator('[data-stream]')).toHaveAttribute('data-stream', `/app/events?seen=${seen}`);
    await expectFeed(page);
    await expect(page.locator('#sales-feed').getByRole('link').first()).toHaveAttribute('href', /^\/app\/sales\/\d+$/);
    await expect.poll(() => connections).toEqual([{ path: `/app/events?seen=${seen}`, tesseraRequest: 'stream', lastEventId: '' }]);
    await markPage(page);
    documents.length = 0;

    // A second page of the same session records a sale through the form.
    const other = await page.context().newPage();
    await other.goto(`/app/sales/new?vehicle=${VINS[0]}`);
    await ready(other);
    await fillSale(other, { customer: BUYER, salesperson: login, price: 21500, soldOn: today() });
    await other.locator('#sale-save').click();
    await expect(other).toHaveURL(/\/app\/sales\/\d+$/);
    await expect(other.locator('#sale-notice')).toHaveText('Recorded the sale.');

    // The dashboard shows it first in the feed, and this month's tiles count it, with no reload.
    await expectFeed(page);
    expect((await shownFeed(page))[0]).toEqual([today(), '2023 Probe Hatch SE', BUYER_NAME, login, '$21,500']);
    const figures = tiles();
    await expect(page.locator('#tile-sales')).toHaveText(count(figures.sales));
    await expect(page.locator('#tile-revenue')).toHaveText(money(figures.revenue));
    await expect(page.locator('#tile-in-stock')).toHaveText(count(figures.inStock));
    await expectMarksKept(page);
    expect(documents).toEqual([]);
    expect(connections).toHaveLength(1);

    await other.close();
    expect(errors).toEqual([]);
});
