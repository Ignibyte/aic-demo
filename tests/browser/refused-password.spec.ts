import { expect, test } from '@playwright/test';

// The gate's user, made by `bin/aic user:add` for each run.
const login = process.env.AIC_GATE_LOGIN ?? '';

test('a refused password shows the error and does not sign in', async ({ page }) => {
    await page.goto('/sign-in?next=%2Fapp');

    await page.locator('#sign-in-email').fill(login);
    await page.locator('#sign-in-password').fill('not-the-password');
    await page.locator('#sign-in-submit').click();

    await expect(page.locator('#sign-in-error')).toHaveText('Wrong email or password.');
    await expect(page.locator('#sign-in-email')).toHaveValue(login);
    await expect(page.locator('#sign-in-password')).toHaveValue('');
    await expect(page.locator('#account-name')).toHaveCount(0);

    await page.goto('/app');
    await expect(page).toHaveURL(/\/sign-in\?next=%2Fapp$/);
});
