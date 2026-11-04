import { expect, test } from '@playwright/test';
import { type Marks, marks, openJobs, queueDigest, removeJobsLooks } from './support/jobs';
import { recordPolls } from './support/live';
import { work } from './support/schedules';
import { anchorPath, collectErrors, expectMarksKept, markPage, ready } from './support/tessera';

let since: Marks;

test.beforeEach(() => {
    work();
    since = marks();
});
test.afterEach(() => removeJobsLooks(since));

test('the open jobs page refreshes its lists by poll: 204 while nothing changes, and a queued job and a worker run show with no reload', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectErrors(page);
    await openJobs(page);
    await ready(page);

    // Both lists sit in the one part Tessera polls from the page itself, under its anchor.
    await expect(page.locator('[data-poll__5s]')).toHaveAttribute('data-poll__5s', '/app/jobs');
    await expect(page.locator('[data-poll__5s] #jobs-queue-heading')).toHaveCount(1);
    await expect(page.locator('[data-poll__5s] #jobs-failed-heading')).toHaveCount(1);
    await expect(page.locator('#jobs-console')).toHaveCount(1);
    await expect(page.locator('[data-poll__5s] #jobs-console')).toHaveCount(0);
    const live = await anchorPath(page, 'jobs-live');
    expect(live).not.toBe('');
    await markPage(page);
    const requests = await recordPolls(page);
    const digests = page.locator('#jobs-queue tbody tr').filter({ has: page.locator('td:first-child', { hasText: /^daily-digest$/ }) });
    const before = await digests.count();

    // While nothing changes each refresh is answered 204.
    await expect.poll(() => requests.filter((request) => request.finished).length, { timeout: 8_000 }).toBeGreaterThan(0);
    expect(requests.filter((request) => request.finished).map((request) => request.status)).toEqual(
        requests.filter((request) => request.finished).map(() => 204),
    );

    // The scheduler queues the digest from the console: within a poll it waits on the page.
    expect(queueDigest()).toMatch(/^schedule:run queued \d+ job\(s\)\.$/);
    await expect(digests).toHaveCount(before + 1, { timeout: 8_000 });

    // A worker runs it, and its mails go out: within a poll it is gone.
    expect(work()).toMatch(/^queue:work ran \d+ job\(s\)\.$/);
    await expect(digests).toHaveCount(before, { timeout: 8_000 });

    // No reload: the same document, and every request since load a finished refresh of the part alone, answered 204
    // while nothing changed and with the part when the queue had.
    await expectMarksKept(page);
    await expect.poll(() => requests.every((request) => request.finished)).toBe(true);
    for (const request of requests) {
        const sent = { resourceType: 'fetch', method: 'GET', path: '/app/jobs', tesseraRequest: 'refresh', targets: live, props: true, anchor: '@jobs-live', failure: '', finished: true };
        const answer = request.status === 204
            ? { status: 204, contentType: expect.any(String), templates: [] }
            : { status: 200, contentType: expect.stringMatching(/^text\/vnd\.tessera\+html/), templates: [live] };

        expect(request).toEqual({ ...sent, ...answer });
    }
    expect(requests.some((request) => request.status === 200)).toBe(true);
    expect(errors).toEqual([]);

    // A refresh whose props were altered answers 400, decided before the queue is listed. The page sends it, with its
    // session: the session cookie is `__Host-` and secure, which Playwright's own request context keeps from a site
    // served over plain HTTP, as gate:23 serves the compiled one.
    const token = await page.locator('[data-t-anchor="jobs-live"]').getAttribute('data-t-props') ?? '';
    expect(token).not.toBe('');
    const middle = Math.floor(token.length / 2);
    const altered = token.slice(0, middle) + (token[middle] === 'A' ? 'B' : 'A') + token.slice(middle + 1);
    const refused = await page.evaluate(async (targets) => {
        const response = await fetch('/app/jobs', {
            headers: {
                'Tessera-Request': 'refresh',
                'Tessera-Protocol': '2',
                'Tessera-Layout': 'lotline',
                'Tessera-Targets': targets,
            },
        });

        return response.status;
    }, `${live} ${altered} @jobs-live`);
    expect(refused).toBe(400);

    // Chromium reports that 400 in the console; it is the only error.
    expect(errors).toEqual(['Failed to load resource: the server responded with a status of 400 (Bad Request)']);
});
