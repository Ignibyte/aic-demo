import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { firstCar } from './support/leads';
import { BOARD, STAGES, card, column, countAt, expectCount, insertLead, insertMember, openBoard, removeBoardLooks } from './support/board';
import { signInAs } from './support/photos';

test.beforeEach(() => removeBoardLooks());
test.afterEach(() => removeBoardLooks());

test('the list and the board are two tabs; the board shows the five stages, their counts and their cards, and a menu only to who may move a card', async ({ browser, page }) => {
    const errors = collectFormErrors(page);
    const car = firstCar();
    const manager = insertMember('Board Manager', 'sales_manager');
    const ann = insertMember('Board Ann');
    const bo = insertMember('Board Bo');
    const older = insertLead('Board Older', 'contacted', ann);
    const newer = insertLead('Board Newer', 'contacted', bo);
    const unassigned = insertLead('Board Nobody', 'won', null);

    // The list's tabs: List current, and Board, which opens the board in place.
    await signInAs(page, manager.email, manager.password);
    await page.goto('/app/leads');
    const views = page.locator('nav[aria-label="Lead views"]');
    await expect(views.locator('a')).toHaveText(['List', 'Board']);
    await expect(views.locator('a[aria-current="page"]')).toHaveText('List');
    await expect(views.locator('a', { hasText: 'Board' })).toHaveAttribute('href', BOARD);
    await page.evaluate(() => {
        (window as unknown as { boardLook: string }).boardLook = 'same document';
    });
    await views.locator('a', { hasText: 'Board' }).click();
    await expect(page).toHaveURL(/\/app\/leads\?view=board$/);
    await expect(page.locator('#leads-heading')).toHaveText('Leads board');
    await expect(page.locator('nav[aria-label="Lead views"] a[aria-current="page"]')).toHaveText('Board');
    expect(await page.evaluate(() => (window as unknown as { boardLook?: string }).boardLook)).toBe('same document');

    // The board: its anchor and stream, then the five columns in order, each with its count.
    await expect(page.locator('[data-t-anchor="leads-board"]')).toHaveCount(1);
    await expect(page.locator('#leads-board-columns')).toHaveAttribute('data-stream', '/app/leads/events');
    await expect(page.locator('#leads-board-columns > section')).toHaveCount(5);
    const headings = await page.locator('#leads-board-columns > section h3').evaluateAll((elements) => elements.map((element) => element.id));
    expect(headings).toEqual(STAGES.map((stage) => `leads-${stage}-heading`));

    for (const stage of STAGES) {
        await expect(column(page, stage)).toHaveAttribute('id', `leads-${stage}`);
        await expectCount(page, stage, countAt(stage));
    }

    // Contacted holds both, newest first; the unassigned one is in Won.
    const contacted = column(page, 'contacted').locator('[id^="lead-card-"]').filter({ hasText: 'Board ' });
    await expect(contacted.locator('a[id^="lead-name-"]')).toHaveText(['Board Newer', 'Board Older']);
    await expect(column(page, 'won').locator(`#lead-card-${unassigned}`)).toHaveCount(1);
    await expect(card(page, older)).toContainText(car.title);
    await expect(card(page, older).locator('p').last()).toHaveText(/^Question · Board Ann · \d{4}-\d{2}-\d{2}$/);
    await expect(card(page, unassigned).locator('p').last()).toContainText('Question · Unassigned · ');
    await expect(card(page, older).locator(`#lead-name-${older}`)).toHaveAttribute('href', `/app/leads/${older}`);

    // A manager may move every card: each drags and has a menu with one form per other stage.
    for (const id of [older, newer, unassigned]) {
        await expect(card(page, id)).toHaveAttribute('draggable', 'true');
        await expect(page.locator(`#lead-move-${id}`)).toHaveAttribute('data-focus-key', `lead-${id}`);
    }

    const forms = page.locator(`#lead-menu-${older} form`);
    await expect(forms).toHaveCount(4);
    expect(await forms.evaluateAll((elements) => elements.map((element) => element.id))).toEqual(
        ['new', 'appointment', 'won', 'lost'].map((stage) => `lead-to-${stage}-${older}`),
    );
    await expect(page.locator(`#lead-to-won-${older}`)).toHaveAttribute('action', `/app/leads/${older}/stage`);
    await expect(page.locator(`#lead-to-won-${older}`)).toHaveAttribute('method', 'post');
    await expect(page.locator(`#lead-to-won-${older} input[name="stage"]`)).toHaveValue('won');
    await expect(page.locator(`#lead-to-won-${older} input[name="aic_csrf"]`)).toHaveCount(1);
    await expect(page.locator(`#lead-go-won-${older}`)).toHaveText('Won');

    // Ann, a salesperson, may move her own card alone.
    const annContext = await browser.newContext();
    const annPage = await annContext.newPage();
    const annErrors = collectFormErrors(annPage);
    await openBoard(annPage, ann);
    await expect(annPage.locator(`#lead-menu-${older}`)).toHaveCount(1);
    await expect(card(annPage, older)).toHaveAttribute('draggable', 'true');

    for (const id of [newer, unassigned]) {
        await expect(card(annPage, id)).toBeVisible();
        await expect(annPage.locator(`#lead-menu-${id}`)).toHaveCount(0);
        await expect(card(annPage, id)).not.toHaveAttribute('draggable', 'true');
    }

    await annContext.close();
    expect(annErrors).toEqual([]);

    // A lead's page shows its stage as its last fact; its badge stays Open, and Closed for Won.
    await page.goto(`/app/leads/${older}`);
    await expect(page.locator('dl dt').last()).toHaveText('Stage');
    await expect(page.locator('dl dd').last()).toHaveText('Contacted');
    await expect(page.locator('#lead-status')).toHaveText('Open');
    await page.goto(`/app/leads/${unassigned}`);
    await expect(page.locator('dl dd').last()).toHaveText('Won');
    await expect(page.locator('#lead-status')).toHaveText('Closed');
    expect(errors).toEqual([]);
});
