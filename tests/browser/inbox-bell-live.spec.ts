import { expect, test } from '@playwright/test';
import { literal, sql } from './support/inventory';
import { collectFormErrors } from './support/forms';
import { fillInquiry, firstCar, inquiryThanks, runWorker, storedLeads } from './support/leads';
import { expectBellCount, insertMember, isRead, lookEmail, removeInboxLooks, signInMember, turnTo } from './support/inbox';

test.beforeEach(() => removeInboxLooks());
test.afterEach(() => removeInboxLooks());

// This look runs the worker on this host (`$AIC_PHP bin/aic queue:work`, AIC #225), as the leads looks do, against the
// database the site under test uses.
test("a visitor's lead lights the salesperson's bell live, and opening it from the menu marks it read", async ({ browser, page }) => {
    const errors = collectFormErrors(page);
    const car = firstCar();
    const member = insertMember('live');
    turnTo(member, car.id);

    // The salesperson's dashboard, with nothing in the bell.
    await signInMember(page, member);
    await expectBellCount(page, 0);
    await page.locator('#bell').click();
    await expect(page.locator('#bell-empty')).toBeVisible();
    await page.evaluate(() => {
        (window as unknown as { inboxLook: string }).inboxLook = 'same document';
    });

    // A visitor asks about the car in another browser, and one worker run delivers the notification.
    const visitorContext = await browser.newContext();
    const visitorPage = await visitorContext.newPage();
    const visitor = { name: 'Bell Look', email: lookEmail('visitor'), phone: '', message: 'Is it still here?' };
    await visitorPage.goto(car.path);
    await fillInquiry(visitorPage, visitor);
    await visitorPage.locator('#inquiry-send').click();
    await expect(visitorPage.locator('#inquiry-thanks')).toHaveText(inquiryThanks(visitor));
    await visitorContext.close();
    const [lead] = storedLeads(visitor.email);
    expect(lead.seller).toBe(member.email);
    expect(runWorker()).toMatch(/queue:work ran \d+ job\(s\)\./);
    const [notification] = sql(`SELECT id FROM aic_notifications WHERE user_id = ${literal(member.id)}`);

    // The bell lights in the same document: the count, and the notification first in the menu.
    await expectBellCount(page, 1);
    const first = page.locator('#bell-items > li').first();
    await expect(first).toHaveId(`bell-item-${notification}`);
    await expect(first).toContainText(`New question about the ${car.title}`);
    await expect(page.locator('#bell-empty')).toBeHidden();
    expect(await page.evaluate(() => (window as unknown as { inboxLook?: string }).inboxLook)).toBe('same document');

    // Opening it from the menu, still open, lands on the lead's page, marks it read and drops the count.
    await first.locator('a').click();
    await expect(page).toHaveURL(new RegExp(`/app/leads/${lead.id}$`));
    await expectBellCount(page, 0);
    expect(isRead(Number(notification))).toBe(true);
    expect(errors).toEqual([]);
});
