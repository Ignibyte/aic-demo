import { expect, test } from '@playwright/test';
import { fillInquiry, fillTestDrive, firstCar, inquiryThanks, lookEmail, openDay, removeLeadLooks, storedLeads, sunday } from './support/leads';

test.beforeEach(() => removeLeadLooks());
test.afterEach(() => removeLeadLooks());

test('with JavaScript off, each lead form is a plain post: refused with its errors, sent with its thank-you', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    const car = firstCar();

    // A question with an address the browser takes and the server does not.
    const visitor = { name: 'Plain Look', email: lookEmail('plain'), phone: '', message: 'Any service history?' };
    await page.goto(car.path);
    await fillInquiry(page, { ...visitor, email: 'nope@nodot' });
    const refused = page.waitForResponse((response) => response.url().endsWith(`${car.path}/inquiry`));
    await page.locator('#inquiry-send').click();
    expect((await refused).status()).toBe(422);
    await expect(page.locator('#inquiry-email-error')).toHaveText('Give an email we can reply to, such as you@example.com.');
    await expect(page.locator('#inquiry-name')).toHaveValue(visitor.name);
    await expect(page).toHaveURL(new RegExp(`${car.path}/inquiry$`));

    await fillInquiry(page, visitor);
    await page.locator('#inquiry-send').click();
    await expect(page.locator('#inquiry-thanks')).toHaveText(inquiryThanks(visitor));
    expect(storedLeads(visitor.email)).toHaveLength(1);

    // A test drive on a Sunday, then on an open day.
    const driver = { name: 'Plain Driver', email: lookEmail('plain-drive'), phone: '503-555-0101', message: 'Bringing my partner.' };
    await page.goto(car.path);
    await fillTestDrive(page, driver, sunday(), '11:00');
    await page.locator('#test-drive-send').click();
    await expect(page.locator('#test-drive-day-error')).toHaveText('We are closed on Sundays. Choose a day from Monday to Saturday.');
    await expect(page).toHaveURL(new RegExp(`${car.path}/test-drive$`));
    expect(storedLeads(driver.email)).toEqual([]);

    const day = openDay();
    await fillTestDrive(page, driver, day, '11:00');
    await page.locator('#test-drive-send').click();
    await expect(page.locator('#test-drive-thanks')).toContainText(`We have your request for ${day} at 11:00`);
    expect(storedLeads(driver.email)).toHaveLength(1);

    await context.close();
});
