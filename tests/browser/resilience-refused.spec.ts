// A refused form (#34): the guide's vehicle form posted empty is answered 422 with three errors, in place, and Tessera
// focuses the summary AIC's `Alert` marks with `data-error-summary`. A valid post goes back to the case page.

import { expect, test } from '@playwright/test';
import { CASES, collectCaseErrors, markWindow, nextPost, windowKept } from './support/resilience';
import { ready } from './support/tessera';

test('a form refused with several errors is answered 422 in place, its error summary focused', async ({ page }) => {
    const errors = collectCaseErrors(page, [422]);
    await page.goto(CASES.refused);
    await ready(page);
    await markWindow(page);

    const [refused] = await Promise.all([nextPost(page, CASES.refused), page.locator('#vehicle-save').click()]);
    expect(refused.status()).toBe(422);
    expect(refused.request().headers()['tessera-request']).toBe('submit');
    expect(refused.headers()['content-type']).toMatch(/^text\/vnd\.tessera\+html/);

    const summary = page.locator('#vehicle-error');
    await expect(summary).toHaveText('Fix the fields marked below.');
    await expect(summary).toHaveAttribute('data-error-summary', '');
    await expect(summary).toBeFocused();
    await expect(page.locator('[aria-invalid="true"]')).toHaveCount(3);

    for (const field of ['#vehicle-name', '#vehicle-price', '#vehicle-body_style']) {
        await expect(page.locator(field)).toHaveAttribute('aria-invalid', 'true');
    }

    expect(await windowKept(page)).toBe(true);
    expect(new URL(page.url()).pathname).toBe(CASES.refused);

    // Valid, it goes back to the case page, the errors gone.
    await page.locator('#vehicle-name').fill('Demo truck');
    await page.locator('#vehicle-price').fill('24500');
    await page.locator('#vehicle-body_style').selectOption('truck');
    const [saved] = await Promise.all([nextPost(page, CASES.refused), page.locator('#vehicle-save').click()]);
    expect(saved.status()).toBe(200);
    expect(saved.headers()['tessera-location']).toBe(CASES.refused);
    await expect(page.locator('#vehicle-error')).toHaveCount(0);
    await expect(page.locator('[aria-invalid="true"]')).toHaveCount(0);

    expect(errors).toEqual([]);
});
