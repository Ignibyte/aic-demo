import { expect, test } from '@playwright/test';
import { signIn } from './support/inventory';
import { collectFormErrors } from './support/forms';
import { csrfToken, fetchInPage, signInAs } from './support/photos';
import { DOMAIN, fillInquiry, firstCar, inquiryThanks, insertStaff, lookEmail, nextSeller, removeLeadLooks, storedLeads } from './support/leads';

test.beforeEach(() => removeLeadLooks());
test.afterEach(() => removeLeadLooks());

test('leads go to the salespeople in turn; another salesperson may not move one; a manager reassigns and its salesperson closes it', async ({ browser, page }) => {
    const errors = collectFormErrors(page);
    const car = firstCar();
    const ann = `ann${DOMAIN}`;
    const bo = `bo${DOMAIN}`;
    const annPassword = insertStaff(ann, 'Ann Look', 'salesperson');
    const boPassword = insertStaff(bo, 'Bo Look', 'salesperson');

    // Three questions, each to the salesperson whose turn it is, and no two in a row to the same one.
    const sent: { email: string; seller: string }[] = [];

    for (const n of [1, 2, 3]) {
        const seller = nextSeller();
        const visitor = { name: `Turn Look ${n}`, email: lookEmail(`turn${n}`), phone: '', message: `Question ${n}` };
        await page.goto(car.path);
        await fillInquiry(page, visitor);
        await page.locator('#inquiry-send').click();
        await expect(page.locator('#inquiry-thanks')).toHaveText(inquiryThanks(visitor));
        expect(storedLeads(visitor.email).map((lead) => lead.seller)).toEqual([seller]);
        sent.push({ email: visitor.email, seller });
    }

    expect(sent[1].seller).not.toBe(sent[0].seller);
    expect(sent[2].seller).not.toBe(sent[1].seller);

    // The first lead goes to Ann through the database, so a look-owned salesperson holds it.
    const [lead] = storedLeads(sent[0].email);
    const path = `/app/leads/${lead.id}`;
    await signIn(page);
    await page.goto(path);
    await page.locator('#lead-salesperson').selectOption(ann);
    await page.locator('#lead-assign').click();
    await expect(page.locator('#lead-notice')).toHaveText('Assigned this lead to Ann Look.');
    expect(storedLeads(sent[0].email)[0].seller).toBe(ann);

    // Bo, another salesperson, reads it but sees no form, and his posts answer 403.
    const boContext = await browser.newContext();
    const boPage = await boContext.newPage();
    await signInAs(boPage, bo, boPassword);
    await boPage.goto(path);
    await expect(boPage.locator('#lead-title')).toHaveText('Turn Look 1');
    await expect(boPage.locator('#lead-assign-form')).toHaveCount(0);
    await expect(boPage.locator('#lead-close-form')).toHaveCount(0);
    // Posted from the page's own fetch(), since page.request sends no Secure cookie to the compiled site over plain HTTP.
    const token = await csrfToken(boPage);
    for (const action of ['assign', 'close']) {
        const answer = await fetchInPage(boPage, `${path}/${action}`, { method: 'POST', fields: { salesperson: bo, aic_csrf: token } });
        expect(answer.status).toBe(403);
    }
    expect(storedLeads(sent[0].email)[0]).toMatchObject({ seller: ann, status: 'open' });
    await boContext.close();

    // The manager (the gate's admin) may reassign it, but not to a choice that is no salesperson (422).
    await page.goto(path);
    await page.locator('#lead-salesperson').selectOption('');
    await page.locator('#lead-assign').click();
    await expect(page.locator('#lead-salesperson-error')).toHaveText('Choose a salesperson on the list.');

    // Ann, its salesperson, closes it: it leaves the open list.
    const annContext = await browser.newContext();
    const annPage = await annContext.newPage();
    await signInAs(annPage, ann, annPassword);
    await annPage.goto(path);
    await annPage.locator('#lead-close').click();
    await expect(annPage).toHaveURL(/\/app\/leads$/);
    await expect(annPage.locator('#leads-notice')).toHaveText('Closed the lead from Turn Look 1.');
    await expect(annPage.locator('#leads-table tbody tr', { hasText: 'Turn Look 1' })).toHaveCount(0);
    await expect(annPage.locator('#leads-table tbody tr', { hasText: 'Turn Look 2' })).toHaveCount(1);
    expect(storedLeads(sent[0].email)[0].status).toBe('closed');

    // A closed lead shows Closed and no forms, and a move answers 409.
    await annPage.goto(path);
    await expect(annPage.locator('#lead-status')).toHaveText('Closed');
    await expect(annPage.locator('#lead-close-form')).toHaveCount(0);
    const closed = await fetchInPage(annPage, `${path}/close`, { method: 'POST', fields: { aic_csrf: await csrfToken(annPage) } });
    expect(closed.status).toBe(409);
    await annContext.close();

    expect(errors).toEqual([]);
});
