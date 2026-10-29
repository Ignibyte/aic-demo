import { expect, type Page, test } from '@playwright/test';
import { sql } from './support/inventory';
import { slugOf } from './support/storefront';

// Opens a path, and holds that it answers 404 with the public look and its heading.
async function expectMissing(page: Page, path: string): Promise<void> {
    const response = await page.goto(path);
    expect(response?.status()).toBe(404);
    await expect(page).toHaveTitle('Car not found · Lotline');
    await expect(page.locator('#store-header')).toContainText('Ridgeway Motors');
    await expect(page.locator('#store-footer')).toBeVisible();
    await expect(page.locator('h1#car-missing')).toHaveText('Car not found');
    await expect(page.locator('[data-region="sidebar"]')).toHaveCount(0);
}

test('a sold car, one on hold and an unknown one answer 404 in the public look', async ({ page }) => {
    const [sold] = sql("SELECT vin FROM vehicles WHERE status = 'sold' ORDER BY vin LIMIT 1");
    const [held] = sql("SELECT vin FROM vehicles WHERE status = 'on_hold' ORDER BY vin LIMIT 1");
    const soldSlug = slugOf(sold);
    const heldSlug = slugOf(held);

    await expectMissing(page, `/cars/${soldSlug}`);
    await expectMissing(page, `/cars/${heldSlug}`);
    await expectMissing(page, '/cars/2023-volkswagen-tiguan-000000');
    await expectMissing(page, '/cars/nope');

    // Neither is in the list, nor answers a photo.
    await page.goto('/cars');
    await expect(page.locator(`#car-${soldSlug}`)).toHaveCount(0);
    await expect(page.locator(`#car-${heldSlug}`)).toHaveCount(0);
    expect((await page.request.get(`/cars/${soldSlug}/photos/1`)).status()).toBe(404);

    // The link back goes to the list.
    await expectMissing(page, `/cars/${soldSlug}`);
    await page.getByRole('link', { name: 'See every car for sale' }).click();
    await expect(page).toHaveURL(/\/cars$/);
});
