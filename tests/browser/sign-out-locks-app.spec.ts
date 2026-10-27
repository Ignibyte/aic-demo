import { expect, test } from '@playwright/test';

// The gate's user, made by `bin/aic user:add` for each run.
const login = process.env.AIC_GATE_LOGIN ?? '';
const password = process.env.AIC_GATE_PASSWORD ?? '';

test('signing out from the header menu locks /app again', async ({ page }) => {
    await page.goto('/sign-in');
    await page.locator('#sign-in-email').fill(login);
    await page.locator('#sign-in-password').fill(password);
    await page.locator('#sign-in-submit').click();
    await expect(page).toHaveURL(/\/app$/);

    await page.locator('#account-name').click();
    await page.locator('#sign-out').click();

    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole('link', { name: 'Sign in' })).toBeVisible();
    await expect(page.locator('#account-name')).toHaveCount(0);

    await page.goto('/app');
    await expect(page).toHaveURL(/\/sign-in\?next=%2Fapp$/);
});
