import { expect, test } from '@playwright/test';
import { literal, sql } from './support/inventory';
import { collectFormErrors } from './support/forms';
import { signInAs } from './support/photos';
import { mailFor } from './support/leads';
import { slugOf } from './support/storefront';
import { databaseNow, insertLookVehicle, insertMember, notificationsOf, removeHoldLooks, storedHolds, VINS, vehicleStatus } from './support/holds';
import { runSchedule, unusedMinute, work } from './support/schedules';

let since = '';

test.beforeEach(async () => {
    await removeHoldLooks();
    since = databaseNow();
});
test.afterEach(() => removeHoldLooks(since));

// These looks run the scheduler and the worker on this host (`$AIC_PHP bin/aic`, AIC #225), mailing into DDEV's Mailpit
// through the SMTP port DDEV publishes, and read Mailpit at the address `ddev describe -j` gives.
test('a held car leaves the public site and sells to no one else; after its 48 hours the release job puts it back and tells who placed it', async ({ page, request }) => {
    const errors = collectFormErrors(page);
    const salesperson = insertMember('Hal Holder', 'salesperson');
    const vehicleId = insertLookVehicle(VINS.held, 'in_stock', 10);
    const title = '2023 Probe Hatch SE';
    const carPath = `/cars/${slugOf(VINS.held)}`;

    // In stock, the car is on the public list and its page.
    await page.goto('/cars?make=Probe');
    await expect(page.locator(`a[href="${carPath}"]`).first()).toBeVisible();
    expect((await page.goto(carPath))?.status()).toBe(200);

    // The salesperson holds it for Ada through the form, the vehicle chosen from its page's link.
    await signInAs(page, salesperson.email, salesperson.password);
    const holdsLink = page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Holds' });
    await holdsLink.click();
    await expect(page).toHaveURL(/\/app\/holds$/);
    await expect(holdsLink).toHaveAttribute('aria-current', 'page');
    await page.goto(`/app/holds/new?vehicle=${VINS.held}`);
    await expect(page.locator('#hold-vehicle')).toHaveValue(VINS.held);

    // A blank customer and note are refused in place, beside their fields, and nothing is held.
    await page.locator('#hold-save').click();
    await expect(page.locator('#hold-customer-error')).toHaveText('Choose the customer.');
    await expect(page.locator('#hold-note-error')).toHaveText('Say why the vehicle is held, such as when the customer will come.');
    expect(storedHolds(VINS.held)).toEqual([]);

    await page.locator('#hold-customer').selectOption('ada.abbott@example.test');
    await page.locator('#hold-note').fill('Coming Saturday with her trade-in');
    await page.locator('#hold-save').click();
    await expect(page).toHaveURL(/\/app\/holds$/);
    await expect(page.locator('#holds-notice')).toHaveText(`Put the ${title} on hold for Ada Abbott for 48 hours.`);

    const [hold] = storedHolds(VINS.held);
    expect(hold).toMatchObject({ status: 'active', customer: 'ada.abbott@example.test', placedBy: salesperson.email, note: 'Coming Saturday with her trade-in', hoursLeft: 48 });
    expect(vehicleStatus(VINS.held)).toBe('on_hold');
    const row = page.locator('#holds-table tbody tr', { hasText: title });
    await expect(row).toContainText('Ada Abbott');
    await expect(row).toContainText('Hal Holder');
    await expect(row).toContainText(hold.expires);
    await expect(row.locator(`a[href="/app/inventory/${VINS.held}"]`)).toBeVisible();

    // Held, it leaves the public list and its page answers 404.
    await page.goto('/cars?make=Probe');
    await expect(page.locator(`a[href="${carPath}"]`)).toHaveCount(0);
    // Asked through the test's own request context, so the browser logs no failed load and the page keeps its session
    // cookie: `page.request` shares the page's cookies, and the compiled site's answer would replace them.
    expect((await request.get(carPath)).status()).toBe(404);

    // A sale to Ben is refused beside the vehicle, and nothing is sold.
    await page.goto(`/app/sales/new?vehicle=${VINS.held}`);
    await page.locator('#sale-customer').selectOption('ben.duarte@example.test');
    await page.locator('#sale-salesperson').selectOption(salesperson.email);
    await page.locator('#sale-save').click();
    await expect(page.locator('#sale-vehicle-error')).toHaveText('This vehicle is on hold for another customer.');
    expect(sql(`SELECT count(*) FROM sales WHERE vehicle_id = ${vehicleId}`)).toEqual(['0']);

    // The release job leaves a hold whose time has not passed.
    expect(runSchedule(unusedMinute('03:15'))).toBe('schedule:run queued 1 job(s).');
    work();
    expect(storedHolds(VINS.held)[0].status).toBe('active');

    // Once its 48 hours have passed, the 15-minute schedule's job releases it.
    sql(`UPDATE holds SET created_at = created_at - interval '49 hours' WHERE id = ${hold.id}`);
    expect(runSchedule(unusedMinute('03:45'))).toBe('schedule:run queued 1 job(s).');
    expect(work()).toMatch(/^queue:work ran \d+ job\(s\)\.$/);
    expect(storedHolds(VINS.held)[0].status).toBe('released');
    expect(vehicleStatus(VINS.held)).toBe('in_stock');

    // The car is back on the public list and its page, and off the holds list.
    await page.goto('/cars?make=Probe');
    await expect(page.locator(`a[href="${carPath}"]`).first()).toBeVisible();
    expect((await page.goto(carPath))?.status()).toBe(200);
    await page.goto('/app/holds');
    await expect(page.locator('#holds-table tbody tr', { hasText: title })).toHaveCount(0);

    // The salesperson who placed it is told, in the database and by mail.
    const subject = `The hold on the ${title} for Ada Abbott has ended`;
    const notices = notificationsOf(salesperson);
    expect(notices.map((notice) => notice.subject)).toEqual([subject]);
    expect(notices[0].link).toBe(`/app/inventory/${VINS.held}`);
    expect(notices[0].body).toContain(`Open the car: /app/inventory/${VINS.held}`);
    const mails = await mailFor(salesperson.email);
    expect(mails.map((mail) => mail.subject)).toEqual([subject]);
    expect(sql(`SELECT count(*) FROM aic_jobs WHERE payload LIKE ${literal(`%${salesperson.email}%`)}`)).toEqual(['0']);
    expect(errors).toEqual([]);
});
