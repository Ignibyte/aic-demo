import { expect, test } from '@playwright/test';
import { literal, sql } from './support/inventory';
import { mailFor, mailText } from './support/leads';
import { databaseNow, DOMAIN, insertLookVehicle, insertMember, removeHoldLooks, VINS } from './support/holds';
import { drain, runSchedule, unusedMinute, work } from './support/schedules';

let since = '';

test.beforeEach(async () => {
    await removeHoldLooks();
    since = databaseNow();
});
test.afterEach(() => removeHoldLooks(since));

// One figure of the database's today, counted here without the app's view.
function figure(statement: string): number {
    return Number(sql(statement)[0]);
}

// These looks run the scheduler and the worker on this host (`$AIC_PHP bin/aic`, AIC #225), mailing into DDEV's Mailpit
// through the SMTP port DDEV publishes, and read Mailpit through its HTTP API at the address `ddev describe -j` gives.
test("the 21:00 schedule mails each manager the day's sales, leads and new stock, as the database counts them", async () => {
    const manager = insertMember('Dee Digest', 'sales_manager');
    const admin = insertMember('Ada Admin', 'admin');
    const salesperson = insertMember('Sol Seller', 'salesperson');

    // Today's own: a car that came onto the lot, a car sold, a question and a test drive request.
    insertLookVehicle(VINS.today, 'in_stock', 0, 'Today');
    const soldId = insertLookVehicle(VINS.sold90, 'sold', 30, 'Sold');
    const [customerId] = sql("SELECT id FROM customers WHERE email = 'ada.abbott@example.test'");
    sql(`INSERT INTO sales (vehicle_id, customer_id, salesperson_id, price, sold_on, created_at) VALUES (${soldId}, ${customerId}, ${salesperson.id}, 18250, current_date, now())`);
    for (const kind of ['inquiry', 'test_drive']) {
        sql(
            'INSERT INTO leads (vehicle_id, kind, name, email, phone, message, status, created_at) VALUES '
                + `(${soldId}, ${literal(kind)}, 'Digest Look', ${literal(`${kind}${DOMAIN}`)}, '', 'Hello', 'open', now())`,
        );
    }
    drain();

    expect(runSchedule(unusedMinute('21:00'))).toBe('schedule:run queued 2 job(s).');
    work();

    // The database's own figures for today.
    const today = sql('SELECT current_date::text')[0];
    const sales = figure('SELECT count(*) FROM sales WHERE sold_on = current_date');
    const revenue = figure('SELECT coalesce(sum(price), 0) FROM sales WHERE sold_on = current_date');
    const leads = figure('SELECT count(*) FROM leads WHERE created_at::date = current_date');
    const inquiries = figure("SELECT count(*) FROM leads WHERE created_at::date = current_date AND kind = 'inquiry'");
    const testDrives = figure("SELECT count(*) FROM leads WHERE created_at::date = current_date AND kind = 'test_drive'");
    const newStock = figure('SELECT count(*) FROM vehicles WHERE added_on = current_date');
    expect(sales).toBeGreaterThanOrEqual(1);
    expect(inquiries).toBeGreaterThanOrEqual(1);
    expect(testDrives).toBeGreaterThanOrEqual(1);
    expect(newStock).toBeGreaterThanOrEqual(1);

    const plural = (count: number, one: string, many: string): string => `${count} ${count === 1 ? one : many}`;
    const subject = `Ridgeway Motors on ${today}: ${plural(sales, 'sale', 'sales')}, ${plural(leads, 'lead', 'leads')}, ${newStock} new on the lot`;

    // Each manager with an address gets one digest, the sales manager and the admin alike; the salesperson none.
    for (const member of [manager, admin]) {
        const mails = await mailFor(member.email);
        expect(mails.map((mail) => mail.subject)).toEqual([subject]);
        const lines = (await mailText(mails[0])).split(/\r?\n/);
        expect(lines).toContain(`Sales: ${sales}, $${revenue.toLocaleString('en-US')} in all`);
        expect(lines).toContain(`Leads: ${leads} (${plural(inquiries, 'question', 'questions')}, ${plural(testDrives, 'test drive', 'test drives')})`);
        expect(lines).toContain(`New stock: ${plural(newStock, 'vehicle', 'vehicles')}`);
    }
    expect(await mailFor(salesperson.email)).toEqual([]);

    // It is mail alone: nothing is kept in an inbox.
    expect(sql(`SELECT count(*) FROM aic_notifications WHERE user_id IN ('${manager.id}', '${admin.id}')`)).toEqual(['0']);
});
