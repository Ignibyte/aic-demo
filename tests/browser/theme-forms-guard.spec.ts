// The theme's own forms carry AIC's guard fields (AIC #246): the inventory's action bar and every "Move to" form on a
// lead card write their `<form>` for Tessera's attributes and place the `GuardFields` atom inside, so each holds the
// session's form token and the empty honeypot, as AIC's `Form` does. The lead card's forms held the token alone before.

import { expect, test, type Locator, type Page } from '@playwright/test';
import { insertLead, insertMember, openBoard, removeBoardLooks } from './support/board';
import { signIn } from './support/inventory';

test.beforeEach(() => removeBoardLooks());
test.afterEach(() => removeBoardLooks());

// Holds a form to one token field carrying the session's token, as the page's `tessera-csrf-token` meta gives it, and
// one honeypot, empty, inside AIC's hidden honeypot paragraph.
async function expectGuarded(page: Page, form: Locator): Promise<void> {
    const token = await page.locator('meta[name="tessera-csrf-token"]').getAttribute('content');
    expect(token).not.toBe('');
    const tokens = form.locator('input[type="hidden"][name="aic_csrf"]');
    await expect(tokens).toHaveCount(1);
    await expect(tokens).toHaveValue(token ?? '');
    const honeypots = form.locator('input[name="aic_website"]');
    await expect(honeypots).toHaveCount(1);
    await expect(honeypots).toHaveValue('');
    await expect(honeypots).toHaveAttribute('tabindex', '-1');
    await expect(form.locator('p.aic-honeypot[aria-hidden="true"] input[name="aic_website"]')).toHaveCount(1);
}

test("the inventory's action bar carries the form token and the honeypot", async ({ page }) => {
    await signIn(page);
    await expectGuarded(page, page.locator('form#vehicles-bulk'));
});

test('every move form on a lead card carries the form token and the honeypot', async ({ page }) => {
    const manager = insertMember('Guard Manager', 'sales_manager');
    const lead = insertLead('Guard Look', 'new', null);
    await openBoard(page, manager);
    const forms = page.locator(`#lead-menu-${lead} form`);
    await expect(forms).toHaveCount(4);

    for (let index = 0; index < 4; index += 1) {
        await expectGuarded(page, forms.nth(index));
    }
});
