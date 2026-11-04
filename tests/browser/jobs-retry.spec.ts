import { expect, test } from '@playwright/test';
import {
    DIGEST,
    type Marks,
    cells,
    failedCard,
    failedMail,
    insertMember,
    isLater,
    makeDue,
    marks,
    openJobs,
    queueDigest,
    queuedMail,
    queueRows,
    removeJobsLooks,
    workWithMailFault,
} from './support/jobs';
import { mailFor } from './support/leads';
import { failedJobs, retry, work } from './support/schedules';
import { collectErrors } from './support/tessera';

let since: Marks;

test.beforeEach(() => {
    work();
    since = marks();
});
test.afterEach(() => removeJobsLooks(since));

test('with the dev mail fault switch a digest mail retries and fails with its reason; Retry now, Discard and queue:retry put it back or drop it', async ({ page }) => {
    test.setTimeout(120_000);
    const errors = collectErrors(page);
    const kept = insertMember('Kit Retry', 'sales_manager');
    const dropped = insertMember('Dot Discard', 'sales_manager');
    const replayed = insertMember('Ray Replay', 'sales_manager');
    const members = [kept, dropped, replayed];
    await openJobs(page);

    // The digest runs under `ddev queue-work-mail-down`; each of its mails cannot reach the server and waits to retry.
    expect(queueDigest()).toMatch(/^schedule:run queued \d+ job\(s\)\.$/);
    expect(workWithMailFault()).toMatch(/^queue:work ran \d+ job\(s\)\.$/);
    for (const member of members) {
        expect(queuedMail(member.email)).toEqual([1]);
    }

    // The page shows each attempt within a poll: Retrying, its attempts, and the next attempt after now.
    const row = queueRows(page, `Mail to ${kept.email}: ${DIGEST}`);
    for (const attempt of [1, 2, 3, 4]) {
        if (attempt > 1) {
            for (const member of members) {
                makeDue(member.email);
            }
            expect(workWithMailFault()).toMatch(/^queue:work ran \d+ job\(s\)\.$/);
            expect(queuedMail(kept.email)).toEqual([attempt]);
        }

        await expect(row.locator('td').nth(3)).toHaveText(String(attempt), { timeout: 8_000 });
        const [job, , state, , due, started] = await cells(row);
        expect([job, state, started]).toEqual(['notify', 'Retrying', 'Not started']);
        expect(isLater(due)).toBe(true);
    }

    // The fifth attempt fails it with the transport's reason: it leaves the queue for Failed.
    for (const member of members) {
        makeDue(member.email);
    }
    expect(workWithMailFault()).toMatch(/^queue:work ran \d+ job\(s\)\.$/);
    const [keptId] = failedMail(kept.email);
    const [droppedId] = failedMail(dropped.email);
    const [replayedId] = failedMail(replayed.email);
    for (const member of members) {
        expect(queuedMail(member.email)).toEqual([]);
    }
    const card = failedCard(page, keptId);
    await expect(card).toBeVisible({ timeout: 8_000 });
    await expect(row).toHaveCount(0);
    await expect(card.locator(`#failed-${keptId}-title`)).toHaveText('notify');
    await expect(card).toContainText('Failed');
    await expect(card).toContainText(`Mail to ${kept.email}: ${DIGEST}`);
    await expect(card.locator('dd').nth(1)).toHaveText('5');
    await expect(card.locator('dd').nth(2)).toHaveText(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/);
    await expect(card.locator(`#failed-${keptId}-reason`)).toContainText('SMTP: cannot reach');
    await expect(page.locator('#jobs-failed-summary')).toHaveText(/^\d+ failed jobs?, newest first$/);
    expect(await mailFor(kept.email)).toEqual([]);

    // Retry now queues it again: back on the jobs page with the notice, waiting with no attempts.
    await card.locator(`#failed-${keptId}-retry`).click();
    await expect(page).toHaveURL(/\/app\/jobs$/);
    await expect(page.locator('#jobs-notice')).toHaveText(`Queued failed job ${keptId} again. It waits with no attempts.`);
    await expect(card).toHaveCount(0);
    const [retriedJob, retriedAbout, retriedState, retriedAttempts] = await cells(row);
    expect([retriedJob, retriedState, retriedAttempts]).toEqual(['notify', 'Waiting', '0']);
    expect(retriedAbout.startsWith(`Mail to ${kept.email}: ${DIGEST}`)).toBe(true);
    expect(queuedMail(kept.email)).toEqual([0]);

    // A plain worker, with the mail back, mails it once, and it leaves the page.
    expect(work()).toMatch(/^queue:work ran \d+ job\(s\)\.$/);
    const mails = await mailFor(kept.email);
    expect(mails).toHaveLength(1);
    expect(mails[0].subject.startsWith(DIGEST)).toBe(true);
    await expect(row).toHaveCount(0, { timeout: 8_000 });

    // Discard drops another from the failed record.
    await failedCard(page, droppedId).locator(`#failed-${droppedId}-discard`).click();
    await expect(page).toHaveURL(/\/app\/jobs$/);
    await expect(page.locator('#jobs-notice')).toHaveText(`Discarded failed job ${droppedId}.`);
    await expect(failedCard(page, droppedId)).toHaveCount(0);
    expect(failedJobs().filter((failed) => failed.id === droppedId)).toEqual([]);
    expect(queuedMail(dropped.email)).toEqual([]);

    // AIC's own queue:retry replays the third from the console, and the page shows it waiting within a poll.
    expect(retry(replayedId)).toBe(`queue:retry queued failed job ${replayedId} again.`);
    await expect(failedCard(page, replayedId)).toHaveCount(0, { timeout: 8_000 });
    const replayedRow = queueRows(page, `Mail to ${replayed.email}: ${DIGEST}`);
    await expect(replayedRow).toHaveCount(1);
    expect((await cells(replayedRow)).slice(2, 4)).toEqual(['Waiting', '0']);
    expect(errors).toEqual([]);
});
