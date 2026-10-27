import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { LIVE } from './support/live';
import { anchorPath, collectErrors, expectTableVisit, ready, recordListRequests, RESULTS } from './support/tessera';

test('the inventory results and the dashboard live part are anchored, and what refreshes them names the anchor', async ({ page }) => {
    const errors = collectErrors(page);
    await signIn(page);
    await ready(page);

    // One element carries the results' anchor: their root, around the table and the pages, marked as a component.
    const results = page.locator(`[data-t-anchor="${RESULTS}"]`);
    await expect(results).toHaveCount(1);
    await expect(results).toHaveAttribute('data-t-c', /.+/);
    await expect(results.locator('#vehicles-table')).toHaveCount(1);
    await expect(results.locator('#vehicles-pages')).toHaveCount(1);
    expect(await anchorPath(page, RESULTS)).toMatch(/^content\/.+/);

    // The filter form, every sortable head and every page link target the anchor, never a path.
    await expect(page.locator('#inventory-filters')).toHaveAttribute('data-target', `@${RESULTS}`);
    const targets = await page.locator('#vehicles-table thead a, #vehicles-pages a')
        .evaluateAll((links) => links.map((link) => link.getAttribute('data-target')));
    expect(targets.length).toBeGreaterThan(1);
    expect(new Set(targets)).toEqual(new Set([`@${RESULTS}`]));

    // A sort sends the results' path, which Tessera finds from the anchor, and gets that fragment alone.
    const requests = await recordListRequests(page);
    const price = page.locator('#vehicles-table thead th', { hasText: 'Price' }).getByRole('link');
    const href = (await price.getAttribute('href')) ?? '';
    await price.click();
    await expectTableVisit(page, requests, href);
    await expect(page).toHaveURL(href);

    // On the dashboard one element carries the live part's anchor, the one Tessera polls.
    await page.goto('/app');
    await ready(page);
    const live = page.locator(`[data-t-anchor="${LIVE}"]`);
    await expect(live).toHaveCount(1);
    await expect(live).toHaveAttribute('data-t-c', /.+/);
    await expect(live).toHaveAttribute('data-poll__5s', '/app');
    await expect(live.locator('#dashboard-tiles')).toHaveCount(1);
    await expect(live.locator('#sales-feed')).toHaveCount(1);
    expect(await anchorPath(page, LIVE)).toMatch(/^content\/.+/);

    expect(errors).toEqual([]);
});
