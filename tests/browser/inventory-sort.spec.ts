import { expect, test } from '@playwright/test';
import { pageVins, shownVins, signIn } from './support/inventory';

test('a head sorts by its column, a second click reverses it, and the filter stays', async ({ page }) => {
    await signIn(page);
    await page.goto('/app/inventory?status=in_stock');

    const head = (label: string) => page.locator('#vehicles-table thead th', { hasText: label });
    const kept = "WHERE status = 'in_stock'";

    await expect(head('Added')).toHaveAttribute('aria-sort', 'descending');
    await expect(head('Price')).toHaveAttribute('aria-sort', 'none');
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'added_on DESC'));

    await head('Price').getByRole('link').click();
    await expect(head('Price')).toHaveAttribute('aria-sort', 'ascending');
    await expect(head('Added')).toHaveAttribute('aria-sort', 'none');
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'list_price ASC'));
    await expect(page.locator('#filter-status')).toHaveValue('in_stock');

    await head('Price').getByRole('link').click();
    await expect(head('Price')).toHaveAttribute('aria-sort', 'descending');
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'list_price DESC'));
    await expect(page.locator('#filter-status')).toHaveValue('in_stock');

    await head('Make').getByRole('link').click();
    await expect(head('Make')).toHaveAttribute('aria-sort', 'ascending');
    await expect(head('Price')).toHaveAttribute('aria-sort', 'none');
    await expect(shownVins(page)).toHaveText(pageVins(kept, 'make ASC'));
});
