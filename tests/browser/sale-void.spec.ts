import { expect, test } from '@playwright/test';
import { BUYER, VINS, insertSale, insertVehicleAndBuyer, login, removeSaleLooks, signInAt, storedSales, vehicleStatus } from './support/sales';

const sale = { customer: BUYER, salesperson: login, price: 16900, soldOn: '2026-10-05' };

let saleId = 0;

test.beforeEach(() => {
    removeSaleLooks();
    insertVehicleAndBuyer(VINS.voided);
    saleId = insertSale(VINS.voided, sale);
});
test.afterEach(() => removeSaleLooks());

test('voiding asks first, then removes the sale and puts the vehicle back in stock', async ({ page }) => {
    await signInAt(page, `/app/sales/${saleId}`);
    await page.locator('#sale-void').click();
    await expect(page).toHaveURL(new RegExp(`/app/sales/${saleId}/void$`));
    await expect(page.getByRole('heading', { name: 'Void the sale of the 2023 Probe Hatch SE?' })).toBeVisible();
    await expect(page.getByText('Sold to Probe Buyer on 2026-10-05 for $16,900')).toBeVisible();

    // Keep it goes back to the sale, which stays.
    await page.getByRole('link', { name: 'Keep it' }).click();
    await expect(page).toHaveURL(new RegExp(`/app/sales/${saleId}$`));
    expect(storedSales(VINS.voided)).toEqual([{ id: saleId, ...sale }]);

    // Void removes it and lands on the vehicle, back in stock.
    await page.locator('#sale-void').click();
    await page.locator('#sale-void-confirm').click();
    await expect(page).toHaveURL(new RegExp(`/app/inventory/${VINS.voided}$`));
    await expect(page.locator('#vehicle-notice')).toHaveText('Voided the sale; the vehicle is back in stock.');
    await expect(page.locator('#vehicle-status')).toHaveText('In stock');
    await expect(page.locator('#vehicle-sale')).toHaveCount(0);
    await expect(page.locator('#vehicle-sell')).toBeVisible();
    expect(storedSales(VINS.voided)).toEqual([]);
    expect(vehicleStatus(VINS.voided)).toBe('in_stock');

    // The sale's page is gone.
    const answer = await page.goto(`/app/sales/${saleId}`);
    expect(answer?.status()).toBe(404);
    await expect(page.locator('#sale-missing')).toHaveText('No sale has that record. It may have been voided.');
});
