import { expect, test } from '@playwright/test';
import { sql } from './support/inventory';
import { mailFor, mailText } from './support/leads';
import { signInAs } from './support/photos';
import { databaseNow, insertLookVehicle, insertMember, notificationsOf, removeHoldLooks, VINS } from './support/holds';
import { drain, runSchedule, unusedMinute, work } from './support/schedules';

let since = '';

test.beforeEach(async () => {
    await removeHoldLooks();
    since = databaseNow();
});
test.afterEach(() => removeHoldLooks(since));

// The vehicles the database says are aged: not sold, on the lot more than 60 days (config/reports.php), longest first,
// as the notification lists them.
function agedLines(): string[] {
    return sql(
        "SELECT '- ' || trim(year || ' ' || make || ' ' || model || ' ' || trim) || ', VIN ' || vin || ', ' || (current_date - added_on) "
            + "|| ' days, $' || to_char(list_price, 'FM9,999,999') FROM vehicles WHERE status <> 'sold' AND current_date - added_on > 60 "
            + 'ORDER BY current_date - added_on DESC, vin ASC',
    );
}

// These looks depend on DDEV: the scheduler and the worker run in its web container (`ddev exec`), which reaches
// Mailpit's SMTP, and Mailpit is read at the address `ddev describe -j` gives.
test('the 07:00 schedule lists the vehicles on the lot more than 60 days in one notification to each sales manager', async ({ page }) => {
    const manager = insertMember('Mia Manager', 'sales_manager');
    const salesperson = insertMember('Sam Seller', 'salesperson');
    insertLookVehicle(VINS.aged61, 'in_stock', 61, 'Aged61');
    insertLookVehicle(VINS.aged60, 'in_stock', 60, 'Aged60');
    insertLookVehicle(VINS.aged75, 'on_hold', 75, 'Aged75');
    insertLookVehicle(VINS.sold90, 'sold', 90, 'Sold90');
    drain();

    // The morning's minute queues the aged-stock job (and the 15-minute release, due at :00 too); one worker runs it.
    expect(runSchedule(unusedMinute('07:00'))).toBe('schedule:run queued 2 job(s).');
    work();

    // One notification, its lines the database's aged vehicles exactly: the 61- and 75-day cars in, the 60-day and the
    // sold one out.
    const lines = agedLines();
    const subject = `${lines.length} vehicles on the lot more than 60 days`;
    const notices = notificationsOf(manager);
    expect(notices.map((notice) => notice.subject)).toEqual([subject]);
    expect(notices[0].link).toBe('/app/inventory?sort=added');
    const listed = notices[0].body.split('\n').filter((line) => line.startsWith('- '));
    expect(listed).toEqual(lines);
    expect(listed).toContain(`- 2023 Probe Aged61 SE, VIN ${VINS.aged61}, 61 days, $17,900`);
    expect(listed).toContain(`- 2023 Probe Aged75 SE, VIN ${VINS.aged75}, 75 days, $17,900`);
    expect(listed.join('\n')).not.toContain(VINS.aged60);
    expect(listed.join('\n')).not.toContain(VINS.sold90);

    // Every sales manager got it once; a salesperson did not.
    const managers = sql("SELECT id FROM users WHERE role = 'sales_manager'");
    for (const id of managers) {
        expect(sql(`SELECT count(*) FROM aic_notifications WHERE user_id = '${id}' AND subject = '${subject}' AND created_at >= '${since}'`)).toEqual(['1']);
    }
    expect(notificationsOf(salesperson)).toEqual([]);

    // It was mailed too, with the same list.
    const mails = await mailFor(manager.email);
    expect(mails.map((mail) => mail.subject)).toEqual([subject]);
    expect((await mailText(mails[0])).split(/\r?\n/).filter((line) => line.startsWith('- '))).toEqual(lines);

    // The manager's inbox shows it; opening it lands on the inventory, oldest first.
    await signInAs(page, manager.email, manager.password);
    await page.goto('/app/inbox');
    const rows = page.locator('#inbox-table tbody tr');
    await expect(rows).toHaveCount(1);
    await rows.getByRole('link', { name: subject }).click();
    await expect(page).toHaveURL(/\/app\/inventory\?sort=added$/);
});
