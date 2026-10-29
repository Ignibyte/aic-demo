import { expect, test } from '@playwright/test';
import { sql } from './support/inventory';
import { mailFor } from './support/leads';
import { databaseNow, insertLookVehicle, insertMember, notificationsOf, removeHoldLooks, VINS } from './support/holds';
import { drain, ran, workInBackground } from './support/schedules';

let since = '';

test.beforeEach(async () => {
    await removeHoldLooks();
    since = databaseNow();
});
test.afterEach(() => removeHoldLooks(since));

// The aged-stock jobs the look queues at once.
const JOBS = 20;

// These looks depend on DDEV: the workers run in its web container (`ddev exec`), which reaches Mailpit's SMTP, and
// Mailpit is read at the address `ddev describe -j` gives.
test('two workers started together on one queue run each job once', async () => {
    const manager = insertMember('Wes Workers', 'sales_manager');
    insertLookVehicle(VINS.aged61, 'in_stock', 61, 'Aged61');
    drain();

    // Each aged-stock job queues a database and a mail delivery for each sales manager whose login is an address.
    const deliveries = Number(sql("SELECT count(*) * 2 FROM users WHERE role = 'sales_manager' AND email LIKE '%@%'")[0])
        + Number(sql("SELECT count(*) FROM users WHERE role = 'sales_manager' AND email NOT LIKE '%@%'")[0]);
    sql(`INSERT INTO aic_jobs (job, payload, attempts, available_at) SELECT 'aged-stock', '{}', 0, now() FROM generate_series(1, ${JOBS})`);

    // Two workers at once share the queue; between them they run every job, and no job twice.
    const lines = await Promise.all([workInBackground(), workInBackground()]);
    expect(lines.map(ran).reduce((sum, count) => sum + count, 0)).toBe(JOBS + JOBS * deliveries);
    expect(sql("SELECT count(*) FROM aic_jobs WHERE job IN ('aged-stock', 'notify')")).toEqual(['0']);

    // So the manager has each notification once, stored and mailed.
    const notices = notificationsOf(manager);
    expect(notices).toHaveLength(JOBS);
    expect(new Set(notices.map((notice) => notice.subject)).size).toBe(1);
    expect(await mailFor(manager.email)).toHaveLength(JOBS);
});
