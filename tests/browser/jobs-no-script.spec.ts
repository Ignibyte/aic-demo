import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { literal, sql } from './support/inventory';
import { DOMAIN, type Marks, cells, failedCard, marks, openJobs, queueRows, removeJobsLooks } from './support/jobs';
import { work } from './support/schedules';

test.use({ javaScriptEnabled: false });

let since: Marks;

test.beforeEach(() => {
    work();
    since = marks();
});
test.afterEach(() => removeJobsLooks(since));

// A failed mail of the look's own, as the worker records one after its last attempt.
function insertFailedMail(name: string): { id: number; email: string } {
    const email = `${name}.${Date.now()}${DOMAIN}`;
    const payload = JSON.stringify({ body: 'Look', channel: 'mail', email, link: '', subject: `No script ${name}`, user: '' });
    const [id] = sql(
        'INSERT INTO aic_failed_jobs (job, payload, attempts, reason, failed_at) '
            + `VALUES ('notify', ${literal(payload)}, 5, 'SMTP: cannot reach 127.0.0.1:1', now()) RETURNING id`,
    );

    return { id: Number(id), email };
}

test('with JavaScript off the jobs page shows both lists, and Retry now and Discard post as plain forms', async ({ page }) => {
    const errors = collectFormErrors(page);
    const retried = insertFailedMail('retried');
    const discarded = insertFailedMail('discarded');
    await openJobs(page);

    // Both panels and both cards, each card's two buttons in plain POST forms.
    await expect(page.locator('#jobs-queue-heading')).toHaveText('In the queue');
    await expect(page.locator('#jobs-failed-heading')).toHaveText('Failed');
    for (const failed of [retried, discarded]) {
        const card = failedCard(page, failed.id);
        await expect(card).toContainText(`Mail to ${failed.email}: No script`);
        await expect(card.locator(`#failed-${failed.id}-reason`)).toHaveText('SMTP: cannot reach 127.0.0.1:1');
        await expect(card.locator(`form[action="/app/jobs/failed/${failed.id}/retry"][method="post"] input[name="aic_csrf"]`)).toHaveCount(1);
        await expect(card.locator(`form[action="/app/jobs/failed/${failed.id}/discard"][method="post"] input[name="aic_csrf"]`)).toHaveCount(1);
    }

    // No poll runs: for more than a tick the page sends nothing.
    const sent: string[] = [];
    page.on('request', (request) => sent.push(request.url()));
    await page.waitForTimeout(6_000);
    expect(sent).toEqual([]);

    // A second tab of the same session holds the page as it is now, the retried card's form with it.
    const stale = await page.context().newPage();
    await stale.goto('/app/jobs');
    await expect(failedCard(stale, retried.id)).toBeVisible();

    // Retry now loads the jobs page with its notice: the mail waits again with no attempts.
    await failedCard(page, retried.id).locator(`#failed-${retried.id}-retry`).click();
    await expect(page).toHaveURL(/\/app\/jobs$/);
    await expect(page.locator('#jobs-notice')).toHaveText(`Queued failed job ${retried.id} again. It waits with no attempts.`);
    await expect(failedCard(page, retried.id)).toHaveCount(0);
    const row = queueRows(page, `Mail to ${retried.email}: No script retried`);
    expect((await cells(row)).slice(0, 4)).toEqual(['notify', `Mail to ${retried.email}: No script retried`, 'Waiting', '0']);

    // Discard loads the page with its notice, the card gone.
    await failedCard(page, discarded.id).locator(`#failed-${discarded.id}-discard`).click();
    await expect(page).toHaveURL(/\/app\/jobs$/);
    await expect(page.locator('#jobs-notice')).toHaveText(`Discarded failed job ${discarded.id}.`);
    await expect(failedCard(page, discarded.id)).toHaveCount(0);
    expect(sql(`SELECT count(*) FROM aic_failed_jobs WHERE id IN (${retried.id}, ${discarded.id})`)).toEqual(['0']);

    // The stale tab's Retry now posts a spent card: the 404 page.
    const [answer] = await Promise.all([
        stale.waitForResponse((response) => response.url().endsWith(`/app/jobs/failed/${retried.id}/retry`)),
        failedCard(stale, retried.id).locator(`#failed-${retried.id}-retry`).click(),
    ]);
    expect(answer.status()).toBe(404);
    await expect(stale).toHaveTitle('Failed job not found · Lotline');
    await expect(stale.locator('#failed-missing')).toHaveText('No failed job has that number. It may have been retried or discarded already.');
    await stale.close();

    // Chrome logs the 404 page as a failed load; nothing else may be logged.
    expect(errors.filter((error) => !error.includes('status of 404'))).toEqual([]);
});
