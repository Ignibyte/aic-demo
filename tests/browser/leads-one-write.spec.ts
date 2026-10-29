import { expect, test } from '@playwright/test';
import { collectFormErrors, recordRequests } from './support/forms';
import { fillInquiry, firstCar, inquiryThanks, lookEmail, removeLeadLooks, storedLeads } from './support/leads';

test.beforeEach(() => removeLeadLooks());
test.afterEach(() => removeLeadLooks());

// The headers a submit is sent again with, as `form-one-write.spec.ts` sends them.
const REPEATED = ['tessera-request', 'tessera-protocol', 'tessera-layout', 'tessera-targets', 'tessera-write-id', 'x-aic-csrf', 'content-type', 'cookie'];

test('a double click on Send records one lead, and a repeat of its write id is answered from the ledger', async ({ page }) => {
    const errors = collectFormErrors(page);
    const car = firstCar();
    const visitor = { name: 'Twice Look', email: lookEmail('twice'), phone: '', message: 'Sent twice?' };
    await page.goto(car.path);
    const requests = await recordRequests(page);
    await fillInquiry(page, visitor);

    await page.locator('#inquiry-send').dblclick();
    await expect(page.locator('#inquiry-thanks')).toHaveText(inquiryThanks(visitor));
    await page.waitForTimeout(500);
    const submits = requests.filter((request) => request.method === 'POST');
    expect(submits.length).toBeGreaterThan(0);
    expect(new Set(submits.map((submit) => submit.writeId)).size).toBe(1);
    expect(submits[0].writeId).not.toBe('');
    expect(storedLeads(visitor.email)).toHaveLength(1);

    // The same submit again, its write id and body unchanged, gets the first answer and records nothing more.
    const [submit] = submits;
    const headers = Object.fromEntries(REPEATED.filter((name) => name in submit.headers).map((name) => [name, submit.headers[name]]));
    const answer = await page.request.post(submit.path, { headers, data: submit.body, maxRedirects: 0 });
    expect(answer.status()).toBe(200);
    expect(await answer.text()).toContain('id="inquiry-thanks"');
    expect(storedLeads(visitor.email)).toHaveLength(1);

    expect(errors).toEqual([]);
});
