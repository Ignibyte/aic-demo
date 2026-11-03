import { expect, test } from '@playwright/test';
import { collectFormErrors, recordRequests } from './support/forms';
import { card, column, expectMove, holdStream, insertLead, insertMember, moves, openBoard, removeBoardLooks, stored } from './support/board';

test.beforeEach(() => removeBoardLooks());
test.afterEach(() => removeBoardLooks());

test('a card dragged onto another column moves in the same one PATCH; dropped on its own column it sends nothing', async ({ page }) => {
    const errors = collectFormErrors(page);
    const ann = insertMember('Drag Ann');
    const lead = insertLead('Drag Look', 'contacted', ann);
    const requests = await recordRequests(page);
    await holdStream(page);
    await openBoard(page, ann);
    requests.length = 0;

    // Dropped back on Contacted, its own column: nothing is sent and nothing moves.
    await card(page, lead).dragTo(column(page, 'contacted'));
    await page.waitForTimeout(500);
    expect(requests).toEqual([]);
    expect(stored(lead)).toEqual({ stage: 'contacted', status: 'open' });

    // Dropped on Appointment: one PATCH naming the two columns, the card in its new column, the move stored.
    await card(page, lead).dragTo(column(page, 'appointment'));
    await expectMove(requests, lead, 'contacted', 'appointment');
    await expect(column(page, 'appointment').locator(`#lead-card-${lead}`)).toHaveCount(1);
    await expect(column(page, 'contacted').locator(`#lead-card-${lead}`)).toHaveCount(0);
    await expect(page.locator(`#lead-move-${lead}`)).toBeFocused();
    expect(stored(lead)).toEqual({ stage: 'appointment', status: 'open' });
    expect(moves(lead)).toEqual(['contacted>appointment']);
    expect(errors).toEqual([]);
});
