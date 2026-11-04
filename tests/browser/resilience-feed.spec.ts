// A failing poll (#34): the feed is polled every second from its page. Taken down, its next three polls are answered
// 503 in plain text, each raising `tessera:error` on the feed, which the page counts and keeps out of the console. The
// fourth poll answers again, and the feed says so, in the same document.

import { expect, test } from '@playwright/test';
import { CASES, collectCaseErrors, heard, listenFor, markWindow, nextPost, windowKept } from './support/resilience';
import { ready } from './support/tessera';

// One poll of the feed and what it was answered with.
interface Poll {
    status: number;
    contentType: string;
}

test('a poll whose endpoint fails raises tessera:error each time, and the feed recovers when it answers again', async ({ page }) => {
    const errors = collectCaseErrors(page, [503]);
    await page.goto(CASES.feed);
    await ready(page);
    await listenFor(page, 'tessera:error');
    await markWindow(page);
    const status = page.locator('#feed-status');
    await expect(status).toHaveText('The feed is up. Tessera polls it every second.');
    await expect(status).toHaveAttribute('data-poll__1s', CASES.feed);

    const polls: Poll[] = [];
    page.on('response', (response) => {
        if (new URL(response.url()).pathname === CASES.feed && response.request().headers()['tessera-request'] === 'refresh') {
            polls.push({ status: response.status(), contentType: response.headers()['content-type'] ?? '' });
        }
    });

    const [down] = await Promise.all([nextPost(page, CASES.feed), page.locator('#feed-down-go').click()]);
    expect(down.status()).toBe(200);
    expect(down.headers()['tessera-location']).toBe(CASES.feed);
    const before = polls.length;

    await expect(status).toHaveText('The feed answered again after 3 failed polls.', { timeout: 15_000 });
    const after = polls.slice(before);
    const failed = after.filter((poll) => poll.status === 503);
    expect(failed).toHaveLength(3);
    expect(failed.every((poll) => poll.contentType === 'text/plain; charset=utf-8')).toBe(true);
    expect(after.at(-1)?.status).toBe(200);
    await expect(page.locator('#feed-watch-count')).toHaveText('3');
    expect(await heard(page, 'tessera:error')).toBe(3);
    expect(await windowKept(page)).toBe(true);

    expect(errors).toEqual([]);
});
