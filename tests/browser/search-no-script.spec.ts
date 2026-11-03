// Search as you type with JavaScript off (#27): nothing runs the box's debounced submit, so each list's search is the
// plain GET form it always was, with its Filter button, and submitting it loads the searched list as a whole document.
// Typing alone sends nothing.

import { expect, test } from '@playwright/test';
import { countWhere as customerCount, pageNames, shownNames, signInAt } from './support/customers';
import { countWhere, fillSignIn, pageVins, shownVins, summary } from './support/inventory';
import { customerSearch, CUSTOMER_COLUMNS, inventorySearch, PAUSE, searched, VEHICLE_COLUMNS } from './support/search';

test.use({ javaScriptEnabled: false });

test('with JavaScript off both searches are plain GET forms with a Filter button', async ({ page }) => {
    const documents: string[] = [];
    page.on('request', (request) => {
        if (request.resourceType() === 'document') {
            documents.push(new URL(request.url()).pathname + new URL(request.url()).search);
        }
    });

    await page.goto('/sign-in?next=%2Fapp%2Finventory');
    await fillSignIn(page);

    // The inventory: typing and a pause send nothing; the button loads the searched list.
    const box = page.locator('#filter-q');
    await expect(page.locator('#inventory-filters')).toHaveAttribute('method', 'get');
    documents.length = 0;
    await box.pressSequentially('cr', { delay: PAUSE });
    await page.waitForTimeout(PAUSE);
    expect(documents).toEqual([]);
    await page.locator('#inventory-filters').getByRole('button', { name: 'Filter' }).click();
    await expect(page).toHaveURL(inventorySearch('cr'));
    expect(documents).toEqual([inventorySearch('cr')]);
    const vehicles = searched(VEHICLE_COLUMNS, 'cr');
    await expect(page.locator('#vehicles-table-summary')).toHaveText(summary(countWhere(vehicles)));
    await expect(shownVins(page)).toHaveText(pageVins(vehicles, 'added_on DESC'));
    await expect(box).toHaveValue('cr');

    // The customers: the same.
    await signInAt(page, '/app/customers');
    await expect(page.locator('#customers-filters')).toHaveAttribute('method', 'get');
    documents.length = 0;
    await box.pressSequentially('ada', { delay: PAUSE });
    await page.waitForTimeout(PAUSE);
    expect(documents).toEqual([]);
    await page.locator('#customers-filters').getByRole('button', { name: 'Filter' }).click();
    await expect(page).toHaveURL(customerSearch('ada'));
    expect(documents).toEqual([customerSearch('ada')]);
    const customers = searched(CUSTOMER_COLUMNS, 'ada');
    await expect(shownNames(page)).toHaveText(pageNames(customers));
    expect(customerCount(customers)).toBeGreaterThan(0);
    await expect(box).toHaveValue('ada');
});
