import { expect, test } from '@playwright/test';
import { literal, sql } from './support/inventory';
import { collectFormErrors } from './support/forms';
import { fillInquiry, firstCar, inquiryThanks, lookEmail, mailFor, mailText, nextSeller, removeLeadLooks, runWorker, storedLeads } from './support/leads';

test.beforeEach(() => removeLeadLooks());
test.afterEach(() => removeLeadLooks());

// These looks run the worker on this host (`$AIC_PHP bin/aic queue:work`, AIC #225), mailing into DDEV's Mailpit
// through the SMTP port DDEV publishes, and read Mailpit at the address `ddev describe -j` gives.
test('sending a lead queues its job and no mail; one worker run mails the salesperson and the visitor and stores the notification', async ({ page }) => {
    const errors = collectFormErrors(page);
    const car = firstCar();
    const seller = nextSeller();
    expect(seller).not.toBe('');
    const visitor = { name: 'Notice Look', email: lookEmail('notice'), phone: '503 555 0188', message: 'Can I see it on Saturday?' };

    await page.goto(car.path);
    await fillInquiry(page, visitor);
    await page.locator('#inquiry-send').click();
    await expect(page.locator('#inquiry-thanks')).toHaveText(inquiryThanks(visitor));
    const [lead] = storedLeads(visitor.email);

    // The request queued the job and sent nothing.
    expect(sql(`SELECT count(*) FROM aic_jobs WHERE job = 'lead-notices' AND payload::jsonb ->> 'lead' = '${lead.id}'`)).toEqual(['1']);
    expect(await mailFor(visitor.email)).toEqual([]);

    // One worker run delivers both mails, each to its address, and keeps the salesperson's notification.
    expect(runWorker()).toMatch(/queue:work ran \d+ job\(s\)\./);
    const mails = await mailFor(visitor.email);
    const toVisitor = mails.filter((mail) => mail.to.includes(visitor.email));
    const toSeller = mails.filter((mail) => mail.to.includes(seller));
    expect(toVisitor.map((mail) => mail.subject)).toEqual([`Your question about the ${car.title}`]);
    expect(toSeller.map((mail) => mail.subject)).toEqual([`New question about the ${car.title}`]);
    expect(mails).toHaveLength(2);
    const sellerText = await mailText(toSeller[0]);
    expect(sellerText).toContain(`Email: ${visitor.email}`);
    expect(sellerText).toContain(visitor.message);
    expect(sellerText).toContain(`/app/leads/${lead.id}`);

    const sellerId = sql(`SELECT id FROM users WHERE email = ${literal(seller)}`)[0];
    const stored = sql(
        `SELECT subject FROM aic_notifications WHERE user_id = ${literal(sellerId)} AND body LIKE ${literal(`%${visitor.email}%`)}`,
    );
    expect(stored).toEqual([`New question about the ${car.title}`]);
    expect(sql(`SELECT count(*) FROM aic_notifications WHERE body LIKE ${literal(`%Hello ${visitor.name}%`)}`)).toEqual(['0']);

    // The worker's notices are delivered: nothing of this lead is left in the queue.
    expect(sql(`SELECT count(*) FROM aic_jobs WHERE payload LIKE ${literal(`%${visitor.email}%`)}`)).toEqual(['0']);

    sql(`DELETE FROM aic_notifications WHERE body LIKE ${literal(`%${visitor.email}%`)}`);
    expect(errors).toEqual([]);
});
