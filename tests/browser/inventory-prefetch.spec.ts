// A row's link to its vehicle prefetches the vehicle's page (#31, #43). The link carries `data-prefetch="hover"`:
// resting the pointer on it sends the visit early, and so does a keyboard focus (Tessera 0.4.4). The vehicle page
// answers `private, no-cache` (AIC #254), so Tessera takes that answer for the click or Enter that follows, and the page
// paints from one request with its panels as skeletons. A press alone prefetches as before, and since Tessera 0.4.4 a
// press drops a hover's pending timer, so no second prefetch follows a quick click. The row's edit cells keep prefetch
// off.

import { expect, test, type Locator, type Page } from '@playwright/test';
import { signIn } from './support/inventory';
import {
    exchangesTo, expectPanelsArrived, insertPanelVehicle, recordExchanges, removePanelLooks, skeleton, vin, type Exchange,
} from './support/panels';
import { fetchInPage } from './support/photos';
import { collectErrors, ready } from './support/tessera';

const VIN = vin('0LTPNPFT2SR31601');
const PATH = `/app/inventory/${VIN}`;
const LIST = `/app/inventory?q=${VIN}`;

test.beforeEach(() => {
    removePanelLooks();
    insertPanelVehicle({ vin: VIN, listPrice: 30000 });
});
test.afterEach(() => removePanelLooks());

// Opens the list and finds the row's VIN link, holding the row's prefetch attributes; then records, from now on, each
// Tessera request for the vehicle's page as it is sent and each exchange as it is answered.
async function openList(page: Page): Promise<{ link: Locator; sent: string[]; exchanges: Exchange[] }> {
    await signIn(page);
    await page.goto(LIST);
    await ready(page);

    const row = page.locator(`[data-t-anchor="vehicle-${VIN}"]`);
    const link = row.getByRole('link', { name: VIN, exact: true });
    await expect(link).toHaveAttribute('data-prefetch', 'hover');
    await expect(row.getByRole('link', { name: '$30,000' })).toHaveAttribute('data-prefetch', 'off');
    await expect(row.getByRole('link', { name: 'In stock' })).toHaveAttribute('data-prefetch', 'off');

    const exchanges = recordExchanges(page);
    const sent: string[] = [];
    page.on('request', (request) => {
        if (new URL(request.url()).pathname === PATH && request.headers()['tessera-request'] !== undefined) {
            sent.push(request.headers()['tessera-request']);
        }
    });

    return { link, sent, exchanges };
}

// The vehicle's page painted from one visit, answered 200 with `cacheControl`, and its panels arrived; no second
// request for it was sent.
async function expectPaintedFromOne(page: Page, sent: string[], exchanges: Exchange[], cacheControl: string): Promise<void> {
    await expect(page.locator('#vehicle-title')).toBeVisible();
    expect(new URL(page.url()).pathname).toBe(PATH);
    await expect(skeleton(page, 'price-history', VIN).or(page.locator(`section#price-history-${VIN}`))).toHaveCount(1);
    await expectPanelsArrived(page, VIN);
    await expect.poll(() => exchangesTo(exchanges, PATH)).toHaveLength(1);

    const [visit] = exchangesTo(exchanges, PATH);
    expect(visit.kind).toBe('visit');
    expect(visit.method).toBe('GET');
    expect(visit.status).toBe(200);
    expect(visit.cacheControl).toBe(cacheControl);
    expect(sent).toEqual(['visit']);
}

test('the vehicle page alone answers private, no-cache; its 404, its panels and the list stay no-store', async ({ page }) => {
    await signIn(page);
    await page.goto(LIST);
    await ready(page);

    // Asked from the page, so the session's cookie goes with it on every site; each answer is the page itself.
    const answers: [string, number, string][] = [
        [PATH, 200, 'private, no-cache'],
        [`/app/inventory/${vin('0LTPNPFT2SR31699')}`, 404, 'private, no-store'],
        [`${PATH}/price-history`, 200, 'private, no-store'],
        [LIST, 200, 'private, no-store'],
    ];

    for (const [path, status, cacheControl] of answers) {
        const answer = await fetchInPage(page, path);
        expect(answer.status, path).toBe(status);
        expect(answer.headers['cache-control'], path).toBe(cacheControl);
    }
});

test('pressing the VIN link prefetches the vehicle page, and the click paints from that one request', async ({ page }) => {
    const errors = collectErrors(page);
    const { link, sent, exchanges } = await openList(page);

    // Dispatched, so no pointer rests on the link: the press alone.
    await link.dispatchEvent('mousedown', { button: 0 });
    await expect.poll(() => sent).toEqual(['visit']);
    await link.dispatchEvent('click', { button: 0 });
    await expectPaintedFromOne(page, sent, exchanges, 'private, no-cache');
    expect(errors).toEqual([]);
});

test('resting the pointer on the VIN link prefetches the page, and the click paints from that answer', async ({ page }) => {
    const errors = collectErrors(page);
    const { link, sent, exchanges } = await openList(page);

    // The hover sends the visit before any press; its answer says `no-cache`, so the click takes it.
    await link.hover();
    await expect.poll(() => sent).toEqual(['visit']);
    await expect.poll(() => exchangesTo(exchanges, PATH)).toHaveLength(1);
    expect(page.url()).toContain(LIST);
    await link.click();
    await expectPaintedFromOne(page, sent, exchanges, 'private, no-cache');
    expect(errors).toEqual([]);
});

test('a keyboard focus on the VIN link prefetches the page, and Enter paints from that answer', async ({ page }) => {
    const errors = collectErrors(page);
    const { link, sent, exchanges } = await openList(page);

    // A key down first, so Tessera reads the focus as the keyboard's, as a Tab to the link would be.
    await page.keyboard.press('Shift');
    await link.focus();
    await expect.poll(() => sent).toEqual(['visit']);
    await expect.poll(() => exchangesTo(exchanges, PATH)).toHaveLength(1);
    expect(page.url()).toContain(LIST);
    await page.keyboard.press('Enter');
    await expectPaintedFromOne(page, sent, exchanges, 'private, no-cache');
    expect(errors).toEqual([]);
});
