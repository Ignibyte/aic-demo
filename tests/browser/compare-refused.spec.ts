// What the comparison refuses or leaves out (#33): four or more slugs answer 422 on the plain page and on the drawer
// alike; a slug that is sold, on hold, unknown, malformed or another car's key with another slug drops out quietly;
// with none left the page says so.

import { expect, test } from '@playwright/test';
import { DRAWER, compareCar, comparedTitles, compareHref, insertCompareCars, removeCompareCars } from './support/compare';
import { sql } from './support/inventory';
import { slugOf } from './support/storefront';
import { anchorPath, ready } from './support/tessera';
import { insertVehicle } from './support/vehicles';

let slugs: string[] = [];

test.beforeEach(() => {
    removeCompareCars();
    slugs = insertCompareCars(4);
});
test.afterEach(() => removeCompareCars());

// Chromium logs a document answered 422 as a failed resource, so this look holds the statuses and not the console.
test('four or more slugs answer 422, plainly and for the drawer', async ({ page }) => {
    const response = await page.goto(compareHref(slugs));
    expect(response?.status()).toBe(422);
    await expect(page.locator('#compare-title')).toHaveText('Compare up to three cars at a time.');
    await expect(page.locator('#compare-table')).toHaveCount(0);
    await ready(page);

    // As Tessera asks for the drawer: the drawer's fragment with the message and no table.
    const drawerPath = await anchorPath(page, DRAWER);
    expect(drawerPath).not.toBe('');
    const answer = await page.request.get(compareHref(slugs), {
        headers: {
            'Tessera-Request': 'visit',
            'Tessera-Protocol': '2',
            'Tessera-Layout': 'lotline-store',
            'Tessera-Targets': `${drawerPath} @${DRAWER}`,
        },
    });
    expect(answer.status()).toBe(422);
    expect(answer.headers()['content-type']).toMatch(/^text\/vnd\.tessera\+html/);
    const body = await answer.text();
    expect([...body.matchAll(/<template data-target="([^"]+)"/g)].map((match) => match[1])).toEqual([drawerPath]);
    expect(body).toContain('<p id="compare-drawer-message">Compare up to three cars at a time.</p>');
    expect(body).not.toContain('compare-table');

    // The same car named twice counts once, so three distinct slugs are compared.
    const twice = await page.goto(compareHref([slugs[0], slugs[1], slugs[0], slugs[2]]));
    expect(twice?.status()).toBe(200);
    await expect.poll(() => comparedTitles(page.locator('#compare-table'))).toEqual(['2023 Comparo C1', '2023 Comparo C2', '2023 Comparo C3']);
});

test('sold, held, unknown, malformed and mismatched slugs drop out quietly', async ({ page }) => {
    insertVehicle(compareCar(5, 'sold'));
    sql(`UPDATE vehicles SET status = 'on_hold' WHERE vin = '${compareCar(4).vin}'`);
    const sold = slugOf(compareCar(5).vin);
    const held = slugs[3];
    // The second car's year and key with another model: what CarFinder refuses, so the comparison does too.
    const mismatched = slugs[1].replace('-c2-', '-c9-');
    expect(mismatched).not.toBe(slugs[1]);

    const first = await page.goto(compareHref([sold, slugs[0], held]));
    expect(first?.status()).toBe(200);
    await expect.poll(() => comparedTitles(page.locator('#compare-table'))).toEqual(['2023 Comparo C1']);

    const second = await page.goto(compareHref(['nope', mismatched, slugs[2]]));
    expect(second?.status()).toBe(200);
    await expect.poll(() => comparedTitles(page.locator('#compare-table'))).toEqual(['2023 Comparo C3']);
    await expect(page.locator('#compare-title')).toHaveText('Compare cars');

    // With none left, the page says so and shows no table.
    const none = await page.goto(compareHref(['2023-comparo-c1-000000', 'nope']));
    expect(none?.status()).toBe(200);
    await expect(page.locator('#compare-title')).toHaveText('No car to compare.');
    await expect(page.locator('#compare-table')).toHaveCount(0);

    const empty = await page.goto('/compare');
    expect(empty?.status()).toBe(200);
    await expect(page.locator('#compare-title')).toHaveText('No car to compare.');
});
