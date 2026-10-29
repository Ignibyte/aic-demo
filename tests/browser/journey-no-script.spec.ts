import { expect, type Page, type Response, test } from '@playwright/test';
import { inquiryThanks } from './support/leads';
import { FIXTURES, addPhoto } from './support/photos';
import { shownSlugs, slugOf } from './support/storefront';
import { insertVehicle } from './support/vehicles';
import {
    VISITOR, carPath, insertMember, journeyCar, removeJourneyLooks, restoreSalespeople, setAsideSalespeople, signInMember,
    storedLead,
} from './support/journey';

test.beforeEach(async () => removeJourneyLooks());
test.afterEach(async () => removeJourneyLooks());

// The document each step lands on is a whole page: an HTML answer with the theme's layout, never a Tessera fragment.
async function expectWholePage(page: Page, response: Response | null): Promise<void> {
    expect(response?.status()).toBe(200);
    expect(response?.headers()['content-type']).toBe('text/html; charset=utf-8');
    expect(response?.request().headers()['tessera-request']).toBeUndefined();
    await expect(page.locator('meta[name="tessera-layout"]')).toHaveAttribute('content', 'lotline-store');
}

test("with JavaScript off, a visitor filters the list, opens the car with its photos and asks about it, each a whole page", async ({ browser, page }) => {
    test.setTimeout(90_000);
    const car = journeyCar(2);
    const manager = insertMember('Noa Journey', 'sales_manager');
    const seller = insertMember('Rio Journey', 'salesperson');

    // The car is on the lot with two photos, added by a sales manager.
    insertVehicle(car);
    await signInMember(page, manager);
    await page.goto(`/app/inventory/${car.vin}`);
    await addPhoto(page, FIXTURES.jpeg);
    await expect(page.locator('#vehicle-photo-1')).toBeVisible();
    await addPhoto(page, FIXTURES.webp);
    await expect(page.locator('#vehicle-photo-2')).toBeVisible();

    const context = await browser.newContext({ javaScriptEnabled: false });
    const visitor = await context.newPage();

    // The filter form is a plain GET that answers the whole list.
    await expectWholePage(visitor, await visitor.goto('/cars'));
    await visitor.locator('#cars-make').selectOption(car.make);
    const filtered = visitor.waitForResponse((response) => response.url().includes(`/cars?make=${car.make}`));
    await visitor.locator('#car-filters').getByRole('button', { name: 'Filter' }).click();
    await expectWholePage(visitor, await filtered);
    await expect(visitor).toHaveURL(new RegExp(`/cars\\?make=${car.make}&year=&min_price=&max_price=&sort=$`));
    const path = carPath(car);
    expect(await shownSlugs(visitor)).toEqual([slugOf(car.vin)]);

    // The car's page shows its photos from AIC's public files, loaded.
    const opened = visitor.waitForResponse((response) => new URL(response.url()).pathname === path);
    await visitor.locator(`a[href="${path}"]`).first().click();
    await expectWholePage(visitor, await opened);
    const photos = visitor.locator('#car-photos img');
    await expect(photos).toHaveCount(2);
    await expect(photos.nth(0)).toHaveAttribute('src', /^\/files\/photos\/[0-9a-f]{64}\.jpg$/);
    await expect(photos.nth(1)).toHaveAttribute('src', /^\/files\/photos\/[0-9a-f]{64}\.webp$/);
    await expect.poll(() => photos.nth(0).evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);

    // The inquiry posts the whole form and answers the whole page, its thanks in place of the form.
    await visitor.locator('#inquiry-name').fill(VISITOR.name);
    await visitor.locator('#inquiry-email').fill(VISITOR.email);
    await visitor.locator('#inquiry-phone').fill(VISITOR.phone);
    await visitor.locator('#inquiry-message').fill(VISITOR.message);
    const setAside = setAsideSalespeople();

    try {
        const sent = visitor.waitForResponse((response) => response.request().method() === 'POST');
        await visitor.locator('#inquiry-send').click();
        await expectWholePage(visitor, await sent);
    } finally {
        restoreSalespeople(setAside);
    }

    await expect(visitor).toHaveURL(new RegExp(`${path}/inquiry$`));
    await expect(visitor.locator('#inquiry-thanks')).toHaveText(inquiryThanks(VISITOR));
    await expect(visitor.locator('#inquiry-form')).toHaveCount(0);
    expect(storedLead().seller).toBe(seller.email);
    await context.close();
});
