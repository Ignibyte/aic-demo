import { expect, test } from '@playwright/test';
import { expectPage, expectScroll, expectVisit, recordRequests, scrollOf, signIn, START, WALK, type Visited } from './support/navigation';
import { collectErrors, expectMarksKept, markPage } from './support/tessera';

// Short enough that the inventory and the customers scroll.
test.use({ viewport: { width: 1280, height: 600 } });

// The lists whose record link the walk scrolls down to before it clicks it.
const SCROLLED = ['the inventory', 'the customers'];

test('back and forward through the walk restore each page and its scroll in place', async ({ page }) => {
    const errors = collectErrors(page);
    await signIn(page);
    await expectPage(page, START, '/app');

    const requests = await recordRequests(page);
    await markPage(page);

    // Forward through the walk, each page's scroll noted as it is left.
    const visited: Visited[] = [{ step: START, path: '/app', scroll: 0 }];

    for (const step of WALK) {
        const here = visited[visited.length - 1];
        const link = step.link(page);

        // The link is brought into view first, so the click does not scroll and the page is left where it was noted.
        await link.evaluate((element) => {
            element.scrollIntoView({ block: 'center' });
        });
        here.scroll = await scrollOf(page);
        await expectScroll(page, here.scroll, here.step.name);

        if (SCROLLED.includes(here.step.name)) {
            expect(here.scroll, here.step.name).toBeGreaterThan(100);
        }

        const path = (await link.getAttribute('href')) ?? '';
        await link.click();
        await expectVisit(requests, path);
        await expectPage(page, step, path);
        await expectScroll(page, 0, step.name);
        visited.push({ step, path, scroll: 0 });
    }

    // Back to the start: each page as it was left, the lists scrolled where they were; the first page sign-in loaded
    // is fetched whole.
    for (let index = visited.length - 2; index >= 0; index--) {
        const entry = visited[index];
        await page.goBack();
        await expectVisit(requests, entry.path, index === 0);
        await expectPage(page, entry.step, entry.path);
        await expectScroll(page, entry.scroll, entry.step.name);
        await expectMarksKept(page);
    }

    // Forward to the end again.
    for (const entry of visited.slice(1)) {
        await page.goForward();
        await expectVisit(requests, entry.path);
        await expectPage(page, entry.step, entry.path);
        await expectScroll(page, entry.scroll, entry.step.name);
        await expectMarksKept(page);
    }

    expect(errors).toEqual([]);
});
