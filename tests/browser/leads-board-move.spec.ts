import { expect, test } from '@playwright/test';
import { sql } from './support/inventory';
import { collectFormErrors, recordRequests } from './support/forms';
import {
    STAGES,
    card,
    column,
    countAt,
    expectCount,
    expectMove,
    holdStream,
    insertLead,
    insertMember,
    kept,
    markBoard,
    moves,
    openBoard,
    removeBoardLooks,
    stored,
} from './support/board';

test.beforeEach(() => removeBoardLooks());
test.afterEach(() => removeBoardLooks());

test('a card moved by its menu goes in one PATCH naming its two columns, which come back alone with their counts, and the moved card takes focus', async ({ page }) => {
    const errors = collectFormErrors(page);
    const manager = insertMember('Move Manager', 'sales_manager');
    const ann = insertMember('Move Ann');
    const lead = insertLead('Move Look', 'new', ann);
    const beside = insertLead('Move Beside', 'new', ann);
    const requests = await recordRequests(page);
    await holdStream(page);
    await openBoard(page, manager);
    await markBoard(page);
    requests.length = 0;

    // "Move to" opens the menu; a click outside it closes it.
    const menu = page.locator(`#lead-menu-${lead}`);
    await page.locator(`#lead-move-${lead}`).click();
    await expect(menu).toHaveAttribute('open', '');
    await expect(page.locator(`#lead-go-contacted-${lead}`)).toBeVisible();
    await page.locator('#leads-heading').click();
    await expect(menu).not.toHaveAttribute('open');

    // Contacted, the answer held: the button carries data-loading and the card is dimmed while it runs.
    let release = (): void => {};
    const held = new Promise<void>((resolve) => {
        release = resolve;
    });
    await page.route((url) => url.pathname === `/app/leads/${lead}/stage`, async (route) => {
        await held;
        await route.fallback();
    });
    const newCount = countAt('new');
    const contactedCount = countAt('contacted');
    await page.locator(`#lead-move-${lead}`).click();
    await page.locator(`#lead-go-contacted-${lead}`).click();
    await expect(page.locator(`#lead-go-contacted-${lead}`)).toHaveAttribute('data-loading', '');
    await expect(card(page, lead)).toHaveCSS('opacity', '0.5');
    release();

    // One action, the two columns alone; the card now in Contacted, both counts followed, the rest the same nodes.
    await expectMove(requests, lead, 'new', 'contacted');
    await expect(column(page, 'contacted').locator(`#lead-card-${lead}`)).toHaveCount(1);
    await expect(column(page, 'new').locator(`#lead-card-${lead}`)).toHaveCount(0);
    await expect(column(page, 'new').locator(`#lead-card-${beside}`)).toHaveCount(1);
    await expectCount(page, 'new', newCount - 1);
    await expectCount(page, 'contacted', contactedCount + 1);

    for (const stage of STAGES.filter((other) => other !== 'new' && other !== 'contacted')) {
        expect(await kept(page, stage)).toBe(true);
    }

    // Focus is on the moved card's menu, in its new column; its menu now offers New and not Contacted.
    await expect(page.locator(`#lead-move-${lead}`)).toBeFocused();
    await expect(page.locator(`#lead-to-new-${lead}`)).toHaveCount(1);
    await expect(page.locator(`#lead-to-contacted-${lead}`)).toHaveCount(0);

    // Postgres: the stage, its status and one move, made by the manager.
    expect(stored(lead)).toEqual({ stage: 'contacted', status: 'open' });
    expect(moves(lead)).toEqual(['new>contacted']);
    expect(sql(`SELECT user_id FROM lead_moves WHERE lead_id = ${lead}`)).toEqual([manager.id]);

    // Lost closes it: the status follows the stage.
    await page.locator(`#lead-move-${lead}`).click();
    await page.locator(`#lead-go-lost-${lead}`).click();
    await expectMove(requests, lead, 'contacted', 'lost');
    await expect(column(page, 'lost').locator(`#lead-card-${lead}`)).toHaveCount(1);
    expect(stored(lead)).toEqual({ stage: 'lost', status: 'closed' });
    expect(moves(lead)).toEqual(['new>contacted', 'contacted>lost']);
    expect(errors).toEqual([]);
});
