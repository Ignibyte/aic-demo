import { expect, test } from '@playwright/test';
import { collectFormErrors, pathOf } from './support/forms';
import { firstCar } from './support/leads';
import { sql } from './support/inventory';
import { SLUG } from './support/storefront';
import { EVENTS, box, carFor, dock, dockKept, insertMember, markDock, removeChatLooks, startChat } from './support/chat';

test.beforeEach(() => removeChatLooks());
test.afterEach(() => removeChatLooks());

test('moving around the storefront keeps the dock as the same node: open, its text kept, one stream connection', async ({ page }) => {
    const errors = collectFormErrors(page);
    const ann = insertMember('Permanent Ann');
    const car = carFor(ann);
    const [other] = sql(`SELECT ${SLUG} FROM vehicles WHERE status = 'in_stock' AND id <> ${firstCar().id} ORDER BY added_on DESC, vin LIMIT 1`);
    const streams: string[] = [];
    page.on('request', (request) => {
        if (pathOf(request.url()) === EVENTS) {
            streams.push(request.url());
        }
    });
    await startChat(page, car, { name: 'Chat Look Permanent', email: '', message: 'Keep me open' });
    await expect.poll(() => streams.length).toBe(1);
    await box(page).fill('Typed before the visits');
    await markDock(page);

    // The car list, another car and back, each through Tessera: the same dock node, open, with its text.
    await page.locator('header a[href="/cars"]').first().click();
    await expect(page).toHaveURL(/\/cars$/);
    await expect(dock(page)).toHaveAttribute('open', '');
    await page.locator(`a[href="/cars/${other}"]`).first().click();
    await expect(page).toHaveURL(new RegExp(`/cars/${other}$`));
    await page.goBack();
    await expect(page).toHaveURL(/\/cars$/);
    await page.goBack();
    await expect(page).toHaveURL(new RegExp(`${car.path}$`));
    expect(await dockKept(page)).toBe(true);
    await expect(dock(page)).toHaveAttribute('open', '');
    await expect(box(page)).toHaveValue('Typed before the visits');

    // The stream stayed on its one connection: no second request for it.
    expect(streams).toHaveLength(1);
    expect(errors).toEqual([]);
});
