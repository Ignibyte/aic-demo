import { expect, test } from '@playwright/test';
import { carFor, chatsOf, dock, insertMember as insertChatMember, lookEmail as chatEmail, removeChatLooks, startChat } from './support/chat';
import { collectFormErrors } from './support/forms';
import { literal, sql } from './support/inventory';
import {
    DIGEST,
    DOMAIN,
    type Marks,
    cells,
    insertMember,
    marks,
    openJobs,
    queueAbouts,
    queueDigest,
    queueRows,
    removeJobsLooks,
    signInMember,
    workWithMailFault,
} from './support/jobs';
import { csrfToken, fetchInPage } from './support/photos';
import { work } from './support/schedules';

// AIC's times, ISO 8601 in UTC.
const ISO = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/;

let since: Marks;

test.beforeEach(async () => {
    await removeChatLooks();
    work();
    since = marks();
});
test.afterEach(async () => {
    await removeJobsLooks(since);
    await removeChatLooks();
});

test('an admin sees the queue at /app/jobs: the digest and a chat transcript waiting, newest first, then the mails they queue', async ({ browser, page }) => {
    test.setTimeout(60_000);
    const errors = collectFormErrors(page);
    const manager = insertMember('Jo Digest', 'sales_manager');
    await openJobs(page);

    // The link is an admin's, current here; the intro names the console that works the queue.
    const navigation = page.getByRole('navigation', { name: 'Main' });
    await expect(navigation.getByRole('link', { name: 'Jobs' })).toHaveAttribute('aria-current', 'page');
    await expect(navigation.getByRole('link', { name: 'Jobs' })).toHaveAttribute('href', '/app/jobs');
    await expect(page.locator('#jobs-heading')).toHaveText('The queue');
    for (const command of ['bin/aic queue:work', 'bin/aic schedule:run', 'bin/aic queue:failed', 'bin/aic queue:retry']) {
        await expect(page.locator('#jobs-console')).toContainText(command);
    }
    await expect(page.locator('#jobs-queue-heading')).toHaveText('In the queue');
    await expect(page.locator('#jobs-failed-heading')).toHaveText('Failed');

    // The scheduler queues the nightly digest; then a visitor who gave an email ends a chat, which queues its
    // transcript.
    expect(queueDigest()).toMatch(/^schedule:run queued \d+ job\(s\)\.$/);
    const ann = insertChatMember('Jobs Ann');
    const car = carFor(ann);
    const visitor = await browser.newContext();
    const visitorPage = await visitor.newPage();
    const visitorEmail = chatEmail('jobs');
    await startChat(visitorPage, car, { name: 'Chat Look Jobs', email: visitorEmail, message: 'Is it still there?' });
    const [chat] = chatsOf('Chat Look Jobs');
    await visitorPage.locator('#chat-end-go').click();
    await expect(dock(visitorPage)).toHaveCount(0);
    await visitor.close();

    // Within a poll both wait in the queue, the transcript first as the newer, with no attempts and not started.
    const transcript = queueRows(page, `chat ${chat.id}`);
    await expect(transcript).toHaveCount(1, { timeout: 8_000 });
    const [job, about, state, attempts, due, started] = await cells(transcript);
    expect([job, about, state, attempts, started]).toEqual(['chat-transcript', `chat ${chat.id}`, 'Waiting', '0', 'Not started']);
    expect(due).toMatch(ISO);
    const digest = page.locator('#jobs-queue tbody tr').filter({ has: page.locator('td:first-child', { hasText: /^daily-digest$/ }) }).first();
    const digestCells = await cells(digest);
    expect([digestCells[0], digestCells[1], digestCells[2], digestCells[3], digestCells[5]]).toEqual(['daily-digest', 'Nothing', 'Waiting', '0', 'Not started']);
    expect(digestCells[4]).toMatch(ISO);
    const abouts = await queueAbouts(page);
    expect(abouts.indexOf(`chat ${chat.id}`)).toBeLessThan(abouts.indexOf('Nothing'));
    await expect(page.locator('#jobs-queue-summary')).toHaveText(/^\d+ jobs? in the queue, newest first$/);

    // A worker with the mail out of reach runs both; the mails they queue wait to retry, each read from its payload.
    expect(workWithMailFault()).toMatch(/^queue:work ran \d+ job\(s\)\.$/);
    const digestMail = queueRows(page, `Mail to ${manager.email}: ${DIGEST}`);
    await expect(digestMail).toHaveCount(1, { timeout: 8_000 });
    expect((await cells(digestMail)).slice(0, 4)).toEqual(['notify', expect.stringMatching(/^Mail to \S+: Ridgeway Motors on \d{4}-\d{2}-\d{2}: /), 'Retrying', '1']);
    const transcriptMail = queueRows(page, `Mail to ${visitorEmail}: Your chat about the `);
    await expect(transcriptMail).toHaveCount(1);
    expect((await cells(transcriptMail))[0]).toBe('notify');
    await expect(transcript).toHaveCount(0);
    expect(errors).toEqual([]);
});

test('a sales manager and a salesperson see no Jobs link and are refused at every jobs route', async ({ page }) => {
    const errors = collectFormErrors(page);
    const email = `kept.failed.${Date.now()}${DOMAIN}`;
    const payload = JSON.stringify({ body: 'Look', channel: 'mail', email, link: '', subject: 'A look of its own', user: '' });
    const [id] = sql(
        'INSERT INTO aic_failed_jobs (job, payload, attempts, reason, failed_at) '
            + `VALUES ('notify', ${literal(payload)}, 5, 'SMTP: cannot reach the look', now()) RETURNING id`,
    );

    for (const role of ['sales_manager', 'salesperson']) {
        const member = insertMember(`Fay ${role} Jobs`, role);
        await signInMember(page, member);
        const navigation = page.getByRole('navigation', { name: 'Main' });
        await expect(navigation.getByRole('link', { name: 'Customers' })).toBeVisible();
        await expect(navigation.getByRole('link', { name: 'Jobs' })).toHaveCount(0);

        const answer = await page.goto('/app/jobs');
        expect(answer?.status()).toBe(403);
        await expect(page).toHaveTitle('Not allowed · Lotline');
        await expect(page.locator('#jobs-refused')).toHaveText('Only an admin can see and change the jobs.');
        await expect(page.locator('#jobs-queue')).toHaveCount(0);

        // The posts, with their own session's token: 403, and the failed job is still there.
        await page.goto('/app');
        const token = await csrfToken(page);
        for (const path of [`/app/jobs/failed/${id}/retry`, `/app/jobs/failed/${id}/discard`]) {
            const posted = await fetchInPage(page, path, { method: 'POST', fields: { aic_csrf: token } });
            expect(posted.status).toBe(403);
            expect(posted.body.toString('utf8')).toContain('Only an admin can see and change the jobs.');
        }

        expect(sql(`SELECT count(*) FROM aic_failed_jobs WHERE id = ${id}`)).toEqual(['1']);
        await page.context().clearCookies();
    }

    // Chrome logs each refused page as a failed load; nothing else may be logged.
    expect(errors.filter((error) => !error.includes('status of 403'))).toEqual([]);
});
