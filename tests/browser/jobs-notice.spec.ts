import { expect, test } from '@playwright/test';
import { literal, sql } from './support/inventory';
import { DOMAIN, type Marks, failedCard, marks, openJobs, removeJobsLooks } from './support/jobs';
import { recordPolls } from './support/live';
import { work } from './support/schedules';
import { anchorPath, collectErrors, ready } from './support/tessera';

let since: Marks;

test.beforeEach(() => {
    work();
    since = marks();
});
test.afterEach(() => removeJobsLooks(since));

// A failed mail of the look's own, as the worker records one after its last attempt.
function insertFailedMail(): number {
    const email = `notice.${Date.now()}${DOMAIN}`;
    const payload = JSON.stringify({ body: 'Look', channel: 'mail', email, link: '', subject: 'Notice look', user: '' });
    const [id] = sql(
        'INSERT INTO aic_failed_jobs (job, payload, attempts, reason, failed_at) '
            + `VALUES ('notify', ${literal(payload)}, 5, 'SMTP: cannot reach 127.0.0.1:1', now()) RETURNING id`,
    );

    return Number(id);
}

test('a poll between a Discard and the page load leaves its notice for the page', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = collectErrors(page);
    const id = insertFailedMail();
    await openJobs(page);
    await ready(page);
    const card = failedCard(page, id);
    await expect(card).toBeVisible();
    const live = await anchorPath(page, 'jobs-live');
    expect(live).not.toBe('');

    // The card's Discard form, its guard fields as the page wrote them.
    const form = card.locator(`form[action="/app/jobs/failed/${id}/discard"][method="post"]`);
    const fields: Record<string, string> = {};
    for (const input of await form.locator('input[type="hidden"], input[name]').all()) {
        const name = await input.getAttribute('name');
        if (name !== null) {
            fields[name] = (await input.getAttribute('value')) ?? '';
        }
    }
    expect(Object.keys(fields)).toContain('aic_csrf');

    // Discard posted from a second tab of the same session, one on the dashboard, which takes no notice. It is the
    // tab's own fetch(), since page.request sends no Secure cookie to the compiled site over plain HTTP, and it does
    // not follow the 303, so nobody has loaded the page the notice waits for.
    const requests = await recordPolls(page);
    const other = await page.context().newPage();
    await other.goto('/app');
    const posted = await other.evaluate(async ({ path, fields }) => {
        const body = new FormData();
        for (const [name, value] of Object.entries(fields)) {
            body.append(name, value);
        }
        const response = await fetch(path, { method: 'POST', body, cache: 'no-store', redirect: 'manual' });

        return response.type;
    }, { path: `/app/jobs/failed/${id}/discard`, fields });
    expect(posted).toBe('opaqueredirect');
    expect(sql(`SELECT count(*) FROM aic_failed_jobs WHERE id = ${id}`)).toEqual(['0']);

    // The open tab polls before the page loads: the queue changed, so the part alone comes back built, and the card
    // goes; the notice is not shown there, since the answer sends the part alone.
    await expect(card).toHaveCount(0, { timeout: 8_000 });
    await expect.poll(() => requests.some((request) => request.finished && request.status === 200), { timeout: 8_000 }).toBe(true);
    for (const request of requests.filter((sent) => sent.finished && sent.status === 200)) {
        expect(request).toMatchObject({ method: 'GET', path: '/app/jobs', tesseraRequest: 'refresh', targets: live, anchor: '@jobs-live', templates: [live] });
    }
    await expect(page.locator('#jobs-notice')).toHaveCount(0);

    // The page load after it still shows the notice, and a second load does not.
    await other.goto('/app/jobs');
    await expect(other.locator('#jobs-notice')).toHaveText(`Discarded failed job ${id}.`);
    await other.reload();
    await expect(other.locator('#jobs-notice')).toHaveCount(0);
    await other.close();

    expect(errors).toEqual([]);
});
