import { expect, test } from '@playwright/test';

// The gate's user, made by `bin/aic user:add` for each run, so its name is its login.
const login = process.env.AIC_GATE_LOGIN ?? '';
const password = process.env.AIC_GATE_PASSWORD ?? '';

test('a signed-out visit to /app lands on sign-in and comes back after it', async ({ page }) => {
    await page.goto('/app');
    await expect(page).toHaveURL(/\/sign-in\?next=%2Fapp$/);

    await page.locator('#sign-in-email').fill(login);
    await page.locator('#sign-in-password').fill(password);
    await page.locator('#sign-in-submit').click();

    await expect(page).toHaveURL(/\/app$/);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Welcome, ${login}`);
});
