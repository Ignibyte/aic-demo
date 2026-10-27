import { expect, test } from '@playwright/test';
import { expectPage, expectVisit, recordRequests, signIn, START, WALK } from './support/navigation';
import { collectErrors, expectMarksKept, markPage } from './support/tessera';

test('a walk through every page swaps its regions in place, its title, current link and focus following each page', async ({ page }) => {
    const errors = collectErrors(page);
    await signIn(page);
    await expectPage(page, START, '/app');

    const requests = await recordRequests(page);
    await markPage(page);

    for (const step of WALK) {
        const path = (await step.link(page).getAttribute('href')) ?? '';
        expect(path, step.name).not.toBe('');
        await step.link(page).click();

        await expectVisit(requests, path);
        await expectPage(page, step, path);
        await expect(page.locator('[data-region="header"] h1')).toBeFocused();
        await expectMarksKept(page);
    }

    // Sign-out ends the session, so its answer asks for a whole document, and the window's mark goes with the old one.
    await page.locator('#account-name').click();
    await page.locator('#sign-out').click();
    await expect(page).toHaveURL('/');
    await expect.poll(() => requests.map((sent) => `${sent.resourceType} ${sent.method} ${sent.path}`)).toEqual(['fetch POST /sign-out', 'document GET /']);
    expect(requests[0].tesseraRequest).toBe('submit');
    expect(requests[0].fullVisit).toBe('/');
    expect(requests[1].status).toBe(200);
    requests.length = 0;
    await page.waitForLoadState('load');
    expect(await page.evaluate(() => (window as Window & { lotlineMark?: string }).lotlineMark)).toBeUndefined();
    await expect(page).toHaveTitle('Dashboard · Lotline');
    await expect(page.locator('[data-region="header"] h1')).toHaveText('Dashboard');

    // The health link answers JSON, so it opts out and loads as a document.
    const health = page.locator('a[href="/health"]');
    await expect(health).toHaveAttribute('data-enhance', 'off');
    await health.click();
    await expect(page).toHaveURL('/health');
    await expect.poll(() => requests.map((sent) => `${sent.resourceType} ${sent.method} ${sent.path} ${sent.status}`)).toEqual(['document GET /health 200']);
    expect(requests[0].tesseraRequest).toBe('');

    expect(errors).toEqual([]);
});
