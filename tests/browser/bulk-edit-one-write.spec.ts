// Bulk edit of the inventory list (#29): one write, one audit. The change and its audit commit together: one `notify`
// job per active sales manager, for the database channel alone, and after a worker run the manager's inbox holds it.
// The same POST sent again with its `Tessera-Write-Id` is answered from AIC's write ledger with the first answer, and
// nothing is written or queued again.

import { expect, test, type Page } from '@playwright/test';
import { signIn, sql } from './support/inventory';
import { collectFormErrors, recordRequests, type SentRequest } from './support/forms';
import { runWorker } from './support/leads';
import {
    auditsFor, BULK, box, expectBulkAction, insertManager, LIST, queuedAudits, removeBulkLooks, stored, VINS,
} from './support/bulk-edit';
import { ready } from './support/tessera';
import { insertVehicle, testVehicle } from './support/vehicles';

test.beforeEach(() => removeBulkLooks());
test.afterEach(() => removeBulkLooks());

// The headers a write is sent again with: Tessera's, the CSRF header, the body's type and the cookie the page sent.
const REPEATED = [
    'tessera-request', 'tessera-protocol', 'tessera-layout', 'tessera-targets', 'tessera-write-id', 'x-aic-csrf', 'content-type', 'cookie',
];

// Sends a recorded bulk POST again, its write id and its body unchanged, and gives the status of the answer.
async function sendAgain(page: Page, write: SentRequest): Promise<number> {
    const headers = Object.fromEntries(REPEATED.filter((name) => name in write.headers).map((name) => [name, write.headers[name]]));
    expect(headers['tessera-write-id']).toBe(write.writeId);
    const answer = await page.request.fetch(BULK, { method: 'POST', headers, data: write.body, maxRedirects: 0 });

    return answer.status();
}

test('a bulk edit queues one audit per active sales manager; sent again with its write id, nothing more happens', async ({ page }) => {
    test.setTimeout(90_000);
    const errors = collectFormErrors(page);
    insertVehicle(testVehicle(VINS.first));
    insertVehicle(testVehicle(VINS.second));
    const manager = insertManager();
    const managers = Number(sql("SELECT count(*) FROM users WHERE role = 'sales_manager' AND deactivated_on IS NULL")[0]);
    await signIn(page);
    await page.goto(LIST);
    await ready(page);
    await box(page, VINS.first).check();
    await box(page, VINS.second).check();
    const requests = await recordRequests(page);
    await page.locator('#vehicles-bulk-choice').selectOption('status:on_hold');
    await page.locator('#vehicles-bulk-apply').click();
    const write = await expectBulkAction(requests, [VINS.first, VINS.second], 200);

    // One audit per active sales manager, for the database channel alone; the look's manager's names both vehicles.
    expect(queuedAudits()).toBe(managers);
    const [audit, ...more] = auditsFor(manager);
    expect(more).toEqual([]);
    expect(audit.channel).toBe('database');
    expect(audit.subject).toBe('Bulk edit: Set 2 vehicles to On hold.');
    expect(audit.body).toContain('made a bulk edit in the inventory list. Set 2 vehicles to On hold.');
    expect(audit.body).toContain(`2023 Probe Hatch SE, VIN ${VINS.first}`);
    expect(audit.body).toContain(`2023 Probe Hatch SE, VIN ${VINS.second}`);
    expect(audit.body).toContain('Open the list: /app/inventory');

    // The same write sent again, after another change to the first vehicle: the ledger answers, nothing is applied.
    sql(`UPDATE vehicles SET status = 'in_stock' WHERE vin = '${VINS.first}'`);
    const changed = stored(VINS.first).written;
    expect(await sendAgain(page, write)).toBe(200);
    expect(stored(VINS.first).status).toBe('in_stock');
    expect(stored(VINS.first).written).toBe(changed);
    expect(queuedAudits()).toBe(managers);

    // A worker run delivers it: the manager's inbox holds the audit, linking to the list.
    expect(runWorker()).toMatch(/queue:work ran \d+ job\(s\)\./);
    const kept = sql(`SELECT subject || '|' || link FROM aic_notifications WHERE user_id = '${manager}' AND body LIKE '%${VINS.first}%'`);
    expect(kept).toEqual(['Bulk edit: Set 2 vehicles to On hold.|/app/inventory']);
    expect(errors).toEqual([]);
});
