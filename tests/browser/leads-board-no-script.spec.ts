import { expect, test } from '@playwright/test';
import { BOARD, card, column, insertLead, insertMember, moves, removeBoardLooks, stored } from './support/board';
import { signInAs } from './support/photos';

test.beforeEach(() => removeBoardLooks());
test.afterEach(() => removeBoardLooks());

test('with JavaScript off, a card\'s menu is a set of forms: a stage posts, and the board comes back with the notice and the card moved', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    const ann = insertMember('Plain Ann');
    const lead = insertLead('Plain Board Look', 'new', ann);
    await signInAs(page, ann.email, ann.password);

    // The list's Board tab is a plain link to the board.
    await page.goto('/app/leads');
    await page.locator('nav[aria-label="Lead views"] a', { hasText: 'Board' }).click();
    await expect(page).toHaveURL(/\/app\/leads\?view=board$/);
    await expect(column(page, 'new').locator(`#lead-card-${lead}`)).toHaveCount(1);

    // The menu opens as the browser opens a details, and shows its buttons; Appointment posts its form.
    await page.locator(`#lead-move-${lead}`).click();
    await expect(page.locator(`#lead-menu-${lead}`)).toHaveAttribute('open', '');
    await expect(page.locator(`#lead-go-appointment-${lead}`)).toBeVisible();
    const posted = page.waitForResponse((response) => response.url().endsWith(`/app/leads/${lead}/stage`) && response.request().method() === 'POST');
    // From the keyboard: with JavaScript off, Playwright never finds the open menu's button stable for a click, though
    // its box does not move, so the look focuses it and presses Enter, as a keyboard user sends it.
    await page.locator(`#lead-go-appointment-${lead}`).focus();
    await page.keyboard.press('Enter');
    expect((await posted).status()).toBe(303);

    // Back on the board, whole: the notice, and the card in Appointment.
    await expect(page).toHaveURL(new RegExp(`${BOARD.replace('?', '\\?')}$`));
    await expect(page.locator('#leads-notice')).toHaveText('Moved the lead from Plain Board Look to Appointment.');
    await expect(column(page, 'appointment').locator(`#lead-card-${lead}`)).toHaveCount(1);
    await expect(column(page, 'new').locator(`#lead-card-${lead}`)).toHaveCount(0);
    await expect(card(page, lead).locator(`#lead-to-new-${lead}`)).toHaveCount(1);
    expect(stored(lead)).toEqual({ stage: 'appointment', status: 'open' });
    expect(moves(lead)).toEqual(['new>appointment']);
    await context.close();
});
