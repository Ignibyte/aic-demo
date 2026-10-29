import { expect, test } from '@playwright/test';
import { literal, sql } from './support/inventory';
import { mailFor } from './support/leads';
import { databaseNow, insertMember, removeHoldLooks } from './support/holds';
import { drain, failedJobs, retry, runSchedule, unusedMinute, work, workWithMailDown } from './support/schedules';

let since = '';

test.beforeEach(async () => {
    await removeHoldLooks();
    since = databaseNow();
});
test.afterEach(() => removeHoldLooks(since));

// The digest's mail delivery to one address, while it waits in the queue: its attempts so far, and whether it still waits
// more than 10 seconds (AIC #208 delays it 30 seconds at least, and `ddev exec` takes some of them).
function waiting(email: string): { attempts: number; delayed: boolean }[] {
    return sql(
        "SELECT attempts || '|' || (available_at > now() + interval '10 seconds') FROM aic_jobs WHERE job = 'notify' "
            + `AND payload::jsonb ->> 'email' = ${literal(email)} AND payload::jsonb ->> 'subject' LIKE 'Ridgeway Motors on %'`,
    ).map((row) => {
        const [attempts, delayed] = row.split('|');

        return { attempts: Number(attempts), delayed: delayed === 'true' };
    });
}

// These looks depend on DDEV: the scheduler and the worker run in its web container (`ddev exec`), where
// `AIC_MAIL_PORT=1` on the worker's command line points the mail at a port nothing answers on.
test('a digest mail that cannot reach the mail server retries after a delay, fails on its fifth attempt with the reason, and is retried with bin/aic', async () => {
    const manager = insertMember('Max Maildown', 'sales_manager');
    drain();
    expect(runSchedule(unusedMinute('21:00'))).toBe('schedule:run queued 2 job(s).');

    // The first worker runs the digest job, which queues the mail; its delivery cannot reach the server and waits.
    workWithMailDown();
    expect(waiting(manager.email)).toEqual([{ attempts: 1, delayed: true }]);

    // Made due again each time, it fails on the second to the fourth attempt and waits again.
    const due = "UPDATE aic_jobs SET available_at = now() WHERE job = 'notify' AND payload::jsonb ->> 'subject' LIKE 'Ridgeway Motors on %'";
    for (const attempt of [2, 3, 4]) {
        sql(due);
        workWithMailDown();
        expect(waiting(manager.email)).toEqual([{ attempts: attempt, delayed: true }]);
    }

    // The fifth attempt fails it with the transport's reason, and bin/aic lists it.
    sql(due);
    workWithMailDown();
    expect(waiting(manager.email)).toEqual([]);
    const [failed] = failedJobs().filter((job) => job.line.includes(manager.email));
    expect(failed.job).toBe('notify');
    expect(failed.line).toMatch(/ after 5 attempts: SMTP: cannot reach /);
    expect(await mailFor(manager.email)).toEqual([]);

    // Retried, it waits again with no attempts, and a worker with the server back delivers it once.
    expect(retry(failed.id)).toBe(`queue:retry queued failed job ${failed.id} again.`);
    expect(failedJobs().filter((job) => job.id === failed.id)).toEqual([]);
    work();
    const mails = await mailFor(manager.email);
    expect(mails).toHaveLength(1);
    expect(mails[0].subject).toMatch(/^Ridgeway Motors on \d{4}-\d{2}-\d{2}: /);
    expect(waiting(manager.email)).toEqual([]);
});
