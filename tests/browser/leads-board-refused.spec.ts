import { randomUUID } from 'node:crypto';
import { expect, test } from '@playwright/test';
import { sql } from './support/inventory';
import { collectFormErrors } from './support/forms';
import { csrfToken } from './support/photos';
import { insertLead, insertMember, moves, openBoard, removeBoardLooks, sendMove, stored } from './support/board';

test.beforeEach(() => removeBoardLooks());
test.afterEach(() => removeBoardLooks());

test('a move with no token, an unknown stage, no such lead or someone else\'s lead is refused and writes nothing; a repeat answers from the ledger', async ({ page }) => {
    const errors = collectFormErrors(page);
    const ann = insertMember('Refused Ann');
    const bo = insertMember('Refused Bo');
    const own = insertLead('Refused Own', 'new', ann);
    const theirs = insertLead('Refused Theirs', 'new', bo);
    await openBoard(page, ann);
    const token = await csrfToken(page);

    for (const method of ['POST', 'PATCH']) {
        // No token: 403.
        expect((await sendMove(page, own, { method, stage: 'contacted', token: '' })).status).toBe(403);

        // A stage that is none of the board's: 422 with the board and why.
        const unknown = await sendMove(page, own, { method, stage: 'bogus', token });
        expect(unknown.status).toBe(422);
        expect(unknown.body).toMatch(/id="leads-refusal"[^>]*>[^<]*Choose one of the board(&apos;|&#39;|')s stages\./);

        // No such lead: 404; Bo's lead, which Ann may not move: 403.
        expect((await sendMove(page, 999_999_999, { method, stage: 'contacted', token })).status).toBe(404);
        expect((await sendMove(page, theirs, { method, stage: 'contacted', token })).status).toBe(403);
    }

    expect(stored(own)).toEqual({ stage: 'new', status: 'open' });
    expect(stored(theirs)).toEqual({ stage: 'new', status: 'open' });
    expect(moves(own)).toEqual([]);
    expect(moves(theirs)).toEqual([]);

    // A move to the stage it is at already answers as a move and writes nothing.
    expect((await sendMove(page, own, { method: 'PATCH', stage: 'new', token })).status).toBe(200);
    expect(moves(own)).toEqual([]);

    // A Tessera move, then the same request again with its write id after the lead was put back by hand: the first
    // answer comes back from the ledger and nothing is written again.
    const writeId = randomUUID();
    const targets = '@leads-new, @leads-contacted';
    const first = await sendMove(page, own, { method: 'PATCH', stage: 'contacted', token, targets, writeId });
    expect(first.status).toBe(200);
    expect(moves(own)).toEqual(['new>contacted']);
    sql(`UPDATE leads SET stage = 'new' WHERE id = ${own}`);
    const again = await sendMove(page, own, { method: 'PATCH', stage: 'contacted', token, targets, writeId });
    expect(again.status).toBe(200);
    expect(again.body).toBe(first.body);
    expect(stored(own).stage).toBe('new');
    expect(moves(own)).toEqual(['new>contacted']);

    // Chrome's own line for each refused fetch above, the 422s apart, and nothing else.
    const line = (status: string): string => `Failed to load resource: the server responded with a status of ${status}`;
    const refusals = [line('403 (Forbidden)'), line('404 (Not Found)'), line('403 (Forbidden)')];
    expect(errors).toEqual([...refusals, ...refusals]);
});
