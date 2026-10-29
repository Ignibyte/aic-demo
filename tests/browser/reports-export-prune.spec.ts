import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';
import { expect, test } from '@playwright/test';
import { sql } from './support/inventory';
import { runSchedule, unusedMinute, work } from './support/schedules';
import { RANGE, exportFile, exportFileExists, insertMember, removeReportLooks, storedExports } from './support/reports';

test.beforeEach(async () => removeReportLooks());
test.afterEach(async () => removeReportLooks());

// A ready export of the member's, asked for that many days ago, with its file.
function insertExport(memberId: string, daysAgo: number): number {
    const [id] = sql(
        'INSERT INTO report_exports (user_id, report, from_day, to_day, status, created_at) '
            + `VALUES (${memberId}, 'sales', '${RANGE.from}', '${RANGE.to}', 'ready', now() - interval '${daysAgo} days') RETURNING id`,
    );
    const path = exportFile(Number(id));
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, 'Month,Sales,Revenue\r\n');

    return Number(id);
}

test('the prune schedule removes an export past seven days, its row and its file, and keeps a younger one', async () => {
    const manager = insertMember('Ira Look', 'sales_manager');
    const old = insertExport(manager.id, 8);
    const young = insertExport(manager.id, 6);
    work();

    // Its own minute, 04:15 UTC, queues the prune and the 15-minute release of holds; one worker runs them.
    expect(runSchedule(unusedMinute('04:15'))).toBe('schedule:run queued 2 job(s).');
    work();

    expect(storedExports(manager).map((stored) => stored.id)).toEqual([young]);
    expect(exportFileExists(old)).toBe(false);
    expect(exportFileExists(young)).toBe(true);
});
