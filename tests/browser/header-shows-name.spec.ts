import { expect, test } from '@playwright/test';

// The gate's user, made by `bin/aic user:add` for each run: an admin whose name is its login.
const login = process.env.AIC_GATE_LOGIN ?? '';
const password = process.env.AIC_GATE_PASSWORD ?? '';

test('signed in, the header shows the name, and the menu the role and sign-out', async ({ page }) => {
    await page.goto('/sign-in');
    await expect(page.getByRole('link', { name: 'Sign in' })).toBeVisible();

    await page.locator('#sign-in-email').fill(login);
    await page.locator('#sign-in-password').fill(password);
    await page.locator('#sign-in-submit').click();
    await expect(page).toHaveURL(/\/app$/);

    await expect(page.locator('#account-name')).toHaveText(login);
    await expect(page.getByRole('link', { name: 'Sign in' })).toHaveCount(0);

    await page.locator('#account-name').click();
    await expect(page.locator('#account-role')).toHaveText('Admin');
    await expect(page.locator('#sign-out')).toBeVisible();
});
