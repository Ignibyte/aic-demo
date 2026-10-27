import { expect, test } from '@playwright/test';
import {
    BUYER,
    VINS,
    fillSale,
    insertVehicleAndBuyer,
    login,
    recordSale,
    removeSaleLooks,
    signInAt,
    storedSales,
    today,
    vehicleStatus,
} from './support/sales';

const sale = { customer: BUYER, salesperson: login, price: 17400, soldOn: '2026-10-01' };

let buyerId = 0;

test.beforeEach(() => {
    removeSaleLooks();
    buyerId = insertVehicleAndBuyer(VINS.recorded).buyerId;
});
test.afterEach(() => removeSaleLooks());

test('a sale recorded from a vehicle marks it sold and shows on the vehicle and the customer', async ({ page }) => {
    // The vehicle's page offers the sale, and the form starts with that vehicle, its list price and today.
    await signInAt(page, `/app/inventory/${VINS.recorded}`);
    await page.locator('#vehicle-sell').click();
    await expect(page).toHaveURL(new RegExp(`/app/sales/new\\?vehicle=${VINS.recorded}$`));
    await expect(page.locator('#sale-vehicle')).toHaveValue(VINS.recorded);
    await expect(page.locator('#sale-price')).toHaveValue('17900');
    await expect(page.locator('#sale-sold_on')).toHaveValue(today());

    await fillSale(page, sale);
    await recordSale(page);

    // Postgres holds the one sale, and the vehicle is sold.
    const stored = storedSales(VINS.recorded);
    expect(stored).toEqual([{ id: expect.any(Number), ...sale }]);
    expect(vehicleStatus(VINS.recorded)).toBe('sold');
    const salePath = `/app/sales/${stored[0]?.id}`;
    await expect(page).toHaveURL(new RegExp(`${salePath}$`));
    await expect(page.locator('#sale-title')).toHaveText('Sale of the 2023 Probe Hatch SE');
    await expect(page.locator('dd')).toHaveText([VINS.recorded, 'Probe Buyer', login, '$17,400', '2026-10-01']);

    // The vehicle's page shows the sale and no longer offers one.
    await page.locator('#sale-vehicle-link').click();
    await expect(page.locator('#vehicle-status')).toHaveText('Sold');
    await expect(page.locator('#vehicle-sell')).toHaveCount(0);
    await expect(page.locator('#vehicle-sale tbody tr td')).toHaveText(['2026-10-01', 'Probe Buyer', login, '$17,400']);
    await expect(page.locator('#vehicle-sale').getByRole('link', { name: '2026-10-01' })).toHaveAttribute('href', salePath);
    await expect(page.locator('#vehicle-sale').getByRole('link', { name: 'Probe Buyer' })).toHaveAttribute('href', `/app/customers/${buyerId}`);

    // The customer's page lists the purchase and counts it.
    await page.goto(`/app/customers/${buyerId}`);
    await expect(page.locator('#customer-badge')).toHaveText('1 purchase');
    await expect(page.locator('#customer-purchases tbody tr td')).toHaveText([VINS.recorded, '2023 Probe Hatch SE', '2026-10-01', '$17,400']);
    await expect(page.locator('#customer-purchases').getByRole('link', { name: VINS.recorded })).toHaveAttribute('href', `/app/inventory/${VINS.recorded}`);

    // A customer who bought stays: the delete page says so and offers no form.
    await page.locator('#customer-delete').click();
    await expect(page.locator('#customer-bought')).toHaveText('This customer has bought from Ridgeway Motors, so the record stays.');
    await expect(page.locator('#customer-delete-form')).toHaveCount(0);
});

test.describe('without JavaScript', () => {
    // Plain posts: the delete posted anyway must go as the browser sends a form.
    test.use({ javaScriptEnabled: false });

    test("a delete posted anyway for a customer who bought is refused, and the customer stays", async ({ page }) => {
        await signInAt(page, `/app/sales/new?vehicle=${VINS.recorded}`);
        await fillSale(page, sale);
        await page.locator('#sale-save').click();
        await expect(page.locator('#sale-notice')).toHaveText('Recorded the sale.');

        // The edit form already carries a token and an envelope sealing this customer's path, which a delete accepts.
        await page.goto(`/app/customers/${buyerId}/edit`);
        await page.locator('#customer-form').evaluate((form, action) => form.setAttribute('action', action), `/app/customers/${buyerId}/delete`);
        const answer = page.waitForResponse((response) => response.request().method() === 'POST');
        await page.locator('#customer-save').click();

        expect((await answer).status()).toBe(422);
        await expect(page.locator('#customer-bought')).toBeVisible();
        expect(storedSales(VINS.recorded)).toHaveLength(1);
        await page.goto(`/app/customers/${buyerId}`);
        await expect(page.locator('#customer-title')).toHaveText('Probe Buyer');
    });
});
