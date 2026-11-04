// Bulk edit of the inventory list (#29, #43): the server empties the selection. An applied edit's answer carries one
// signal patch into the `content` region's scope, `{"selected":[]}` (AIC #253), which Tessera merges once the rows and
// the totals line have landed: the count hides and no box stays ticked. A refused edit's answer (422) carries none, and
// every ticked box stays ticked.

import { expect, test, type Page } from '@playwright/test';
import { signIn } from './support/inventory';
import { collectFormErrors } from './support/forms';
import { BULK, box, insertRecordedSale, LIST, removeBulkLooks, VINS } from './support/bulk-edit';
import { ready } from './support/tessera';
import { insertVehicle, testVehicle } from './support/vehicles';

const PATCH = '<script type="application/json" data-scope="content">{"selected":[]}</script>';

test.beforeEach(() => removeBulkLooks());
test.afterEach(() => removeBulkLooks());

// Takes each bulk edit's answer through a route, so its body can be read once the page has used it.
async function recordAnswers(page: Page): Promise<{ status: number; body: string }[]> {
    const answers: { status: number; body: string }[] = [];
    await page.route((url) => url.pathname === BULK, async (route) => {
        const response = await route.fetch({ maxRedirects: 0 });
        const body = await response.text();
        answers.push({ status: response.status(), body });
        await route.fulfill({ response, body });
    });

    return answers;
}

// Applies the bar's choice and returns the bulk edit's answer.
async function apply(page: Page, answers: { status: number; body: string }[], choice: string): Promise<{ status: number; body: string }> {
    const before = answers.length;
    await page.locator('#vehicles-bulk-choice').selectOption(choice);
    await page.locator('#vehicles-bulk-apply').click();
    await expect.poll(() => answers.length).toBe(before + 1);

    return answers[before];
}

test('an applied bulk edit empties the selection through its answer\'s signal patch; a refused one keeps it', async ({ page }) => {
    const errors = collectFormErrors(page);
    insertVehicle(testVehicle(VINS.first));
    insertVehicle(testVehicle(VINS.second));
    insertVehicle(testVehicle(VINS.sold));
    insertRecordedSale(VINS.sold);
    await signIn(page);
    await page.goto(LIST);
    await ready(page);
    const count = page.locator('#vehicles-bulk-count');
    const answers = await recordAnswers(page);

    // Refused: a status for a vehicle with a recorded sale. The answer patches no signal and every box stays ticked.
    await box(page, VINS.first).check();
    await box(page, VINS.sold).check();
    await expect(count).toHaveText('2 selected');
    const refused = await apply(page, answers, 'status:on_hold');
    expect(refused.status).toBe(422);
    expect(refused.body).not.toContain('data-scope');
    await expect(page.locator('#vehicles-table-outcome')).toContainText('Nothing was changed.');
    await expect(box(page, VINS.first)).toBeChecked();
    await expect(box(page, VINS.sold)).toBeChecked();
    await expect(count).toHaveText('2 selected');

    // Applied: the answer carries the one patch, after its templates, and the selection empties.
    await box(page, VINS.sold).uncheck();
    await box(page, VINS.second).check();
    await expect(count).toHaveText('2 selected');
    const applied = await apply(page, answers, 'status:on_hold');
    expect(applied.status).toBe(200);
    expect(applied.body.split('data-scope=')).toHaveLength(2);
    expect(applied.body).toContain(PATCH);
    expect(applied.body.indexOf(PATCH)).toBeGreaterThan(applied.body.lastIndexOf('</template>'));
    await expect(page.locator('#vehicles-table-outcome')).toHaveText('Set 2 vehicles to On hold.');
    await expect(box(page, VINS.first)).not.toBeChecked();
    await expect(box(page, VINS.second)).not.toBeChecked();
    await expect(count).toBeHidden();
    await expect(page.locator('#vehicles-bulk-apply')).toBeDisabled();
    expect(errors).toEqual([]);
});
