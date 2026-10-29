import { expect, test } from '@playwright/test';
import { collectFormErrors, recordRequests } from './support/forms';
import { documentKept, markDocument } from './support/storefront';
import { fillTestDrive, firstCar, lookEmail, openDay, removeLeadLooks, storedLeads, sunday, today } from './support/leads';

test.beforeEach(() => removeLeadLooks());
test.afterEach(() => removeLeadLooks());

test('a test drive for today, a Sunday or after hours is refused in place; one in opening hours is thanked', async ({ page }) => {
    const errors = collectFormErrors(page);
    const car = firstCar();
    const visitor = { name: 'Drive Look', email: lookEmail('drive'), phone: '(503) 555-0170', message: '' };
    await page.goto(car.path);
    await markDocument(page);
    const requests = await recordRequests(page);

    // Each refusal is a 422 that Tessera swaps into the test drive's panel alone, with the reason beside its field.
    const refusals: [string, string, string, string][] = [
        [today(), '10:00', '#test-drive-day-error', 'Choose a day from tomorrow on.'],
        [sunday(), '10:00', '#test-drive-day-error', 'We are closed on Sundays. Choose a day from Monday to Saturday.'],
        [openDay(), '20:00', '#test-drive-time-error', 'Choose a time from 09:00 to 17:00. We are open 09:00 to 18:00, Monday to Saturday.'],
    ];

    for (const [day, time, field, reason] of refusals) {
        requests.length = 0;
        await fillTestDrive(page, visitor, day, time);
        await page.locator('#test-drive-send').click();
        await expect(page.locator(field)).toHaveText(reason);
        await expect(page.locator('#test-drive-error')).toBeVisible();
        const [submit] = requests.filter((request) => request.method === 'POST');
        expect(submit).toMatchObject({ path: `${car.path}/test-drive`, tesseraRequest: 'submit', status: 422 });
        expect(submit.targets).toContain('@car-test-drive');
        expect(submit.templates).toHaveLength(1);
        expect(await documentKept(page)).toBe(true);
        expect(storedLeads(visitor.email)).toEqual([]);
    }

    // In opening hours it is thanked, and stored with its day and time.
    const day = openDay();
    await fillTestDrive(page, visitor, day, '10:30');
    await page.locator('#test-drive-send').click();
    await expect(page.locator('#test-drive-thanks')).toHaveText(
        `Thanks, ${visitor.name}. We have your request for ${day} at 10:30 and will call ${visitor.phone} to confirm it.`,
    );
    expect(await documentKept(page)).toBe(true);
    const leads = storedLeads(visitor.email);
    expect(leads).toHaveLength(1);
    expect(leads[0]).toMatchObject({ kind: 'test_drive', vehicleId: car.id, driveOn: day, driveAt: '10:30', status: 'open' });

    expect(errors).toEqual([]);
});
