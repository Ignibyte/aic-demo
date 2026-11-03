import { expect, test } from '@playwright/test';
import { literal, sql } from './support/inventory';
import { collectFormErrors } from './support/forms';
import { firstCar, runWorker } from './support/leads';
import { csrfToken } from './support/photos';
import { insertLead, insertMember, openBoard, removeBoardLooks, sendMove, stored, wonJobs } from './support/board';

test.beforeEach(() => removeBoardLooks());
test.afterEach(() => removeBoardLooks());

// The notifications a member holds: subject, link and body, oldest first.
function notices(userId: string): { subject: string; link: string; body: string }[] {
    return sql(`SELECT subject || '|' || link || '|' || replace(body, E'\\n', ' / ') FROM aic_notifications WHERE user_id = ${literal(userId)} ORDER BY id`)
        .map((line) => {
            const [subject, link, body] = line.split('|');

            return { subject, link, body };
        });
}

// This look runs the worker on this host (`$AIC_PHP bin/aic queue:work`, AIC #225), as the leads looks do, against the
// database the site under test uses.
test('a move to Won queues one lead-won job, whose run tells the salesperson in their inbox; no salesperson, or off Won first, tells no one', async ({ page }) => {
    const errors = collectFormErrors(page);
    const car = firstCar();
    const manager = insertMember('Won Manager', 'sales_manager');
    const ann = insertMember('Won Ann');
    const bo = insertMember('Won Bo');
    const won = insertLead('Won Look', 'appointment', ann);
    const nobody = insertLead('Won Nobody', 'appointment', null);
    const back = insertLead('Won Back', 'appointment', bo);
    await openBoard(page, manager);
    const token = await csrfToken(page);

    // Ann's lead to Won: one job, in the move's transaction, beside the stage.
    expect((await sendMove(page, won, { method: 'PATCH', stage: 'won', token })).status).toBe(200);
    expect(stored(won)).toEqual({ stage: 'won', status: 'closed' });
    expect(wonJobs(won)).toBe(1);

    // The unassigned lead to Won: no job. Bo's to Won, then to Lost before the worker runs.
    expect((await sendMove(page, nobody, { method: 'PATCH', stage: 'won', token })).status).toBe(200);
    expect(wonJobs(nobody)).toBe(0);
    expect((await sendMove(page, back, { method: 'PATCH', stage: 'won', token })).status).toBe(200);
    expect((await sendMove(page, back, { method: 'PATCH', stage: 'lost', token })).status).toBe(200);
    expect(wonJobs(back)).toBe(1);

    // One worker run: Ann holds the one notification, linking to her lead and naming who moved it; Bo holds none.
    expect(runWorker()).toMatch(/queue:work ran \d+ job\(s\)\./);
    expect(notices(ann.id)).toEqual([{
        subject: `Won: Won Look on the ${car.title}`,
        link: `/app/leads/${won}`,
        body: `Won Manager moved the lead from Won Look about the ${car.title} to Won on the leads board. /  / Open the lead: /app/leads/${won}`,
    }]);
    expect(notices(bo.id)).toEqual([]);
    expect(wonJobs(won)).toBe(0);
    expect(errors).toEqual([]);
});
