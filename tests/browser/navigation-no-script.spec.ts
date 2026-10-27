import { expect, test } from '@playwright/test';
import { expectDocument, expectPage, recordRequests, signIn, START, WALK, type Visited } from './support/navigation';
import { collectErrors } from './support/tessera';

test.use({ javaScriptEnabled: false });

test('with JavaScript off every page of the walk loads as a document, and back and forward work', async ({ page }) => {
    const errors = collectErrors(page);
    await signIn(page);
    await expectPage(page, START, '/app');

    const requests = await recordRequests(page);
    const visited: Visited[] = [{ step: START, path: '/app', scroll: 0 }];

    for (const step of WALK) {
        const path = (await step.link(page).getAttribute('href')) ?? '';
        expect(path, step.name).not.toBe('');
        await step.link(page).click();

        await expectDocument(requests, path);
        await expectPage(page, step, path);
        visited.push({ step, path, scroll: 0 });
    }

    // The pages answer `no-store`, so back and forward load each one again as a document.
    for (let index = visited.length - 2; index >= 0; index--) {
        await page.goBack();
        await expectDocument(requests, visited[index].path);
        await expectPage(page, visited[index].step, visited[index].path);
    }

    for (const entry of visited.slice(1)) {
        await page.goForward();
        await expectDocument(requests, entry.path);
        await expectPage(page, entry.step, entry.path);
    }

    expect(errors).toEqual([]);
});
