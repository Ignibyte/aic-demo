import { expect, test } from '@playwright/test';
import { collectFormErrors } from './support/forms';
import { column, countAt, expectCount, holdStream, insertLead, insertMember, openBoard, removeBoardLooks } from './support/board';

test.beforeEach(() => removeBoardLooks());
test.afterEach(() => removeBoardLooks());

test('a move one salesperson makes shows on another salesperson\'s open board, both columns and counts, without a reload', async ({ browser, page }) => {
    const errors = collectFormErrors(page);
    const ann = insertMember('Live Ann');
    const bo = insertMember('Live Bo');
    const lead = insertLead('Live Look', 'appointment', ann);

    // Bo watches the board; his document is marked, so a reload would show.
    const boContext = await browser.newContext();
    const boPage = await boContext.newPage();
    const boErrors = collectFormErrors(boPage);
    await openBoard(boPage, bo);
    await expect(column(boPage, 'appointment').locator(`#lead-card-${lead}`)).toHaveCount(1);
    await boPage.evaluate(() => {
        (window as unknown as { boardLook: string }).boardLook = 'same document';
    });

    // Ann moves her card to Won on her own board. Her stream is held back: a fresh connection sends every column
    // first, and that patch landing between her two clicks closes the menu she opened. Her move's own answer is
    // the PATCH; Bo's stream is the one this look follows.
    await holdStream(page);
    await openBoard(page, ann);
    const appointment = countAt('appointment');
    const won = countAt('won');
    await page.locator(`#lead-move-${lead}`).click();
    await page.locator(`#lead-go-won-${lead}`).click();
    await expect(column(page, 'won').locator(`#lead-card-${lead}`)).toHaveCount(1);

    // Bo's board follows through its stream: the card in Won, both counts, the same document.
    await expect(column(boPage, 'won').locator(`#lead-card-${lead}`)).toHaveCount(1, { timeout: 10_000 });
    await expect(column(boPage, 'appointment').locator(`#lead-card-${lead}`)).toHaveCount(0);
    await expectCount(boPage, 'appointment', appointment - 1);
    await expectCount(boPage, 'won', won + 1);
    await expect(boPage.locator(`#lead-menu-${lead}`)).toHaveCount(0);
    expect(await boPage.evaluate(() => (window as unknown as { boardLook?: string }).boardLook)).toBe('same document');
    await boContext.close();
    expect(boErrors).toEqual([]);
    expect(errors).toEqual([]);
});
