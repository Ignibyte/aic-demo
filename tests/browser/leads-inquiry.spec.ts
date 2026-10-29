import { expect, test } from '@playwright/test';
import { literal, signIn, sql } from './support/inventory';
import { collectFormErrors } from './support/forms';
import { documentKept, markDocument } from './support/storefront';
import { firstCar, fillInquiry, inquiryThanks, lookEmail, nextSeller, removeLeadLooks, storedLeads } from './support/leads';

test.beforeEach(() => removeLeadLooks());
test.afterEach(() => removeLeadLooks());

test('a visitor asks about a car, is thanked in place, and staff find the lead in /app/leads', async ({ page }) => {
    const errors = collectFormErrors(page);
    const car = firstCar();
    const seller = nextSeller();
    const sellerName = sql(`SELECT name FROM users WHERE email = ${literal(seller)}`)[0] ?? 'Unassigned';
    const visitor = { name: `Inquiry Look ${Date.now()}`, email: lookEmail('inquiry'), phone: '', message: 'Is it still for sale, and does it tow?' };

    // Signed out, on the car's page: the question is answered in its panel, in the same document, and the URL stays.
    await page.goto(car.path);
    await markDocument(page);
    await fillInquiry(page, visitor);
    await page.locator('#inquiry-send').click();
    await expect(page.locator('#inquiry-thanks')).toHaveText(inquiryThanks(visitor));
    expect(await documentKept(page)).toBe(true);
    await expect(page).toHaveURL(new RegExp(`${car.path}$`));
    await expect(page.locator('#test-drive-form')).toBeVisible();
    await expect(page.locator('#inquiry-form')).toHaveCount(0);

    // Stored: the car, the kind, the question, open, and the salesperson whose turn it was.
    const [lead] = storedLeads(visitor.email);
    expect(storedLeads(visitor.email)).toHaveLength(1);
    expect(lead).toMatchObject({ kind: 'inquiry', vehicleId: car.id, message: visitor.message, seller, status: 'open', customerId: 0 });

    // Staff: the list shows it first, with its car and salesperson.
    await signIn(page);
    await page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Leads' }).click();
    await expect(page).toHaveURL(/\/app\/leads$/);
    const first = page.locator('#leads-table tbody tr').first();
    await expect(first).toContainText(visitor.name);
    await expect(first).toContainText(car.title);
    await expect(first).toContainText(sellerName);

    // Filtered by its kind and its salesperson it stays; by the other kind it goes.
    const row = page.locator('#leads-table tbody tr', { hasText: visitor.name });
    await page.locator('#filter-kind').selectOption('inquiry');
    if (seller !== '') {
        await page.locator('#filter-salesperson').selectOption(seller);
    }
    await page.locator('#leads-filters button[type="submit"]').click();
    await expect(page).toHaveURL(/kind=inquiry/);
    await expect(row).toHaveCount(1);
    await page.locator('#filter-kind').selectOption('test_drive');
    await page.locator('#leads-filters button[type="submit"]').click();
    await expect(page).toHaveURL(/kind=test_drive/);
    await expect(row).toHaveCount(0);

    // Its page shows it and the car.
    await page.goto(`/app/leads/${lead.id}`);
    await expect(page.locator('#lead-title')).toHaveText(visitor.name);
    await expect(page.locator('#lead-status')).toHaveText('Open');
    for (const detail of [visitor.message, car.title, car.vin]) {
        await expect(page.locator('dd', { hasText: detail })).toHaveCount(1);
    }
    await expect(page.locator('#lead-car')).toHaveAttribute('href', `/app/inventory/${car.vin}`);

    expect(errors).toEqual([]);
});
