import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { csrfToken } from './support/photos';
import { STAGES, insertLead, insertMember, newestMove, openBoard, readBoard, removeBoardLooks, sendMove } from './support/board';

test.beforeEach(() => removeBoardLooks());
test.afterEach(() => removeBoardLooks());

test('a fresh stream sends every column; a resumed one only the columns later moves touched, each once; one at the newest move none', async ({ page }) => {
    const errors = collectFormErrors(page);
    const ann = insertMember('Resume Ann');
    const lead = insertLead('Resume Look', 'new', ann);
    await openBoard(page, ann);
    const token = await csrfToken(page);

    // Fresh: one inner patch per column in board order, the last carrying the newest move's id.
    const start = newestMove();
    const fresh = await readBoard(page, '', 5);
    expect(fresh.map((event) => [event.target, event.mode])).toEqual(STAGES.map((stage) => [`@leads-${stage}`, 'inner']));
    expect(fresh.map((event) => event.id)).toEqual(['', '', '', '', String(start)]);
    expect(fresh[0].elements).toContain(`id="lead-card-${lead}"`);
    expect(fresh[0].elements).toContain('id="leads-new-count"');
    expect(fresh[0].elements).toMatch(/^<div class="flex flex-col gap-2"><h3 /);

    // Two moves, New to Contacted to Won, then a resume from before them: New, Contacted and Won once each, in board
    // order, the last with the newest id; Won holds the card.
    for (const stage of ['contacted', 'won']) {
        expect((await sendMove(page, lead, { method: 'PATCH', stage, token })).status).toBe(200);
    }

    const newest = newestMove();
    const resumed = await readBoard(page, String(start), 5);
    expect(resumed.map((event) => [event.target, event.mode, event.id])).toEqual([
        ['@leads-new', 'inner', ''],
        ['@leads-contacted', 'inner', ''],
        ['@leads-won', 'inner', String(newest)],
    ]);
    expect(resumed[2].elements).toContain(`id="lead-card-${lead}"`);
    expect(resumed[0].elements).not.toContain(`id="lead-card-${lead}"`);

    // From the newest move: no patch before the first keep-alive.
    expect(await readBoard(page, String(newest), 5)).toEqual([]);
    expect(errors).toEqual([]);
});
